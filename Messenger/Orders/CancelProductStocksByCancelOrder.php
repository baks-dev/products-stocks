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
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\ExistOrderEventByStatus\ExistOrderEventByStatusInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCanceled;
use BaksDev\Orders\Order\UseCase\Admin\Canceled\OrderCanceledDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CancelProductStocksByCancelOrder
{
    private LoggerInterface $logger;
    private CurrentOrderEventInterface $currentOrderEvent;
    private ProductStocksByOrderInterface $productStocksByOrder;
    private CancelProductStockHandler $cancelProductStockHandler;
    private ExistOrderEventByStatusInterface $existOrderEventByStatus;

    public function __construct(
        LoggerInterface $productsStocksLogger,
        CurrentOrderEventInterface $currentOrderEvent,
        ProductStocksByOrderInterface $productStocksByOrder,
        CancelProductStockHandler $cancelProductStockHandler,
        ExistOrderEventByStatusInterface $existOrderEventByStatus
    )
    {

        $this->logger = $productsStocksLogger;
        $this->currentOrderEvent = $currentOrderEvent;
        $this->productStocksByOrder = $productStocksByOrder;
        $this->cancelProductStockHandler = $cancelProductStockHandler;
        $this->existOrderEventByStatus = $existOrderEventByStatus;
    }


    /**
     * Отменяем складскую заявку при отмене заказа
     */

    public function __invoke(OrderMessage $message): void
    {
        /** Получаем активное состояние заказа */
        $OrderEvent = $this->currentOrderEvent->getCurrentOrderEvent($message->getId());

        if(!$OrderEvent)
        {
            return;
        }

        /** Если статус заказа не Canceled «Отменен» - завершаем обработчик */
        if(false === $OrderEvent->getStatus()->equals(OrderStatusCanceled::class))
        {
            return;
        }

        /** Не Отменяем складскую заявку если дублируется событие */
        $isOtherExists = $this->existOrderEventByStatus->isOtherExists(
            $message->getId(),
            $message->getEvent(),
            OrderStatusCanceled::class
        );

        if($isOtherExists)
        {
            return;
        }

        /** Получаем все заявки по идентификатору заказа */
        $stocks = $this->productStocksByOrder->findByOrder($message->getId());

        if(empty($stocks))
        {
            return;
        }

        /** @var ProductStockEvent $ProductStockEvent */
        foreach($stocks as $ProductStockEvent)
        {
            /** Если статус складской заявки Canceled «Отменен» - завершаем обработчик */
            if(true === $ProductStockEvent->getStatus()->equals(ProductStockStatusCancel::class))
            {
                continue;
            }

            $OrderCanceledDTO = new OrderCanceledDTO(new UserProfileUid());
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

            $this->logger->critical('Ошибка отмены складской заявки', [
                __FILE__.':'.__LINE__,
                'ProductStockEventUid' => (string) $ProductStockEvent->getId(),
                'OrderUid' => (string) $message->getId()
            ]);
        }
    }
}