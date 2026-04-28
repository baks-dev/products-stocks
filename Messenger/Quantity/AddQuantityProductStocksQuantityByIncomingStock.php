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

namespace BaksDev\Products\Stocks\Messenger\Quantity;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEvent;
use BaksDev\Products\Stocks\Entity\Quantity\ProductStockQuantity;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Repository\Quantity\ProductStocksQuantityStorage\ProductStocksQuantityStorageInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Comment\ProductStockQuantityNewEditCommentDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\ProductStockQuantityNewEditDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\ProductStockQuantityNewEditHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Пополнение складских остатков при поступлении на склад либо при отмене выполненного заказа (ВОЗВРАТ)
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 1)]
final readonly class AddQuantityProductStocksQuantityByIncomingStock
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $Logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private UserByUserProfileInterface $UserByUserProfileRepository,
        private ProductStocksQuantityStorageInterface $ProductStocksQuantityStorageRepository,
        private ProductStockQuantityNewEditHandler $ProductStockQuantityNewEditHandler,
        private DeduplicatorInterface $Deduplicator,
        private CurrentProductIdentifierByConstInterface $CurrentProductIdentifierByConstRepository,
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->Deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                md5(self::class),
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем складскую заявку */
        $productStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($productStockEvent instanceof ProductStockEvent))
        {
            $this->Logger->error(
                sprintf('Событие складской заявки %s не было найдено', $message->getEvent()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }


        /**
         * Пополняем отстаток только если статус НЕ является Incoming «Приход на склад» либо Cancel «Отменен»
         */
        if(
            false === $productStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class) &&
            false === $productStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class)
        )
        {
            return;
        }


        /** Получаем предыдущее событие */
        $lastProductStockEvent = $this
            ->ProductStocksEventRepository
            ->forEvent($message->getLast())
            ->find();


        /**
         * Если статус Cancel «Отменен», и предыдущее событие НЕ является Completed «Выдан по месту назначения» при
         * возврате СЗ создается без предыдущего состояния
         */
        if(
            true === ($productStockEvent instanceof ProductStockEvent)
            && true === $productStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class)
            && false === $lastProductStockEvent->equalsProductStockStatus(ProductStockStatusCompleted::class)
        )
        {
            return;
        }


        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $productStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->Logger->warning(
                'Заявка не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }


        /** Идентификатор профиля склада при поступлении */
        $userProfileUid = $productStockEvent->getInvariable()?->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            if(empty($product->getTotal()))
            {
                $this->Logger->warning(
                    sprintf(
                        '%s: Не добавляем приход с нулевым количеством продукции',
                        $productStockEvent->getNumber(),
                    ),
                    [self::class.':'.__LINE__, var_export($message, true)],
                );

                continue;
            }


            /**
             * Находим ProductInvariable по константным значениям продукта
             */
            $currentProductIdentifierResult = $this->CurrentProductIdentifierByConstRepository
                ->forProduct($product->getProduct())
                ->forOfferConst($product->getOffer())
                ->forVariationConst($product->getVariation())
                ->forModificationConst($product->getModification())
                ->find();


            /** Получаем место для хранения указанной продукции данного профиля */
            $ProductStockQuantityEvent = $this->ProductStocksQuantityStorageRepository
                ->profile($userProfileUid)
                ->invariable($currentProductIdentifierResult->getProductInvariable())
                ->storage($product->getStorage())
                ->find();


            $productStockQuantityDTO = new ProductStockQuantityNewEditDTO();

            /** Если уже существует такое место складирования - сеттим на DTO */
            if(true === ($ProductStockQuantityEvent instanceof ProductStockQuantityEvent))
            {
                $ProductStockQuantityEvent->getDto($productStockQuantityDTO);
            }

            /** Если нужно создать новое место складирования на указанный профиль и пользователя */
            if(false === ($ProductStockQuantityEvent instanceof ProductStockQuantityEvent))
            {
                /* получаем пользователя профиля, для присвоения новому месту складирования */
                $user = $this->UserByUserProfileRepository
                    ->forProfile($userProfileUid)
                    ->find();

                if(false === ($user instanceof User))
                {
                    $this->Logger->error(
                        'Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                        [
                            self::class.':'.__LINE__,
                            'profile' => (string) $userProfileUid,
                        ],
                    );

                    throw new InvalidArgumentException('Ошибка при обновлении складских остатков.');
                }

                if(false === ($currentProductIdentifierResult instanceof CurrentProductIdentifierResult))
                {
                    $this->Logger->error(
                        sprintf('Продукт с идентификатором %s не был найден', $product->getProduct()),
                        [self::class.':'.__LINE__, var_export($message, true)],
                    );
                    return;
                }

                if(true === empty($currentProductIdentifierResult->getProductInvariable()))
                {
                    $this->Logger->error(
                        sprintf(
                            'У продукта с идентификатором %s отсутствует invariable',
                            $product->getProduct(),
                        ),
                        [self::class.':'.__LINE__, var_export($message, true)],
                    );
                    return;
                }

                $productStockQuantityDTO
                    ->getInvariable()
                    ->setUsr($user->getId())
                    ->setProfile($userProfileUid)
                    ->setInvariable($currentProductIdentifierResult->getProductInvariable())
                    ->setStorage($product->getStorage());

                /** При поступлении всегда подтверждаем остаток */
                $productStockQuantityDTO->getApprove()->setValue(true);

                $this->ProductStockQuantityNewEditHandler->handle($productStockQuantityDTO);

                $this->Logger->info(
                    'Место складирования не найдено! Создали новое место для указанной продукции',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $userProfileUid,
                    ],
                );
            }

            if(false === empty($productStockEvent->getComment()))
            {
                /** Сохраняем комментарий из прихода */
                $productStockQuantityCommentDTO = new ProductStockQuantityNewEditCommentDTO()
                    ->setValue($productStockEvent->getComment());

                $productStockQuantityDTO->setComment($productStockQuantityCommentDTO);
            }

            $this->Logger->info(
                sprintf('Добавляем приход продукции по заявке %s', $productStockEvent->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            $this->handle($productStockQuantityDTO, $product->getTotal());
        }

        $Deduplicator->save();
    }

    public function handle(ProductStockQuantityNewEditDTO $productStockQuantityDTO, int $total): void
    {
        /** При поступлении всегда подтверждаем остаток */
        $productStockQuantityDTO->getApprove()->setValue(true);

        $newTotal = $productStockQuantityDTO->getTotal() + $total;
        $productStockQuantityDTO->setTotal($newTotal);

        /** Добавляем приход на указанный профиль (склад) */
        $handle = $this->ProductStockQuantityNewEditHandler->handle($productStockQuantityDTO);

        if(false === ($handle instanceof ProductStockQuantity))
        {
            $this->Logger->critical(
                'Ошибка при обновлении складских остатков',
                [
                    'ProductInvariable' => (string) $productStockQuantityDTO->getInvariable()->getInvariable(),
                    self::class.':'.__LINE__,
                ],
            );

            return;
        }

        $this->Logger->info(
            'Добавили приход продукции на склад',
            [
                'ProductStockQuantityUid' => (string) $handle->getId(),
                self::class.':'.__LINE__,
            ],
        );
    }
}
