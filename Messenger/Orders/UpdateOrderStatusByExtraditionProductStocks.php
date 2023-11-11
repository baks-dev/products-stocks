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

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusExtradition;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class UpdateOrderStatusByExtraditionProductStocks
{
    //private ProductStocksByIdInterface $productStocks;

    private EntityManagerInterface $entityManager;

    private CurrentOrderEventInterface $currentOrderEvent;

    private OrderStatusHandler $OrderStatusHandler;

    private CentrifugoPublishInterface $CentrifugoPublish;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        CurrentOrderEventInterface $currentOrderEvent,
        OrderStatusHandler $OrderStatusHandler,
        CentrifugoPublishInterface $CentrifugoPublish,
        LoggerInterface $messageDispatchLogger,
    )
    {

        $this->entityManager = $entityManager;
        $this->currentOrderEvent = $currentOrderEvent;
        $this->OrderStatusHandler = $OrderStatusHandler;
        $this->CentrifugoPublish = $CentrifugoPublish;
        $this->logger = $messageDispatchLogger;
    }

    /** Обновляет статус заказа при сборке на складе  */
    public function __invoke(ProductStockMessage $message): void
    {

        /**
         * Получаем статус заявки.
         */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        // Если Статус складской заявки не является "Собран"
        if(!$ProductStockEvent || !$ProductStockEvent->getStatus()->equals(new ProductStockStatusExtradition()))
        {
            return;
        }

        /* Если упаковка складской заявки на перемещение - статус заказа не обновляем */
        if($ProductStockEvent->getMoveOrder())
        {
            return;
        }

        /**
         * Получаем событие заказа.
         */
        $OrderEvent = $this->currentOrderEvent->getCurrentOrderEventOrNull($ProductStockEvent->getOrder());

        if($OrderEvent)
        {
            /** Обновляем статус заказа на "Собран, готов к отправке" (Extradition) */
            $OrderStatusDTO = new OrderStatusDTO(new OrderStatus(new OrderStatusExtradition()), $OrderEvent->getId(), $ProductStockEvent->getProfile());
            $this->OrderStatusHandler->handle($OrderStatusDTO);

            // Отправляем сокет для скрытия заказа у других менеджеров
            $this->CentrifugoPublish
                ->addData(['order' => (string) $ProductStockEvent->getOrder()])
                ->addData(['profile' => (string) $ProductStockEvent->getProfile()])
                ->send('orders');


            $this->logger->info('Обновили статус заказа на "Собран, готов к отправке" (Extradition)',
                [
                    __FILE__.':'.__LINE__,
                    'order' => (string) $ProductStockEvent->getOrder(),
                    'profile' => (string) $ProductStockEvent->getProfile()
                ]);
        }


    }
}
