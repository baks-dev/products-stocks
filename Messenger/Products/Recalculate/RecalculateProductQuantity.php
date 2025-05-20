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

namespace BaksDev\Products\Stocks\Messenger\Products\Recalculate;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class RecalculateProductQuantity
{
    public function __construct(
        #[Target('productsProductLogger')] private LoggerInterface $logger,
        private ProductStocksTotalInterface $productStocksTotal,
        private EntityManagerInterface $entityManager,
        private AppCacheInterface $cache,

        private CurrentProductIdentifierByConstInterface $CurrentProductIdentifierByConst
    ) {}

    /**
     * Делает перерасчет указанной продукции и присваивает в карточку
     */
    public function __invoke(RecalculateProductMessage $product): void
    {
        $CurrentProductIdentifierResult = $this
            ->CurrentProductIdentifierByConst
            ->forProduct($product->getProduct())
            ->forOfferConst($product->getOffer())
            ->forVariationConst($product->getVariation())
            ->forModificationConst($product->getModification())
            ->find();


        if(false === ($CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult))
        {
            $this->logger->info(
                'products-stocks: Карточка товара для перерасчета продукции не найдена',
                [
                    self::class.':'.__LINE__,
                    var_export($product, true),
                ],
            );

            return;
        }


        /**
         * Метод возвращает общее количество продукции на всех складах (без учета резерва)
         */
        $ProductStocksTotal = $this->productStocksTotal
            ->product($product->getProduct())
            ->offer($product->getOffer())
            ->variation($product->getVariation())
            ->modification($product->getModification())
            ->get();

        /**
         * Количественный учет модификации множественного варианта торгового предложения
         */
        if($CurrentProductIdentifierResult->getModification() instanceof ProductModificationUid)
        {
            $ProductModification = $this->entityManager
                ->getRepository(ProductModification::class)
                ->find($CurrentProductIdentifierResult->getModification());

            if($ProductModification instanceof ProductModification)
            {
                $ProductModification->setQuantity($ProductStocksTotal);
                $this->entityManager->flush();
                $this->cacheClear();

                return;
            }
        }

        /**
         * Количественный учет множественного варианта торгового предложения
         */

        if($CurrentProductIdentifierResult->getVariation() instanceof ProductVariationUid)
        {
            $ProductVariation = $this->entityManager
                ->getRepository(ProductVariation::class)
                ->find($CurrentProductIdentifierResult->getVariation());

            if($ProductVariation instanceof ProductVariation)
            {
                $ProductVariation->setQuantity($ProductStocksTotal);
                $this->entityManager->flush();
                $this->cacheClear();

                return;
            }
        }


        /**
         * Количественный учет торгового предложения
         */

        if($CurrentProductIdentifierResult->getOffer() instanceof ProductOfferUid)
        {
            $ProductOffer = $this->entityManager
                ->getRepository(ProductOffer::class)
                ->find($CurrentProductIdentifierResult->getOffer());

            if($ProductOffer instanceof ProductOffer)
            {
                $ProductOffer->setQuantity($ProductStocksTotal);
                $this->entityManager->flush();
                $this->cacheClear();

                return;
            }
        }


        /**
         * Обновляем стоимость продукции
         */

        $ProductPrice = $this->entityManager
            ->getRepository(ProductPrice::class)
            ->find($CurrentProductIdentifierResult->getEvent());

        if($ProductPrice instanceof ProductPrice)
        {
            $ProductPrice->setQuantity($ProductStocksTotal);
            $this->entityManager->flush();
            $this->cacheClear();
        }

    }

    public function cacheClear(): void
    {
        /* Чистим кеш модуля продукции */
        $cache = $this->cache->init('products-product');
        $cache->clear();

    }
}
