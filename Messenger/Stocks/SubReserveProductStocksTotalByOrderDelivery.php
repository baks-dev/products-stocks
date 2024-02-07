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
use BaksDev\DeliveryTransport\Type\OrderStatus\OrderStatusDelivery;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductWarehouseByOrder\ProductWarehouseByOrderInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SubReserveProductStocksTotalByOrderDelivery
{
    private EntityManagerInterface $entityManager;

    private ProductWarehouseByOrderInterface $warehouseByOrder;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductWarehouseByOrderInterface $warehouseByOrder,
        LoggerInterface $productsStocksLogger,
    ) {
        $this->entityManager = $entityManager;
        $this->entityManager->clear();

        $this->warehouseByOrder = $warehouseByOrder;
        $this->logger = $productsStocksLogger;
    }

    /**
     * Снимаем резерв со склада при статусе "ДОСТАВКА"
     */
    public function __invoke(OrderMessage $message): void
    {

        /* Снимаем резерв со склада при модуле доставки */
        if(!class_exists(OrderStatusDelivery::class))
        {
            return;
        }

        $OrderEvent = $this->entityManager->getRepository(OrderEvent::class)->find($message->getEvent());

        if(!$OrderEvent)
        {
            return;
        }

        /* Если статус заказа не Delivery «Доставка (погружен в транспорт)» */
        if (!$OrderEvent->getStatus()->equals(OrderStatusDelivery::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус заказа не Delivery «Доставка (погружен в транспорт)»',
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
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification(),
            );

            throw new DomainException($throw);
        }

        $ProductStockTotal->subReserve($product->getTotal());
        $ProductStockTotal->subTotal($product->getTotal());

        $this->logger->info('Сняли резерв и уменьшили количество на складе при «Доставка (погружен в транспорт)»',
            [
                __FILE__.':'.__LINE__,
                'profile' => $profile,
                'product' => $product->getProduct(),
                'offer' => $product->getOffer(),
                'variation' => $product->getVariation(),
                'modification' => $product->getModification(),
                'total' => $product->getTotal(),
            ]);

    }
}
