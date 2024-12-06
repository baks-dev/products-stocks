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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Lock\AppLockInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Добавляет резерв продукции при перемещении
 */
#[AsMessageHandler(priority: 1)]
final readonly class AddReserveProductByProductStockMove
{
    private LoggerInterface $logger;

    public function __construct(
        private ProductStocksByIdInterface $productStocks,
        private ProductModificationQuantityInterface $modificationQuantity,
        private ProductVariationQuantityInterface $variationQuantity,
        private ProductOfferQuantityInterface $offerQuantity,
        private ProductQuantityInterface $productQuantity,
        private EntityManagerInterface $entityManager,
        private DeduplicatorInterface $deduplicator,
        LoggerInterface $productsProductLogger,
    )
    {
        $this->logger = $productsProductLogger;
    }

    /**
     * Добавляет резерв продукции при перемещении
     */
    public function __invoke(ProductStockMessage $message): void
    {

        $this->entityManager->clear();

        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        /** Если Статус не является Статус Moving «Перемещение» */
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusMoving::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $this->productStocks->getProductsMovingStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusMoving::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->entityManager->clear();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->changeReserve($product);
        }

        $Deduplicator->save();
    }


    public function changeReserve(ProductStockProduct $product): void
    {
        $ProductUpdateReserve = null;

        // Количественный учет модификации множественного варианта торгового предложения
        if(null === $ProductUpdateReserve && $product->getModification())
        {
            $this->entityManager->clear();

            $ProductUpdateReserve = $this->modificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );
        }

        // Количественный учет множественного варианта торгового предложения
        if(null === $ProductUpdateReserve && $product->getVariation())
        {
            $this->entityManager->clear();

            $ProductUpdateReserve = $this->variationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation()
            );
        }

        // Количественный учет торгового предложения
        if(null === $ProductUpdateReserve && $product->getOffer())
        {
            $this->entityManager->clear();

            $ProductUpdateReserve = $this->offerQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer()
            );
        }

        // Количественный учет продукта
        if(null === $ProductUpdateReserve)
        {
            $this->entityManager->clear();

            $ProductUpdateReserve = $this->productQuantity->getProductQuantity(
                $product->getProduct()
            );
        }


        $context = [
            self::class.':'.__LINE__,
            'total' => $product->getTotal(),
            'ProductUid' => (string) $product->getProduct(),
            'ProductStockEventUid' => (string) $product->getEvent()->getId(),
            'ProductOfferConst' => (string) $product->getOffer(),
            'ProductVariationConst' => (string) $product->getVariation(),
            'ProductModificationConst' => (string) $product->getModification(),
        ];

        if($ProductUpdateReserve && $ProductUpdateReserve->addReserve($product->getTotal()))
        {
            $this->entityManager->flush();
            $this->logger->info('Перемещение: Добавили общий резерв продукции в карточке', $context);
            return;
        }

        $this->logger->critical('Перемещение: Невозможно добавить общий резерв продукции (карточка не найдена)', $context);
    }
}
