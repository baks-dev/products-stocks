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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Generator;
use InvalidArgumentException;

final class ProductWarehouseChoiceRepository implements ProductWarehouseChoiceInterface
{
    private ?UserUid $user = null;

    private ?ProductUid $product = null;

    private ?ProductOfferConst $offer = null;

    private ?ProductVariationConst $variation = null;

    private ?ProductModificationConst $modification = null;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function user(UserUid|string $user): self
    {
        if(is_string($user))
        {
            $user = new UserUid($user);
        }

        $this->user = $user;

        return $this;
    }


    public function product(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }


    public function offerConst(ProductOfferConst|string $offer): self
    {
        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variationConst(ProductVariationConst|string $variation): self
    {
        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modificationConst(ProductModificationConst|string $modification): self
    {
        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }


    /**
     * Возвращает список складов (профилей пользователя) на которых имеется данный вид продукта
     */
    public function fetchWarehouseByProduct(): Generator
    {
        if(!$this->user || !$this->product)
        {
            throw new InvalidArgumentException('Необходимо передать все параметры');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        $dbal->from(ProductStockTotal::class, 'stock');

        $dbal
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $this->user, UserUid::TYPE);

        $dbal
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);


        $dbal->andWhere('(stock.total - stock.reserve) > 0');


        if($this->offer)
        {
            $dbal->andWhere('stock.offer = :offer');
            $dbal->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
            $dbal->addGroupBy('stock.offer');
        }
        else
        {
            $dbal->andWhere('stock.offer IS NULL');
        }

        if($this->variation)
        {
            $dbal->andWhere('stock.variation = :variation');
            $dbal->setParameter('variation', $this->variation, ProductVariationConst::TYPE);

            $dbal->addGroupBy('stock.variation');
        }
        else
        {
            $dbal->andWhere('stock.variation IS NULL');
        }

        if($this->modification)
        {
            $dbal->andWhere('stock.modification = :modification');
            $dbal->setParameter('modification', $this->modification, ProductModificationConst::TYPE);

            $dbal->addGroupBy('stock.modification');

        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }

        $dbal->join(
            'stock',
            UserProfile::class,
            'profile',
            'profile.id = stock.profile',
        );

        $dbal->join(
            'profile',
            UserProfilePersonal::class,
            'profile_personal',
            'profile_personal.event = profile.event',
        );

        $dbal->addSelect('stock.profile AS value')->groupBy('stock.profile');
        $dbal->addSelect('profile_personal.username AS attr')->addGroupBy('profile_personal.username');
        $dbal->addSelect('(SUM(stock.total) - SUM(stock.reserve)) AS property');

        return $dbal
            ->fetchAllHydrate(UserProfileUid::class);


    }
}
