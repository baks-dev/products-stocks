<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateOrderStatusByCompletedProductStocks
{
    private LoggerInterface $logger;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentOrderEventInterface $currentOrderEvent,
        private OrderStatusHandler $OrderStatusHandler,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private DeduplicatorInterface $deduplicator,
        LoggerInterface $ordersOrderLogger,
    )
    {
        $this->logger = $ordersOrderLogger;
    }

    /**
     * Обновляет статус заказа при доставке (Completed «Выдан по месту назначения»)
     */
    public function __invoke(ProductStockMessage $message): void
    {

        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        /** Если складская заявка не является статусом Completed «Выдан по месту назначения» */
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusCompleted::class))
        {
            return;
        }

        if($ProductStockEvent->getMoveOrder() !== null)
        {
            $this->logger
                ->warning(
                    'Не обновляем статус заказа: Заявка на перемещение по заказу между складами (ожидаем сборку на целевом складе и доставки клиенту)',
                    [self::class.':'.__LINE__, 'number' => $ProductStockEvent->getNumber()]
                );

            return;
        }

        /**
         * Получаем событие заказа.
         */
        $OrderEvent = $this->currentOrderEvent
            ->forOrder($ProductStockEvent->getOrder())
            ->execute();

        if(!$OrderEvent)
        {
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusCompleted::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->logger->info(
            'Обновляем статус заказа при доставке заказа в пункт назначения (выдан клиенту).',
            [self::class.':'.__LINE__, 'number' => $ProductStockEvent->getNumber()]
        );

        /** Обновляем статус заказа на Completed «Выдан по месту назначения» */
        $OrderStatusDTO = new OrderStatusDTO(
            OrderStatusCompleted::class,
            $OrderEvent->getId(),
            $ProductStockEvent->getProfile()
        );

        $this->OrderStatusHandler->handle($OrderStatusDTO);

        $Deduplicator->save();

        // Отправляем сокет для скрытия заказа у других менеджеров
        $this->CentrifugoPublish
            ->addData(['order' => (string) $ProductStockEvent->getOrder()])
            ->addData(['profile' => (string) $ProductStockEvent->getProfile()])
            ->send('orders');


        $this->logger->info(
            'Обновили статус заказа на Completed «Выдан по месту назначения»',
            [
                self::class.':'.__LINE__,
                'OrderUid' => (string) $ProductStockEvent->getOrder(),
                'UserProfileUid' => (string) $ProductStockEvent->getProfile()
            ]
        );

    }
}
