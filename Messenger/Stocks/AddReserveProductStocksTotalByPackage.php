<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\LockOrder\OrderUnlockMessage;
use BaksDev\Orders\Order\Messenger\MultiplyOrdersPackage\OrdersPackageByMultiplyMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\EditProductStockTotalMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalAccess\ProductStocksTotalAccessInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Если статус складской заявки Package «Упаковка» - Резервирование продукции на складе
 *
 * @note проверяет складские остатки продукции из СЗ
 * @note если складских остатков не хватает - отменяет существующую СЗ
 * @note Снимает блокировку с заказа
 *
 * @see MultiplyProductStocksPackageDispatcher
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 100)]
final readonly class AddReserveProductStocksTotalByPackage
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorageRepository,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ProductStocksTotalAccessInterface $ProductStocksTotalAccessRepository,
        private CurrentOrderEventInterface $currentOrderEventRepository,
        private DeleteProductStocksHandler $deleteProductStocksHandler,
        private UserByUserProfileInterface $UserByUserProfileRepository,
    ) {}

    public function __invoke(EditProductStockTotalMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }


        /** Новая складская заявка не должна иметь предыдущего события */
        if(true === ($message->getLast() instanceof ProductStockEventUid))
        {
            $DeduplicatorExecuted->save();
            return;
        }

        $ProductStockEvent = $this
            ->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->critical(
                sprintf('products-stocks: Событие складской заявки %s не было найдено', $message->getEvent()),
                [self::class.':'.__LINE__, var_export($message, true)]
            );
            return;
        }


        /** Если Статус НЕ является Package «Упаковка» - завершаем работу */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            $DeduplicatorExecuted->save();
            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->critical(
                sprintf('products-stocks: %s: Складская заявка не может определить ProductStocksInvariable',
                    $ProductStockEvent->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }

        $OrderEvent = $this->currentOrderEventRepository
            ->forOrder($ProductStockEvent->getOrder())
            ->find();

        /** Заказ, связанный со складской заявкой */
        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                sprintf('products-stocks: %s: Не найден заказ, связанный со складской заявкой',
                    $ProductStockEvent->getNumber()
                ),
                [self::class.':'.__LINE__, var_export($message, true)]
            );
            return;
        }

        $UserProfileUid = $ProductStockEvent->getInvariable()->getProfile();

        /** Получаем всю продукцию в складской заявке */
        $products = $ProductStockEvent->getProduct();

        if(true === $products->isEmpty())
        {
            $this->logger->warning(
                sprintf('%s: В складской заявке отсутствует продукция',
                    $ProductStockEvent->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }


        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            /** Проверяем остаток */
            $total = $this->ProductStocksTotalAccessRepository
                ->forProfile($UserProfileUid)
                ->forProduct($product->getProduct())
                ->forOfferConst($product->getOffer())
                ->forVariationConst($product->getVariation())
                ->forModificationConst($product->getModification())
                ->get();

            /** Недостаточно доступной продукции на складе */
            if($total < $product->getTotal())
            {
                $this->logger->warning(
                    sprintf('%s: Недостаточно доступной продукции на складе для изменения резерва',
                        $ProductStockEvent->getNumber()),
                    [self::class.':'.__LINE__]
                );

                /** Нужно отменить созданную складскую заявку */
                $DeleteProductStocksDTO = new DeleteProductStocksDTO();
                $ProductStockEvent->getDto($DeleteProductStocksDTO);

                $ProductStock = $this->deleteProductStocksHandler->handle($DeleteProductStocksDTO);

                if($ProductStock instanceof ProductStock)
                {
                    $this->logger->info(
                        sprintf(
                            '%s: Отменили складскую заявку при недостаточном количестве продукции на складе',
                            $ProductStockEvent->getNumber(),
                        ),
                        [self::class.':'.__LINE__]
                    );
                }

                /** Синхронно снимаем блокировку с заказа */

                $this->messageDispatch->dispatch(
                    message: new OrderUnlockMessage(
                        $OrderEvent->getMain(), self::class.':'.__LINE__
                    ),
                );

                return;
            }

            $this->logger->info(
                sprintf('%s: Добавляем резерв продукции на складе при создании заявки на упаковку',
                    $ProductStockEvent->getNumber()),
                ['total' => $product->getTotal(), self::class.':'.__LINE__],
            );

            $AddProductStocksReserve = new AddProductStocksReserveMessage(
                order: $message->getId(),
                profile: $UserProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification(),
            );

            $productTotal = $product->getTotal();


            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorageRepository
                ->forProfile($UserProfileUid)
                ->forProduct($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->count();

            if(false === $storage)
            {
                $this->logger->critical(
                    'Не найдено место складирования на складе для создания резерва при упаковке',
                    [
                        'profile' => (string) $UserProfileUid,
                        var_export($AddProductStocksReserve, true),
                        self::class.':'.__LINE__,
                    ],
                );

                continue;
            }


            /**
             * Если на складе одно место хранения -
             * обновляем сразу резерв на ВСЕ количество продукции из складской заявки
             */

            if($storage === 1)
            {
                $this->logger->info(
                    sprintf('%s: обновляем сразу резерв на ВСЕ количество (%s) продукции из складской заявки',
                        $ProductStockEvent->getNumber(),
                        $product->getTotal()
                    ),
                    [self::class.':'.__LINE__],
                );

                $AddProductStocksReserve
                    ->setIterate(1)
                    ->setTotal($productTotal);

                $this->messageDispatch->dispatch($AddProductStocksReserve);

                continue;
            }


            /**
             * Если на складе количество мест хранения несколько
             * - создаем резерв на КАЖДУЮ единицу продукции по местам -
             * от места, с меньшим количеством продукции, к большему
             *
             * @note на КАЖДУЮ единицу продукции будет запущен процесс резервирования остатков
             */

            $this->logger->info(
                sprintf('%s: создаем резерв на КАЖДУЮ единицу продукции по количеству мест складе - (%s)',
                    $ProductStockEvent->getNumber(),
                    $storage,
                ),
                [self::class.':'.__LINE__],
            );

            for($i = 1; $i <= $productTotal; $i++)
            {
                $AddProductStocksReserve
                    ->setIterate($i)
                    ->setTotal(1);

                $this->messageDispatch->dispatch($AddProductStocksReserve);

                if($i >= $productTotal)
                {
                    break;
                }
            }
        }


        /** Получаем текущее событие заказа из заявки */
        $OrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($ProductStockEvent->getOrder())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                sprintf(
                    'orders-order: Ошибка при получении информации о заказе при создании складской заявки %s',
                    $ProductStockEvent->getInvariable()->getNumber()
                ),
                [self::class.':'.__LINE__],
            );

            return;
        }

        $UserUid = $ProductStockEvent->getModifyUser();

        /** Получаем профиль пользователя в случае если заявка была создана системной */
        if(false === ($ProductStockEvent->getModifyUser() instanceof UserUid))
        {
            $User = $this->UserByUserProfileRepository
                ->forProfile($UserProfileUid)
                ->find();

            if(false === ($User instanceof User))
            {
                $this->logger->critical(
                    sprintf('products-stocks: Пользователь по профилю %s не найден', $UserProfileUid),
                    [self::class.':'.__LINE__, var_export($message, true)],
                );

                return;
            }

            $UserUid = $User->getId();
        }

        /** Бросаем сообщение на обновление статуса */
        $OrdersPackageByMultiplyMessage = new OrdersPackageByMultiplyMessage(
            $OrderEvent->getId(),
            $UserUid,
            $UserProfileUid,
            $OrderEvent->getComment(),
        );

        $this->messageDispatch->dispatch(
            message: $OrdersPackageByMultiplyMessage,
            transport: 'products-stocks'
        );

        $DeduplicatorExecuted->save();
    }
}
