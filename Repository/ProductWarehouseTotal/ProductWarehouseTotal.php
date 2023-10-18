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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseTotal;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use Doctrine\ORM\EntityManagerInterface;

final class ProductWarehouseTotal implements ProductWarehouseTotalInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /** Метод возвращает количество данной продукции на указанном складе */
    public function getProductWarehouseTotal(
        ContactsRegionCallConst $warehouse,
        ProductUid $product,
        ?ProductOfferConst $offer,
        ?ProductVariationConst $variation,
        ?ProductModificationConst $modification
    ) : int {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();

        $qb->select('(stock.total - stock.reserve)');

        $qb->from(ProductStockTotal::TABLE, 'stock');

        $qb->andWhere('stock.warehouse = :warehouse');
        $qb->setParameter('warehouse', $warehouse, ContactsRegionCallConst::TYPE);

        $qb->andWhere('stock.product = :product');
        $qb->setParameter('product', $product, ProductUid::TYPE);

        if ($offer)
        {
            $qb->andWhere('stock.offer = :offer');
            $qb->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.offer IS NULL');
        }

        if ($variation)
        {
            $qb->andWhere('stock.variation = :variation');
            $qb->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.variation IS NULL');
        }

        if ($modification)
        {
            $qb->andWhere('stock.modification = :modification');
            $qb->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.modification IS NULL');
        }

        return $qb->fetchOne() ?: 0;

        /* Кешируем результат DBAL */

//        $cacheFilesystem = new FilesystemAdapter('products-stocks');
//
//        $config = $this->entityManager->getConnection()->getConfiguration();
//        $config?->setResultCache($cacheFilesystem);
//
//        return $this->entityManager->getConnection()->executeCacheQuery(
//            $qb->getSQL(),
//            $qb->getParameters(),
//            $qb->getParameterTypes(),
//            new QueryCacheProfile(60 * 60)
//        )->fetchOne();

        //return $qb->fetchOne();
    }
}
