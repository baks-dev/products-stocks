<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCanceled;
use BaksDev\Orders\Order\UseCase\Admin\Canceled\OrderCanceledDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CancelOrderByCancelProductStocks
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CurrentOrderEventInterface $currentOrderEvent;
    private OrderStatusHandler $orderStatusHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $ordersOrderLogger,
        CurrentOrderEventInterface $currentOrderEvent,
        OrderStatusHandler $orderStatusHandler,
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $ordersOrderLogger;
        $this->currentOrderEvent = $currentOrderEvent;
        $this->orderStatusHandler = $orderStatusHandler;
    }


    /**
     * Отменяем заказ при отмене заявки
     */

    public function __invoke(ProductStockMessage $message): void
    {
        if(!$message->getLast())
        {
            return;
        }

        $this->entityManager->clear();

        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        // Если Статус не является Cancel «Отменен».
        if(!$ProductStockEvent || false === $ProductStockEvent->getStatus()->equals(ProductStockStatusCancel::class))
        {
            return;
        }

        // Если заявка не по заказу
        if(!$ProductStockEvent->getOrder())
        {
            return;
        }

        /** Получаем событие заказа */
        $OrderEvent = $this->currentOrderEvent->getCurrentOrderEvent($ProductStockEvent->getOrder());

        if(!$OrderEvent || $OrderEvent->isStatusEquals(OrderStatusCanceled::class))
        {
            return;
        }

        $CancelProductStockDTO = new CancelProductStockDTO();
        $ProductStockEvent->getDto($CancelProductStockDTO);

        $OrderCanceledDTO = new OrderCanceledDTO($ProductStockEvent->getProfile());
        $OrderEvent->getDto($OrderCanceledDTO);
        $OrderCanceledDTO->setComment($CancelProductStockDTO->getComment());

        $Order = $this->orderStatusHandler->handle($OrderCanceledDTO);

        if($Order instanceof Order)
        {
            $this->logger->info(sprintf('Отменили заказ при отмене заявки %s', $ProductStockEvent->getNumber()));
            return;
        }

        $this->logger->critical('Ошибка при отмене заказа', [
            __FILE__.':'.__LINE__,
            'ProductStockEvent' => (string) $message->getEvent(),
            'OrderUid' => (string) $ProductStockEvent->getOrder()
        ]);

    }
}