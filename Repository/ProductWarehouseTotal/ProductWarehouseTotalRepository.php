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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseTotal;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final readonly class ProductWarehouseTotalRepository implements ProductWarehouseTotalInterface
{
    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Метод возвращает доступное количество данной продукции на указанном складе
     */
    public function getProductProfileTotal(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer = null,
        ProductVariationConst|false|null $variation = null,
        ProductModificationConst|false|null $modification = null
    ): int
    {

        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('(SUM(stock.total) - SUM(stock.reserve))');

        $qb->from(ProductStockTotal::class, 'stock');

        $qb
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        $qb
            ->andWhere('stock.product = :product')
            ->setParameter('product', $product, ProductUid::TYPE);

        if(true === ($offer instanceof ProductOfferConst))
        {
            $qb
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.offer IS NULL');
        }

        if(true === ($variation instanceof ProductVariationConst))
        {
            $qb
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.variation IS NULL');
        }

        if(true === ($modification instanceof ProductModificationConst))
        {
            $qb
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.modification IS NULL');
        }

        return $qb->fetchOne() ?: 0;
    }

    /**
     * Метод возвращает весь резерв данной продукции на указанном складе
     */
    public function getProductProfileReserve(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer = null,
        ProductVariationConst|false|null $variation = null,
        ProductModificationConst|false|null $modification = null
    ): int
    {

        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('SUM(stock.reserve)');

        $qb->from(ProductStockTotal::class, 'stock');

        $qb
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        $qb
            ->andWhere('stock.product = :product')
            ->setParameter('product', $product, ProductUid::TYPE);

        if(true === ($offer instanceof ProductOfferConst))
        {
            $qb
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.offer IS NULL');
        }

        if(true === ($variation instanceof ProductVariationConst))
        {
            $qb
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.variation IS NULL');
        }

        if(true === ($modification instanceof ProductModificationConst))
        {
            $qb
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.modification IS NULL');
        }

        return $qb->fetchOne() ?: 0;
    }

    /**
     * Метод возвращает количество данной продукции на указанном складе без резерва
     */
    public function getProductProfileTotalNotReserve(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer = null,
        ProductVariationConst|false|null $variation = null,
        ProductModificationConst|false|null $modification = null
    ): int
    {

        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('SUM(stock.total)');

        $qb->from(ProductStockTotal::class, 'stock');

        $qb
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        $qb
            ->andWhere('stock.product = :product')
            ->setParameter('product', $product, ProductUid::TYPE);

        if(true === ($offer instanceof ProductOfferConst))
        {
            $qb
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.offer IS NULL');
        }

        if(true === ($variation instanceof ProductVariationConst))
        {
            $qb
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.variation IS NULL');
        }

        if(true === ($modification instanceof ProductModificationConst))
        {
            $qb
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.modification IS NULL');
        }

        return $qb->fetchOne() ?: 0;
    }
}
