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

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use BaksDev\Products\Stocks\Repository\ProductWarehouseByOrder\ProductWarehouseByOrderInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимаем резерв и остаток со склада продукции при статусе заказа Completed «Выполнен»
 */
#[AsMessageHandler(priority: 60)]
final readonly class SubReserveProductStocksTotalByOrderCompleteDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private ProductWarehouseByOrderInterface $warehouseByOrder,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                OrderStatusCompleted::STATUS,
                self::class
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }


        $OrderEvent = $this->CurrentOrderEvent->forOrder($message->getId())->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            return;
        }

        /** Если статус заказа не Completed «Выполнен» */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCompleted::class))
        {
            return;
        }

        /**
         * Получаем склад, на который была отправлена заявка для сборки.
         *
         * @var UserProfileUid $UserProfileUid
         */
        $UserProfileUid = $this->warehouseByOrder
            ->forOrder($message->getId())
            ->getWarehouseByOrder();

        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            return;
        }

        /** @var OrderProduct $product */
        foreach($OrderEvent->getProduct() as $product)
        {
            /* Снимаем резерв со склада при доставке */
            $this->changeReserve($product, $UserProfileUid, $message->getId());
        }

        $DeduplicatorExecuted->save();
    }

    public function changeReserve(OrderProduct $product, UserProfileUid $profile, OrderUid $order): void
    {
        /** Получаем идентификаторы карточки */
        $CurrentProductDTO = $this->CurrentProductIdentifier
            ->forEvent($product->getProduct())
            ->forOffer($product->getOffer())
            ->forVariation($product->getVariation())
            ->forModification($product->getModification())
            ->find();

        if(false === ($CurrentProductDTO instanceof CurrentProductDTO))
        {
            $this->logger->critical(
                'products-stocks: Невозможно снять резерв и остаток на складе (карточка не найдена)',
                [$product, self::class.':'.__LINE__]
            );

            return;
        }

        /**
         * Снимаем резерв и остаток продукции на складе по одной единице продукции
         */

        $this->logger->info('Снимаем резерв и остаток на складе при выполненном заказа:');

        $productTotal = $product->getTotal();

        $SubProductStocksTotalMessage = new SubProductStocksTotalAndReserveMessage(
            order: $order,
            profile: $profile,
            product: $CurrentProductDTO->getProduct(),
            offer: $CurrentProductDTO->getOfferConst(),
            variation: $CurrentProductDTO->getVariationConst(),
            modification: $CurrentProductDTO->getModificationConst(),
        );

        for($i = 1; $i <= $productTotal; $i++)
        {
            $SubProductStocksTotalMessage
                ->setIterate($i);

            $this->messageDispatch->dispatch(
                message: $SubProductStocksTotalMessage,
                stamps: [new MessageDelay('3 seconds')],
                transport: 'products-stocks',
            );

            if($i === $product->getTotal())
            {
                break;
            }
        }
    }
}
