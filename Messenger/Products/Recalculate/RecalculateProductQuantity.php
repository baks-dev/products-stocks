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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Products\Recalculate;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Products\Product\Entity\Offers\Quantity\ProductOfferQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Quantity\ProductModificationQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Quantity\ProductVariationQuantity;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Делает перерасчет указанной продукции и присваивает в карточку
 */
#[AsMessageHandler(priority: 0)]
final readonly class RecalculateProductQuantity
{
    public function __construct(
        #[Target('productsProductLogger')] private LoggerInterface $logger,
        private ProductModificationQuantityInterface $modificationQuantity,
        private ProductVariationQuantityInterface $variationQuantity,
        private ProductOfferQuantityInterface $offerQuantity,
        private ProductQuantityInterface $productQuantity,
        private ProductStocksTotalInterface $productStocksTotal,
        private EntityManagerInterface $entityManager,
        private AppCacheInterface $cache,
    ) {}


    public function __invoke(RecalculateProductMessage $product): void
    {

        // Метод возвращает общее количество продукции на всех Логистических складах (без учета резерва)
        $ProductStocksTotal = $this->productStocksTotal
            ->product($product->getProduct())
            ->offer($product->getOffer())
            ->variation($product->getVariation())
            ->modification($product->getModification())
            ->onlyLogisticWarehouse()
            ->get();


        $ProductUpdateQuantity = null;

        /* Чистим кеш модуля продукции */
        $cache = $this->cache->init('products-product');
        $cache->clear();


        /**
         * Количественный учет модификации множественного варианта торгового предложения
         */

        if(null === $ProductUpdateQuantity && true === ($product->getModification() instanceof ProductModificationConst))
        {

            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->modificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification(),
            );

            if(false === ($ProductUpdateQuantity instanceof ProductModificationQuantity))
            {
                $this->logger->critical(
                    sprintf('products-stocks: Ошибка %s при перерасчете общего количества модификации множественного варианта торгового предложения в карточке', $ProductUpdateQuantity),
                    [
                        self::class.':'.__LINE__,
                        'total' => $ProductStocksTotal,
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                        'modification' => (string) $product->getModification(),
                    ],
                );

                return;
            }


            $ProductUpdateQuantity->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();

            $this->logger->info(
                'products-stocks: Обновили общее количество модификации множественного варианта торгового предложения в карточке',
                [
                    self::class.':'.__LINE__,
                    'total' => $ProductStocksTotal,
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                    'variation' => (string) $product->getVariation(),
                    'modification' => (string) $product->getModification(),
                ],
            );

            return;

        }





        /**
         * Количественный учет множественного варианта торгового предложения
         */


        if(null === $ProductUpdateQuantity && true === ($product->getVariation() instanceof ProductVariationConst))
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->variationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
            );

            if(false === ($ProductUpdateQuantity instanceof ProductVariationQuantity))
            {
                $this->logger->critical(
                    sprintf('products-stocks: Ошибка %s при перерасчете общего количества множественного варианта торгового предложения в карточке', $ProductUpdateQuantity),
                    [
                        self::class.':'.__LINE__,
                        'total' => $ProductStocksTotal,
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                    ],
                );

                return;
            }

            $ProductUpdateQuantity->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();

            $this->logger->info(
                'products-stocks: Обновили общее количество множественного варианта торгового предложения в карточке',
                [
                    self::class.':'.__LINE__,
                    'total' => $ProductStocksTotal,
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                    'variation' => (string) $product->getVariation(),
                ],
            );

            return;
        }


        /**
         * Количественный учет торгового предложения
         */

        if(null === $ProductUpdateQuantity && true == ($product->getOffer() instanceof ProductOfferConst))
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->offerQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer(),
            );

            if(false === ($ProductUpdateQuantity instanceof ProductOfferQuantity))
            {
                $this->logger->critical(
                    sprintf('products-stocks: Ошибка %s при перерасчете общего количества торгового предложения в карточке', $ProductUpdateQuantity),
                    [
                        self::class.':'.__LINE__,
                        'total' => $ProductStocksTotal,
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),

                    ],
                );

                return;
            }

            $ProductUpdateQuantity->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();

            $this->logger->info(
                'products-stocks: Обновили общее количество торгового предложения в карточке',
                [
                    self::class.':'.__LINE__,
                    'total' => $ProductStocksTotal,
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                ],
            );

            return;
        }


        /**
         * Количественный учет продукта
         */

        if(null === $ProductUpdateQuantity)
        {
            $this->entityManager->clear();

            $ProductUpdateQuantity = $this->productQuantity->getProductQuantity(
                $product->getProduct(),
            );


            if(false === ($ProductUpdateQuantity instanceof ProductPrice))
            {
                $this->logger->critical(
                    sprintf('products-stocks: Ошибка %s при перерасчете общего количества продукции в карточке', $ProductUpdateQuantity),
                    [
                        self::class.':'.__LINE__,
                        'total' => $ProductStocksTotal,
                        'product' => (string) $product->getProduct(),
                    ],
                );

                return;
            }


            $ProductUpdateQuantity->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();

            $this->logger->info(
                'products-stocks: Обновили общее количество продукции в карточке',
                [
                    self::class.':'.__LINE__,
                    'total' => $ProductStocksTotal,
                    'product' => (string) $product->getProduct(),
                ],
            );

        }

    }
}
