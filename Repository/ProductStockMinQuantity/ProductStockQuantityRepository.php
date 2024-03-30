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

namespace BaksDev\Products\Stocks\Repository\ProductStockMinQuantity;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class ProductStockQuantityRepository implements ProductStockQuantityInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    private ?UserProfileUid $profile = null;

    private ?ProductUid $product = null;

    private ?ProductOfferConst $offer = null;

    private ?ProductVariationConst $variation = null;

    private ?ProductModificationConst $modification = null;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }


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

    public function offerConst(?ProductOfferConst $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    public function variationConst(?ProductVariationConst $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    public function modificationConst(?ProductModificationConst $modification): self
    {
        $this->modification = $modification;
        return $this;
    }


    private function builder(): ORMQueryBuilder
    {

        if(!$this->profile)
        {
            throw new InvalidArgumentException('profile not found : ->profile(UserProfileUid $profile) ');
        }

        if(!$this->product)
        {
            throw new InvalidArgumentException('product not found : ->product(ProductUid $product) ');
        }


        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('stock');

        $orm->from(ProductStockTotal::class, 'stock');

        $orm
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $this->profile, UserProfileUid::TYPE);

        $orm
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);


        if($this->offer)
        {
            $orm
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.offer IS NULL');
        }

        if($this->variation)
        {
            $orm
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.variation IS NULL');
        }

        if($this->modification)
        {
            $orm
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $this->modification, ProductModificationConst::TYPE);
        }
        else
        {
            $orm->andWhere('stock.modification IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm;

    }

    /**
     * Метод возвращает место складирования продукции с минимальным количеством в наличие без учета резерва
     */
    public function findOneByTotalMin(): ?ProductStockTotal
    {

        $orm = $this->builder();

        $orm->orderBy('stock.total');

        /* складские места только с наличием */
        $orm->andWhere('stock.total > 0');

        return $orm->getOneOrNullResult();
    }


    /**
     * Метод возвращает место складирования продукции с минимальным количеством в наличие без учета резерва
     */
    public function findOneByReserveMax(): ?ProductStockTotal
    {
        $orm = $this->builder();

        $orm->orderBy('stock.total', 'DESC');

        /* складские места только с резервом */
        $orm->andWhere('stock.reserve > 0');

        return $orm->getOneOrNullResult();
    }


    /**
     * Метод возвращает место складирования продукции с минимальным количеством в наличие с учетом резерва
     */
    public function findOneBySubReserve(): ?ProductStockTotal
    {

        $orm = $this->builder();

        $orm->orderBy('stock.total');

        /* складские места только с наличием учитывая резерв */
        $orm->andWhere('(stock.total - stock.reserve) > 0');

        return $orm->getOneOrNullResult();
    }



}