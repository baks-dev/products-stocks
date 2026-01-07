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
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksReserve\SubProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Отменяет резерв на складе при отмене складской заявки
 *
 * @see CancelProductStocksByCancelOrderDispatcher
 */
#[AsMessageHandler(priority: 1)]
final readonly class SubReserveProductStockTotalByCancel
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private UserByUserProfileInterface $UserByUserProfileRepository,
        private EntityManagerInterface $entityManager,
    ) {}


    public function __invoke(ProductStockMessage $message): void
    {
        if(false === ($message->getLast() instanceof ProductStockEventUid))
        {
            return;
        }

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

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /** Если статус события заявки НЕ является Cancel «Отменен» - завершаем работу */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class))
        {
            return;
        }

        /**
         * Получаем предыдущее событие заявки (у заявки на отмену всегда должно быть предыдущее событие)
         */

        $LastProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getLast())
            ->find();

        if(false === ($LastProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /**
         * Не снимаем резерв на складе, если предыдущее событие заявки - Completed «Выдан по месту назначения».
         *
         * @note Резерв и остаток на складе был списан при завершении заказа
         * @see SubReserveProductStocksTotalByOrderCompleteDispatcher
         *
         */
        if($LastProductStockEvent->equalsProductStockStatus(ProductStockStatusCompleted::class))
        {
            return;
        }


        // Получаем всю продукцию в заявке
        $products = $ProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [
                self::class.':'.__LINE__,
                var_export($message, true),
            ]);

            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                'Складская заявка не может определить ProductStocksInvariable',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }


        /** Идентификатор профиля склада отгрузки, где производится отмена заявки */
        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();


        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                sprintf('%s: Отменяем резерв на складе при отмене складской заявки', $ProductStockEvent->getNumber()),
                [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            $SubProductStocksTotalCancelMessage = new SubProductStocksReserveMessage(
                stock: $message->getId(),
                profile: $UserProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification(),
            );


            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorage
                ->forProfile($UserProfileUid)
                ->forProduct($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->count();


            if(false === $storage)
            {
                /**
                 * Если при отмене нет остатка (например возврат) - создаем с новым остатком и таким же резервом
                 */

                /* Получаем идентификатор пользователя профиля */
                $User = $this->UserByUserProfileRepository
                    ->forProfile($UserProfileUid)
                    ->find();

                if(true === ($User instanceof User))
                {
                    /* Создаем новое место складирования на указанный профиль и пользователя */
                    $ProductStockTotal = new ProductStockTotal(
                        $User->getId(),
                        $UserProfileUid,
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification(),
                        $product->getStorage(),
                    );

                    $ProductStockTotal
                        ->addTotal($product->getTotal())
                        ->addReserve($product->getTotal());

                    $this->entityManager->persist($ProductStockTotal);
                    $this->entityManager->flush();

                    $storage = 1;
                }
                else
                {
                    $this->logger->critical(
                        sprintf('%s: Не найдено место складирования на складе для списания резерва при отмене', $ProductStockEvent->getNumber()),
                        [
                            self::class.':'.__LINE__,
                            var_export($message, true),
                        ],
                    );
                }
            }

            /**
             * Если на складе количество мест одно - снимаем сразу весь резерв
             */

            if($storage === 1)
            {
                $SubProductStocksTotalCancelMessage
                    ->setIterate(1)
                    ->setTotal($product->getTotal());

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalCancelMessage,
                    transport: 'products-stocks-low',
                );

                continue;
            }


            /**
             * Если на складе количество мест несколько - снимаем резерв на единицу продукции
             * для резерва по местам от меньшего к большему
             */

            $productTotal = $product->getTotal();

            for($i = 1; $i <= $productTotal; $i++)
            {
                $SubProductStocksTotalCancelMessage
                    ->setIterate($i)
                    ->setTotal(1);

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalCancelMessage,
                    transport: 'products-stocks-low',
                );

                if($i === $product->getTotal())
                {
                    break;
                }
            }
        }

        $DeduplicatorExecuted->save();
    }
}
