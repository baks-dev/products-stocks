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

namespace BaksDev\Products\Stocks\Repository\ProductStockMinQuantity;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class ProductStockQuantityRepository implements ProductStockQuantityInterface
{
    private UserProfileUid|false $profile = false;

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(
        private readonly ORMQueryBuilder $ORMQueryBuilder
    ) {}


    public function profile(UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function product(ProductUid|string $product): self
    {
        if(empty($product))
        {
            $this->product = false;
            return $this;
        }

        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function offerConst(ProductOfferConst|string|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variationConst(ProductVariationConst|string|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modificationConst(ProductModificationConst|string|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }


    private function builder(): ORMQueryBuilder
    {
        if(false === ($this->profile instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Invalid Argument Product');
        }


        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('stock')
            ->from(ProductStockTotal::class, 'stock');

        $orm
            ->andWhere('stock.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        $orm
            ->andWhere('stock.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );


        if($this->offer)
        {
            $orm
                ->andWhere('stock.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('stock.offer IS NULL');
        }

        if($this->variation)
        {
            $orm
                ->andWhere('stock.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('stock.variation IS NULL');
        }

        if($this->modification)
        {
            $orm
                ->andWhere('stock.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('stock.modification IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm;

    }

    /**
     * Метод возвращает место складирования продукции с минимальным количеством в наличии без учета резерва
     */
    public function findOneByTotalMin(): ?ProductStockTotal
    {

        $orm = $this->builder();

        $orm->orderBy('stock.total');

        /* складские места только с наличием */
        $orm->andWhere('stock.total > 0');

        /* складские места только с резервом */
        $orm->andWhere('stock.reserve > 0');

        return $orm->getOneOrNullResult();
    }

    /**
     * Метод возвращает место складирования продукции с максимальным количеством в наличии без учета резерва
     */
    public function findOneByTotalMax(): ?ProductStockTotal
    {

        $orm = $this->builder();

        $orm->orderBy('stock.total', 'DESC');

        /* складские места только с наличием */
        $orm->andWhere('stock.total > 0');

        return $orm->getOneOrNullResult();
    }


    /**
     * Метод возвращает место складирования продукции с максимальным количеством в наличии и резервом > 0
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
     * Метод возвращает место складирования продукции с минимальным количеством в наличии с учетом резерва
     */
    public function findOneBySubReserve(): ?ProductStockTotal
    {
        $orm = $this->builder();

        $orm->orderBy('stock.priority', 'DESC'); // сортируем по приоритету
        $orm->addOrderBy('stock.total'); // сортируем по количеству

        /* складские места только с наличием учитывая резерв */
        $orm->andWhere('(stock.total - stock.reserve) > 0');

        return $orm->getOneOrNullResult();
    }


}
