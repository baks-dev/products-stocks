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

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusExtradition;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляет статус заказа при сборке на складе
 */
#[AsMessageHandler(priority: 1)]
final readonly class UpdateOrderStatusByExtraditionProductStocksDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private UserByUserProfileInterface $UserByUserProfile,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private OrderStatusHandler $OrderStatusHandler,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(ProductStockMessage $message): void
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

        /** @var ProductStockEvent $ProductStockEvent */
        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /**
         * Заказ обновляется только при условии, что заявка Extradition «Укомплектована, готова к выдаче»
         */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusExtradition::class))
        {
            return;
        }

        /* Если упаковка складской заявки на перемещение - статус заказа не обновляем */
        if($ProductStockEvent->getMoveOrder())
        {
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

        /**
         * Получаем активное событие заказа.
         */

        $CurrentOrderEvent = $this->CurrentOrderEvent
            ->forOrder($ProductStockEvent->getOrder())
            ->find();

        if(false === ($CurrentOrderEvent instanceof OrderEvent))
        {
            return;
        }


        $this->logger->info(
            'Обновляем статус заказа на "Собран, готов к отправке" (Extradition)',
            [self::class.':'.__LINE__],
        );


        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();

        $OrderStatusDTO = new OrderStatusDTO
        (
            status: OrderStatusExtradition::class,
            id: $CurrentOrderEvent->getId(),
        )
            ->setProfile($UserProfileUid);


        /**
         * Присваиваем ответственное лицо если указан FIXED
         */
        if(true === ($ProductStockEvent->getFixed() instanceof UserProfileUid))
        {
            $User = $this->UserByUserProfile
                ->forProfile($ProductStockEvent->getFixed())
                ->find();

            if(false === ($User instanceof User))
            {
                $this->logger->critical(
                    'orders-order: Пользователь ответственного лица не найден',
                    [self::class.':'.__LINE__, 'fixed' => (string) $ProductStockEvent->getFixed()],
                );

                return;
            }

            $OrderStatusDTO
                ->getModify()
                ->setUsr($User->getId());
        }

        //$ModifyDTO = $OrderStatusDTO->getModify();
        //$ModifyDTO->setUsr($ProductStockEvent->getModifyUser());

        $handle = $this->OrderStatusHandler->handle($OrderStatusDTO);

        if(false === ($handle instanceof Order))
        {
            $this->logger->critical(
                'products-stocks: Ошибка при обновлении статуса заказа на Extradition «Готов к выдаче»',
                [$handle, $message, self::class.':'.__LINE__],
            );

            return;
        }

        $DeduplicatorExecuted->save();

        // Отправляем сокет для скрытия заказа у других менеджеров
        $this->CentrifugoPublish
            ->addData(['order' => (string) $ProductStockEvent->getOrder()])
            ->addData(['profile' => (string) $UserProfileUid])
            ->send('orders');


        $this->logger->info(
            'Обновили статус заказа на Extradition «Готов к выдаче»',
            [
                self::class.':'.__LINE__,
                'order' => (string) $ProductStockEvent->getOrder(),
                'profile' => (string) $UserProfileUid,
            ],
        );

    }
}
