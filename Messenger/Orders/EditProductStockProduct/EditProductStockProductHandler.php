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

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockProduct;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\EditOrder\EditOrderMessage;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPhone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Запускает процесс редактирования складской заявки при изменении заказа, если заказ уже на упаковке
 */
#[AsMessageHandler(priority: 0)]
final class EditProductStockProductHandler
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $currentOrderEventRepository,
        private CentrifugoPublishInterface $publish,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(EditOrderMessage $message): void
    {
        $OrderEvent = $this->currentOrderEventRepository
            ->forOrder($message->getId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                message: sprintf('%s Не найдено активное событие заказа', $message->getId()),
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * Если заказ в статусе:
         *
         * - New «Новый»
         * - Phone «Не дозвонились»
         *
         * следовательно заказу еще не присвоен склад упаковки
         */
        if(
            true === $OrderEvent->isStatusEquals(OrderStatusNew::class)
            || true === $OrderEvent->isStatusEquals(OrderStatusPhone::class)
        )
        {
            return;
        }

        /** Скрываем заказ у всех пользователей */
        $this->publish
            ->addData(['order' => (string) $OrderEvent->getId()])
            ->send('orders');

        $this->messageDispatch->dispatch(
            message: new EditProductStockProductMessage(
                $OrderEvent->getMain(),
                $OrderEvent->getOrderProfile(),
                $OrderEvent->getOrderUser(),
                $OrderEvent->getOrderNumber(),
            ),
            transport: 'products-stocks',
        );
    }
}
