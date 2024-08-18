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

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use BaksDev\Products\Stocks\Repository\ProductWarehouseByOrder\ProductWarehouseByOrderInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SubReserveProductStocksTotalByOrderComplete
{
    private EntityManagerInterface $entityManager;

    private ProductWarehouseByOrderInterface $warehouseByOrder;

    private LoggerInterface $logger;
    private MessageDispatchInterface $messageDispatch;
    private DeduplicatorInterface $deduplicator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductWarehouseByOrderInterface $warehouseByOrder,
        LoggerInterface $productsStocksLogger,
        MessageDispatchInterface $messageDispatch,
        DeduplicatorInterface $deduplicator
    ) {
        $this->entityManager = $entityManager;
        $this->warehouseByOrder = $warehouseByOrder;
        $this->logger = $productsStocksLogger;
        $this->messageDispatch = $messageDispatch;
        $this->deduplicator = $deduplicator;
    }

    /**
     * Снимаем резерв и остаток со склада при статусе заказа Completed «Выполнен»
     */
    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace(md5(self::class))
            ->deduplication([
                (string) $message->getId(),
                OrderStatusCompleted::STATUS
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->entityManager->clear();

        /** @var OrderEvent $OrderEvent */
        $OrderEvent = $this->entityManager
            ->getRepository(OrderEvent::class)
            ->find($message->getEvent());

        if(!$OrderEvent)
        {
            return;
        }

        /** Если статус заказа не Completed «Выполнен» */
        if(false === $OrderEvent->getStatus()->equals(OrderStatusCompleted::class))
        {
            return;
        }

        /**
         * Получаем склад, на который была отправлена заявка для сборки.
         *
         * @var UserProfileUid $UserProfileUid
         */
        $UserProfileUid = $this->warehouseByOrder->getWarehouseByOrder($message->getId());

        if($UserProfileUid)
        {
            /** @var OrderProduct $product */
            foreach($OrderEvent->getProduct() as $product)
            {
                /* Снимаем резерв со склада при доставке */
                $this->changeReserve($product, $UserProfileUid);
            }
        }

        $Deduplicator->save();
    }

    public function changeReserve(OrderProduct $product, UserProfileUid $profile): void
    {
        /** Получаем продукт */

        /** ID продукта */
        $ProductUid = $this->entityManager
            ->getRepository(ProductEvent::class)
            ->find($product->getProduct())?->getMain();

        /** Постоянный уникальный идентификатор ТП */
        $ProductOfferConst = $product->getOffer() ? $this->entityManager
            ->getRepository(ProductOffer::class)
            ->find($product->getOffer())?->getConst() : null;

        /** Постоянный уникальный идентификатор варианта */
        $ProductVariationConst = $product->getVariation() ? $this->entityManager
            ->getRepository(ProductVariation::class)
            ->find($product->getVariation())?->getConst() : null;

        /** Постоянный уникальный идентификатор модификации */
        $ProductModificationConst = $product->getModification() ? $this->entityManager
            ->getRepository(ProductModification::class)
            ->find($product->getModification())?->getConst() : null;

        $this->logger->info(
            'Снимаем резерв и остаток на складе при выполненном заказа',
            [
                self::class.':'.__LINE__,
                'total' => $product->getTotal(),
                'profile' => (string) $profile,
                'product' => (string) $product->getProduct(),
                'offer' => (string) $product->getOffer(),
                'variation' => (string) $product->getVariation(),
                'modification' => (string) $product->getModification(),

            ]
        );

        /** Снимаем резерв и остаток продукции на складе по одной единице продукции */
        for($i = 1; $i <= $product->getTotal(); $i++)
        {
            $SubProductStocksTotalMessage = new SubProductStocksTotalAndReserveMessage(
                $profile,
                $ProductUid,
                $ProductOfferConst,
                $ProductVariationConst,
                $ProductModificationConst
            );

            $this->messageDispatch->dispatch($SubProductStocksTotalMessage, transport: 'products-stocks');

            if($i === $product->getTotal())
            {
                break;
            }
        }
    }
}
