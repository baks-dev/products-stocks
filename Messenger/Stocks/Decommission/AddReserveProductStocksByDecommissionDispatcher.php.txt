<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Messenger\Stocks\Decommission;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusDecommission;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Резервирование на складе продукции при статусе Decommission «Списание»
 */
#[AsMessageHandler(priority: 999)]
final readonly class AddReserveProductStocksByDecommissionDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $Deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifierRepository,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->Deduplicator
            ->namespace('orders-order')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $OrderEvent = $this->CurrentOrderEvent
            ->forOrder($message->getId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                'products-sign: Не найдено событие OrderEvent',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        if(false === $OrderEvent->isStatusEquals(OrderStatusDecommission::class))
        {
            return;
        }

        // Получаем всю продукцию в заказе
        $products = $OrderEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning(
                'Заказ не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        $UserProfileUid = $OrderEvent->getOrderProfile();

        /** @var OrderProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                'Добавляем резерв продукции на складе при списании',
                ['total' => $product->getTotal()],
            );

            /** Получаем активные идентификаторы карточки на случай, если товар обновлялся */
            $CurrentProductIdentifier = $this->CurrentProductIdentifierRepository
                ->forEvent($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->find();

            if(false === ($CurrentProductIdentifier instanceof CurrentProductIdentifierResult))
            {
                $this->logger->critical(
                    'products-sign: Продукт не найден',
                    [
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                        'modification' => (string) $product->getModification(),
                        self::class.':'.__LINE__,
                    ],
                );

                continue;
            }

            $AddProductStocksReserve = new AddProductStocksReserveMessage(
                order: $message->getId(),
                profile: $UserProfileUid,
                product: $CurrentProductIdentifier->getProduct(),
                offer: $CurrentProductIdentifier->getOfferConst(),
                variation: $CurrentProductIdentifier->getVariationConst(),
                modification: $CurrentProductIdentifier->getModificationConst(),
            );

            $productTotal = $product->getTotal();

            /** Проверяем количество мест складирования продукции на складе */
            $storage = $this->CountProductStocksStorage
                ->forProfile($UserProfileUid)
                ->forProduct($CurrentProductIdentifier->getProduct())
                ->forOffer($CurrentProductIdentifier->getOfferConst())
                ->forVariation($CurrentProductIdentifier->getVariationConst())
                ->forModification($CurrentProductIdentifier->getModificationConst())
                ->count();

            if(false === $storage)
            {
                $this->logger->critical(
                    'Не найдено место складирования на складе для создания резерва при списании',
                    [
                        'profile' => (string) $UserProfileUid,
                        var_export($AddProductStocksReserve, true),
                        self::class.':'.__LINE__,
                    ],
                );

                continue;
            }

            /**
             * Если на складе количество мест одно - обновляем сразу весь резерв
             */
            if($storage === 1)
            {
                $AddProductStocksReserve
                    ->setIterate(1)
                    ->setTotal($productTotal);

                $this->messageDispatch->dispatch($AddProductStocksReserve);

                continue;
            }


            /**
             * Если на складе количество мест несколько - создаем резерв на единицу продукции
             * для резерва по местам от меньшего к большему
             */

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

        $Deduplicator->save();
    }
}