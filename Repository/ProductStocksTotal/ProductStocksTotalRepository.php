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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotal;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class ProductStocksTotalRepository implements ProductStocksTotalInterface
{

    private DBALQueryBuilder $DBALQueryBuilder;
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ORMQueryBuilder $ORMQueryBuilder
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /** Метод возвращает общее количество продукции на всех складах (без учета резерва) */
    public function getProductStocksTotal(
        ProductUid $product,
        ?ProductOfferConst $offer = null,
        ?ProductVariationConst $variation = null,
        ?ProductModificationConst $modification = null
    ): int
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->select('SUM(stock.total)');

        $dbal->from(ProductStockTotal::class, 'stock');


        $dbal->andWhere('stock.product = :product');
        $dbal->setParameter('product', $product, ProductUid::TYPE);

        if($offer)
        {
            $dbal
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.offer IS NULL');
        }

        if($variation)
        {
            $dbal
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.variation IS NULL');
        }

        if($modification)
        {
            $dbal
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }

        return $dbal->fetchOne() ?: 0;
    }

    public function getProductStocksTotalByStorage(
        UserProfileUid $profile,
        ProductUid $product,
        ?ProductOfferConst $offer,
        ?ProductVariationConst $variation,
        ?ProductModificationConst $modification,
        ?string $storage
    ): ?ProductStockTotal
    {

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('stock');

        $orm->from(ProductStockTotal::class, 'stock');

        $orm
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        $orm
            ->andWhere('stock.product = :product')
            ->setParameter('product', $product, ProductUid::TYPE);


        if($storage)
        {
            $storage = trim($storage);
            $storage = mb_strtolower($storage);

            $orm
                ->andWhere('LOWER(stock.storage) = :storage')
                ->setParameter('storage', $storage);

        }
        else
        {
            $orm->andWhere('stock.storage IS NULL');
        }

        if($offer)
        {
            $orm
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.offer IS NULL');
        }

        if($variation)
        {
            $orm
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.variation IS NULL');
        }

        if($modification)
        {
            $orm
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.modification IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm->getOneOrNullResult();
    }
}
