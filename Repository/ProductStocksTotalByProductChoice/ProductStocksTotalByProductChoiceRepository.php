<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotalByProductChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductStocksTotalByProductChoiceRepository implements ProductStocksTotalByProductChoiceInterface
{
    private ?UserProfileUid $profile = null;
    private ?ProductUid $product = null;
    private ?ProductOfferConst $offer = null;
    private ?ProductVariationConst $variation = null;
    private ?ProductModificationConst $modification = null;
    private ?ProductStockTotalUid $skipId = null;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function profile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function product(ProductUid $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function offer(?ProductOfferConst $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    public function variation(?ProductVariationConst $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    public function modification(?ProductModificationConst $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    public function skipId(?ProductStockTotalUid $productStockTotal): self
    {
        $this->skipId = $productStockTotal;
        return $this;
    }

    public function fetchStocksByProduct(): Generator
    {
        if(true === empty($this->product) || true === empty($this->profile))
        {
            throw new InvalidArgumentException('Необходимо передать все параметры');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('product_stock_total.id AS value')
            ->addSelect('product_stock_total.storage AS attr')
            ->addSelect('product_stock_total.total AS option')
            ->addSelect('product_stock_total.comment AS property')
            ->from(ProductStockTotal::class, 'product_stock_total')
            ->where('product_stock_total.id != :skip')
            ->andWhere('product_stock_total.product = :product')
            ->andWhere('product_stock_total.offer = :offer')
            ->andWhere('product_stock_total.variation = :variation')
            ->andWhere('product_stock_total.modification = :modification')
            ->andWhere('product_stock_total.profile = :profile')
            ->setParameter('product', $this->product, ProductUid::TYPE)
            ->setParameter('offer', $this->offer, ProductOfferConst::TYPE)
            ->setParameter('variation', $this->variation, ProductVariationConst::TYPE)
            ->setParameter('modification', $this->modification, ProductModificationConst::TYPE)
            ->setParameter('profile', $this->profile, UserProfileUid::TYPE)
            ->setParameter('skip', $this->skipId, ProductStockTotalUid::TYPE);

        return $dbal->fetchAllHydrate(ProductStockTotalUid::class);
    }
}