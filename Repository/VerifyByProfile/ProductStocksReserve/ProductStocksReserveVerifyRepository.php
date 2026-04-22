<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksReserve;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class ProductStocksReserveVerifyRepository implements ProductStocksReserveVerifyInterface
{
    private UserProfileUid|false $profile = false;

    private ProductUid $product;

    private ProductOfferConst|false $offerConst = false;

    private ProductVariationConst|false $variationConst = false;

    private ProductModificationConst|false $modificationConst = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfileUid|UserProfile|false|null $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function forProduct(ProductUid $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function forOfferConst(ProductOfferConst|null|false $offerConst): self
    {
        if(empty($offerConst))
        {
            $this->offerConst = false;
            return $this;
        }

        $this->offerConst = $offerConst;

        return $this;
    }

    public function forVariationConst(ProductVariationConst|null|false $variationConst): self
    {
        if(empty($variationConst))
        {
            $this->variationConst = false;
            return $this;
        }

        $this->variationConst = $variationConst;

        return $this;
    }

    public function forModificationConst(ProductModificationConst|null|false $modificationConst): self
    {
        if(empty($modificationConst))
        {
            $this->modificationConst = false;
            return $this;
        }

        $this->modificationConst = $modificationConst;

        return $this;
    }


    /**
     * Все резерв продукции на складе
     */
    public function find(): int
    {
        if(false === ($this->profile instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfileUid');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('SUM(stock_product.reserve) AS reserve')
            ->from(ProductStockTotal::class, 'stock_product');

        $dbal
            ->where('stock_product.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        $dbal
            ->andWhere('stock_product.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );


        if($this->offerConst instanceof ProductOfferConst)
        {
            $dbal
                ->andWhere('stock_product.offer = :offer_const')
                ->setParameter(
                    key: 'offer_const',
                    value: $this->offerConst,
                    type: ProductOfferConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('stock_product.offer IS NULL');
        }


        if($this->variationConst instanceof ProductVariationConst)
        {
            $dbal
                ->andWhere('stock_product.variation = :variation_const')
                ->setParameter(
                    key: 'variation_const',
                    value: $this->variationConst,
                    type: ProductVariationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('stock_product.variation IS NULL');
        }


        if($this->modificationConst instanceof ProductModificationConst)
        {
            $dbal
                ->andWhere('stock_product.modification = :modification_const')
                ->setParameter(
                    key: 'modification_const',
                    value: $this->modificationConst,
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('stock_product.modification IS NULL');
        }

        return $dbal->fetchOne() ?: 0;
    }
}