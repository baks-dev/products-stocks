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
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;

final class ProductWarehouseTotalRepository implements ProductWarehouseTotalInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(DBALQueryBuilder $DBALQueryBuilder)
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }





    /**
     * Метод возвращает количество данной продукции на указанном складе
     */
    public function getProductProfileTotal(
        UserProfileUid $profile,
        ProductUid $product,
        ?ProductOfferConst $offer = null,
        ?ProductVariationConst $variation = null,
        ?ProductModificationConst $modification = null
    ) : int
    {



        $qb =$this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('(SUM(stock.total) - SUM(stock.reserve))');

        $qb->from(ProductStockTotal::class, 'stock');

        $qb->andWhere('stock.profile = :profile');
        $qb->setParameter('profile', $profile, UserProfileUid::TYPE);

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
    }
}
