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

namespace BaksDev\Products\Stocks\Messenger\Orders;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use BaksDev\Orders\Order\UseCase\Admin\Canceled\CanceledOrderDTO;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Отменяем складскую заявку на продукцию при отмене заказа
 */
#[AsMessageHandler(priority: 8)]
final readonly class CancelProductStocksByCancelOrderDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $currentOrderEvent,
        private ProductStocksByOrderInterface $productStocksByOrder,
        private CancelProductStockHandler $cancelProductStockHandler,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем активное состояние заказа */
        $OrderEvent = $this->currentOrderEvent
            ->forOrder($message->getId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                'products-stocks: Не найдено событие OrderEvent',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * Складскую заявку можно отменить только при условии, если заказ со статусом Canceled «Отменен»
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCanceled::class))
        {
            return;
        }

        /** Получаем все заявки по идентификатору заказа */
        $stocks = $this->productStocksByOrder
            ->onOrder($message->getId())
            ->findAll();

        if(empty($stocks))
        {
            return;
        }

        /** @var ProductStockEvent $ProductStockEvent */
        foreach($stocks as $ProductStockEvent)
        {
            /** Если статус складской заявки Canceled «Отменен» - пропускаем */
            if(true === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class))
            {
                continue;
            }

            /**
             * Присваиваем рандомные пользователя и профиль,
             * т.к. при отмене заявки нам важен только комментарий
             */
            $OrderCanceledDTO = new CanceledOrderDTO();
            $OrderEvent->getDto($OrderCanceledDTO);

            $CancelProductStockDTO = new CancelProductStockDTO();
            $ProductStockEvent->getDto($CancelProductStockDTO);
            $CancelProductStockDTO->setComment($OrderCanceledDTO->getComment());

            $ProductStock = $this->cancelProductStockHandler->handle($CancelProductStockDTO);

            if($ProductStock instanceof ProductStock)
            {
                $this->logger->info(sprintf('Отменили складскую заявку %s при отмене заказа', $ProductStockEvent->getNumber()));
                continue;
            }

            $this->logger->critical(
                sprintf('products-stocks: Ошибка %s отмены складской заявки при отмене заказа %s', $ProductStock, $ProductStockEvent->getNumber()),
                [
                    self::class.':'.__LINE__,
                    'ProductStockEventUid' => (string) $ProductStockEvent->getId(),
                    'OrderUid' => (string) $message->getId(),
                ]);
        }

        $Deduplicator->save();
    }
}
