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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Orders;

use BaksDev\Centrifugo\BaksDevCentrifugoBundle;
use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\LockOrder\OrderUnlockMessage;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusReturn;
use BaksDev\Orders\Order\UseCase\Admin\Canceled\CanceledOrderDTO;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Если статус заказа Canceled «Отменен» или Return «Возврат» - отменяем складскую заявку
 *
 * @note Снимает блокировку с заказа
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 8)]
final readonly class CancelProductStocksByCancelOrderDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private MessageDispatchInterface $messageDispatch,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ProductStocksByOrderInterface $ProductStocksByOrderRepository,
        private CancelProductStockHandler $cancelProductStockHandler,
        private ?CentrifugoPublishInterface $centrifugoPublish = null,
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
        $OrderEvent = $this->CurrentOrderEventRepository
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
         * Складскую заявку можно отменить только при условии, если заказ со статусом:
         * - Canceled «Отменен»
         * - Return «Возврат»
         */
        if(
            false === $OrderEvent->isStatusEquals(OrderStatusCanceled::class)
            && false === $OrderEvent->isStatusEquals(OrderStatusReturn::class)
        )
        {
            return;
        }

        /**
         * Получаем все заявки по идентификатору заказа
         * @note в массиве должна быть максимум одна складская заявка
         */
        $stocks = $this->ProductStocksByOrderRepository
            ->onOrder($message->getId())
            ->findAll();

        if(true === empty($stocks))
        {
            return;
        }

        /** @var ProductStockEvent $ProductStockEvent */
        foreach($stocks as $ProductStockEvent)
        {

            if(true === class_exists(BaksDevCentrifugoBundle::class))
            {
                /** Скрываем складскую заявку */
                $this->centrifugoPublish
                    ->addData([
                        'identifier' => (string) $ProductStockEvent->getMain(),
                        'context' => self::class.':'.__LINE__,
                    ])
                    ->send('remove');
            }

            /**
             * Получаем комментарий из заказа при его отмене
             */
            $OrderCanceledDTO = new CanceledOrderDTO();
            $OrderEvent->getDto($OrderCanceledDTO);

            $CancelProductStockDTO = new CancelProductStockDTO();
            $ProductStockEvent->getDto($CancelProductStockDTO);
            $CancelProductStockDTO->setComment($OrderCanceledDTO->getComment());

            $ProductStock = $this->cancelProductStockHandler->handle($CancelProductStockDTO);

            if(false === $ProductStock instanceof ProductStock)
            {
                $this->logger->critical(
                    message: sprintf(
                        'products-stocks: Ошибка %s отмены складской заявки при отмене заказа %s',
                        $ProductStock, $ProductStockEvent->getNumber()
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        'ProductStockEventUid' => (string) $ProductStockEvent->getId(),
                        'OrderUid' => (string) $message->getId(),
                    ]);
            }

            $this->logger->info(
                message: sprintf('%s: Отменили складскую заявку при возврате заказа', $ProductStockEvent->getNumber()),
                context: [self::class.':'.__LINE__],
            );

            /** Синхронно снимаем блокировку с заказа */

            $this->messageDispatch->dispatch(
                message: new OrderUnlockMessage(
                    $ProductStockEvent->getOrder(), self::class.':'.__LINE__
                ),
            );

        }

        $Deduplicator->save();
    }
}
