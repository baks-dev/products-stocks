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

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\DeliveryTransport\Type\OrderStatus\OrderStatusDelivery;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusExtradition;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusNew;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalMessage;
use BaksDev\Products\Stocks\Repository\ProductWarehouseByOrder\ProductWarehouseByOrderInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SubReserveProductStocksTotalByOrderComplete
{
    private EntityManagerInterface $entityManager;

    private ProductWarehouseByOrderInterface $warehouseByOrder;

    private LoggerInterface $logger;
    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductWarehouseByOrderInterface $warehouseByOrder,
        LoggerInterface $productsStocksLogger,
        MessageDispatchInterface $messageDispatch
    ) {
        $this->entityManager = $entityManager;
        $this->entityManager->clear();

        $this->warehouseByOrder = $warehouseByOrder;
        $this->logger = $productsStocksLogger;
        $this->messageDispatch = $messageDispatch;
    }

    /**
     * Снимаем резерв со склада при статусе "ВЫПОЛНЕН" если предыдущее событие было "СОБРАН"
     * (например при самовывозе с «Собран» перевели в статус «Выполнен»)
     */
    public function __invoke(OrderMessage $message): void
    {
        $this->entityManager->clear();

        /** @var OrderEvent $OrderEvent */
        $OrderEvent = $this->entityManager->getRepository(OrderEvent::class)->find($message->getEvent());

        if(!$OrderEvent)
        {
            return;
        }

        /* Если статус заказа не Completed «Выполнен» */
        if (!$OrderEvent->getStatus()->equals(OrderStatusCompleted::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус заказа не Completed «Выполнен»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

            return;
        }


        $lastOrderEvent = $this->entityManager->getRepository(OrderEvent::class)->find($message->getLast());

        if(!$lastOrderEvent)
        {
            return;
        }

        /* Если статус предыдущего события заказа не Extradition «Готов к выдаче» */
        if (!$lastOrderEvent->getStatus()->equals(OrderStatusExtradition::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус предыдущего события не Extradition «Готов к выдаче»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

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
            foreach ($OrderEvent->getProduct() as $product)
            {
                /* Снимаем резерв со склада при доставке */
                $this->changeReserve($product, $UserProfileUid);
            }
        }
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

        $ProductStockTotal = $this->entityManager
            ->getRepository(ProductStockTotal::class)
            ->findOneBy(
                [
                    'profile' => $profile,
                    'product' => $ProductUid,
                    'offer' => $ProductOfferConst,
                    'variation' => $ProductVariationConst,
                    'modification' => $ProductModificationConst
                ]
            );

        if (!$ProductStockTotal)
        {
            $throw = sprintf(
                'Невозможно снять резерв с продукции, которой нет на складе (warehouse: %s, product: %s, offer: %s, variation: %s, modification: %s)',
                $profile,
                $ProductUid,
                $ProductOfferConst,
                $ProductVariationConst,
                $ProductModificationConst,
            );

            throw new DomainException($throw);
        }

        /** Снимаем резерв и остаток продукции на складе */
        for($i = 1; $i <= $product->getTotal(); $i++)
        {
            $SubProductStocksTotalMessage = new SubProductStocksTotalMessage(
                $profile,
                $ProductUid,
                $ProductOfferConst,
                $ProductVariationConst,
                $ProductModificationConst
            );

            $this->messageDispatch->dispatch($SubProductStocksTotalMessage, transport: 'products-stocks');
        }

        //$ProductStockTotal->subReserve($product->getTotal());
        //$ProductStockTotal->subTotal($product->getTotal());
        //$this->entityManager->flush();

        $this->logger->info('Сняли резерв и уменьшили количество на складе при самовывозе',
            [
                __FILE__.':'.__LINE__,
                'profile' => $profile->getValue(),
                'product' => $product->getProduct()->getValue(),
                'offer' => $product->getOffer()?->getValue(),
                'variation' => $product->getVariation()?->getValue(),
                'modification' => $product->getModification()?->getValue(),
                'total' => $product->getTotal(),
            ]);


    }
}
