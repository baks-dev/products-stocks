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
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusDecommission;
use BaksDev\Orders\Order\UseCase\Admin\Edit\EditOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Edit\Products\OrderProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 *  Списываем со склада нужное количество и резерв, если создается заказ со статусом Decommission «Списание»
 */
#[AsMessageHandler(priority: 900)]
final readonly class SubProductTotalAndReserveByDecommissionDispatcher
{
    public function __construct(
        private DeduplicatorInterface $deduplicator,
        private OrderEventInterface $OrderEventRepository,
        #[Target('ordersOrderLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private CurrentProductIdentifierInterface $CurrentProductIdentifierRepository,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('orders-order')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $OrderEvent = $this->OrderEventRepository->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                'products-sign: Не найдено событие OrderEvent',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /** Если статус не "СПИСАНИЕ" - завершаем обработчик */
        if(false === $OrderEvent->isStatusEquals(OrderStatusDecommission::class))
        {
            return;
        }

        /** Получаем активное событие заказа в случае если статус заказа изменился */
        if(empty($OrderEvent->getOrderNumber()))
        {
            $OrderEvent = $this->CurrentOrderEvent
                ->forOrder($message->getId())
                ->find();

            if(false === ($OrderEvent instanceof OrderEvent))
            {
                $this->logger->critical(
                    'orders-order: Не найдено событие OrderEvent',
                    [self::class.':'.__LINE__, var_export($message, true)],
                );

                return;
            }
        }

        $this->logger->info(
            sprintf(
                '%s: Снимаем резерв и остаток продукции на складе при списании (см. products-stock.log)',
                $OrderEvent->getOrderNumber(),
            ),
            [
                'status' => OrderStatusDecommission::class,
                'deduplicator' => $Deduplicator->getKey(),
            ],
        );

        $EditOrderDTO = new EditOrderDTO();
        $OrderEvent->getDto($EditOrderDTO);

        /** @var OrderProductDTO $product */
        foreach($EditOrderDTO->getProduct() as $product)
        {
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

            /** Списываем складской остаток */
            $this->messageDispatch->dispatch(new SubProductStocksTotalAndReserveMessage(
                $OrderEvent->getMain(),
                $OrderEvent->getOrderProfile(),
                $CurrentProductIdentifier->getProduct(),
                $CurrentProductIdentifier->getOfferConst(),
                $CurrentProductIdentifier->getVariationConst(),
                $CurrentProductIdentifier->getModificationConst(),
            )->setTotal($product->getPrice()->getTotal()));

            $this->logger->info(
                sprintf(
                    'Product %s: Снимаем резерв и остаток продукта на складе при списании (см. products-stock.log)',
                    $CurrentProductIdentifier->getProduct(),
                ),
            );
        }

        $Deduplicator->save();
    }
}