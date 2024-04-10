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

namespace BaksDev\Products\Stocks\Messenger\Products\Recalculate;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class RecalculateProductQuantity
{
    private EntityManagerInterface $entityManager;
    private ProductModificationQuantityInterface $modificationQuantity;
    private ProductVariationQuantityInterface $variationQuantity;
    private ProductOfferQuantityInterface $offerQuantity;
    private ProductQuantityInterface $productQuantity;
    private LoggerInterface $logger;
    private ProductStocksTotalInterface $productStocksTotal;
    private AppCacheInterface $cache;

    public function __construct(
        ProductModificationQuantityInterface $modificationQuantity,
        ProductVariationQuantityInterface $variationQuantity,
        ProductOfferQuantityInterface $offerQuantity,
        ProductQuantityInterface $productQuantity,
        ProductStocksTotalInterface $productStocksTotal,

        EntityManagerInterface $entityManager,
        LoggerInterface $productsStocksLogger,
        AppCacheInterface $cache
    ) {

        $this->modificationQuantity = $modificationQuantity;
        $this->variationQuantity = $variationQuantity;
        $this->offerQuantity = $offerQuantity;
        $this->productQuantity = $productQuantity;

        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->productStocksTotal = $productStocksTotal;
        $this->cache = $cache;
    }

    /**
     * Делает перерасчет указанной продукции и присваивает в карточку
     */
    public function __invoke(RecalculateProductMessage $product): void
    {

        $ProductUpdateQuantity = null;

        // Количественный учет модификации множественного варианта торгового предложения
        if(null === $ProductUpdateQuantity && $product->getModification())
        {

            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->modificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );
        }

        // Количественный учет множественного варианта торгового предложения
        if(null === $ProductUpdateQuantity && $product->getVariation())
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->variationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation()
            );
        }

        // Количественный учет торгового предложения
        if(null === $ProductUpdateQuantity && $product->getOffer())
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->offerQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer()
            );
        }

        // Количественный учет продукта
        if(null === $ProductUpdateQuantity)
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->productQuantity->getProductQuantity(
                $product->getProduct()
            );
        }

        if($ProductUpdateQuantity)
        {
            $ProductStocksTotal = $this->productStocksTotal->getProductStocksTotal(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );

            $ProductUpdateQuantity->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();

            $this->logger->info('Обновили общее количество продукции в карточке',
                [
                    __FILE__.':'.__LINE__,
                    'total' => $ProductStocksTotal,
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                    'variation' => (string) $product->getVariation(),
                    'modification' => (string) $product->getModification(),
                ]);
        }

        /* Чистим кеш модуля продукции */
        $cache = $this->cache->init('products-product');
        $cache->clear();

    }
}