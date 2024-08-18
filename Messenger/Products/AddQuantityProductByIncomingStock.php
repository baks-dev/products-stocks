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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Lock\AppLockInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddQuantityProductByIncomingStock
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private ProductModificationQuantityInterface $modificationQuantity;
    private ProductVariationQuantityInterface $variationQuantity;
    private ProductOfferQuantityInterface $offerQuantity;
    private ProductQuantityInterface $productQuantity;
    private LoggerInterface $logger;
    private DeduplicatorInterface $deduplicator;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        ProductModificationQuantityInterface $modificationQuantity,
        ProductVariationQuantityInterface $variationQuantity,
        ProductOfferQuantityInterface $offerQuantity,
        ProductQuantityInterface $productQuantity,
        EntityManagerInterface $entityManager,
        LoggerInterface $productsProductLogger,
        DeduplicatorInterface $deduplicator
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->modificationQuantity = $modificationQuantity;
        $this->variationQuantity = $variationQuantity;
        $this->offerQuantity = $offerQuantity;
        $this->productQuantity = $productQuantity;
        $this->logger = $productsProductLogger;
        $this->deduplicator = $deduplicator;
    }

    /**
     * Пополнение наличием продукции в карточке при поступлении на склад
     */
    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace(md5(self::class))
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusIncoming::STATUS
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }


        $this->entityManager->clear();

        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        // Если статус не является Incoming «Приход на склад»
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusIncoming::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());

        if($products)
        {
            $this->entityManager->clear();

            /** @var ProductStockProduct $product */
            foreach($products as $product)
            {
                /** Снимаем резерв отмененного заказа */
                $this->changeTotal($product);
            }
        }

        $Deduplicator->save();
    }

    public function changeTotal(ProductStockProduct $product): void
    {
        $context = [
            self::class.':'.__LINE__,
            'total' => $product->getTotal(),
            'ProductUid' => (string) $product->getProduct(),
            'ProductStockEventUid' => (string) $product->getEvent()->getId(),
            'ProductOfferConst' => (string) $product->getOffer(),
            'ProductVariationConst' => (string) $product->getVariation(),
            'ProductModificationConst' => (string) $product->getModification(),
        ];

        /**
         * Количественный учет модификации множественного варианта торгового предложения
         */
        if($product->getModification())
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->modificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );

            if($ProductUpdateQuantity)
            {
                $ProductUpdateQuantity->addQuantity($product->getTotal());
                $this->entityManager->flush();
                $this->logger->info('Поступление на склад: Пополнили общий остаток модификации множественного варианта в карточке', $context);
            }
            else
            {
                $this->logger->critical('Поступление на склад: Невозможно добавить общий остаток модификации множественного варианта: карточка не найдена)', $context);
            }

            return;
        }


        /**
         * Количественный учет множественного варианта торгового предложения
         */
        if($product->getVariation())
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->variationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation()
            );

            if($ProductUpdateQuantity)
            {
                $ProductUpdateQuantity->addQuantity($product->getTotal());
                $this->entityManager->flush();
                $this->logger->info('Поступление на склад: Пополнили общий остаток множественного варианта в карточке', $context);
            }
            else
            {
                $this->logger->critical('Поступление на склад: Невозможно добавить общий остаток множественного варианта: карточка не найдена)', $context);
            }

            return;
        }


        /**
         * Количественный учет торгового предложения
         */
        if($product->getOffer())
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->offerQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer()
            );

            if($ProductUpdateQuantity)
            {
                $ProductUpdateQuantity->addQuantity($product->getTotal());
                $this->entityManager->flush();
                $this->logger->info('Поступление на склад: пополнили общий остаток торгового предложения в карточке', $context);
            }
            else
            {
                $this->logger->critical('Поступление на склад: Невозможно добавить общий остаток торгового предложения: карточка не найдена)', $context);
            }
        }


        /**
         * Количественный учет продукта
         */

        $this->entityManager->clear();

        $ProductUpdateQuantity = $this->productQuantity->getProductQuantity(
            $product->getProduct()
        );

        if($ProductUpdateQuantity)
        {
            $ProductUpdateQuantity->addQuantity($product->getTotal());
            $this->entityManager->flush();
            $this->logger->info('Поступление на склад: Пополнили общий остаток продукции в карточке', $context);
        }
        else
        {
            $this->logger->critical('Поступление на склад: Невозможно добавить общий остаток продукции: карточка не найдена)', $context);
        }
    }
}
