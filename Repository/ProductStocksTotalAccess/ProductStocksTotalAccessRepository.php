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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotalAccess;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use InvalidArgumentException;

final class ProductStocksTotalAccessRepository implements ProductStocksTotalAccessInterface
{
    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    private ProductUid $product;

    private ?ProductOfferConst $offer = null;

    private ?ProductVariationConst $variation = null;

    private ?ProductModificationConst $modification = null;


    public function product(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function offer(ProductOfferConst|string|null $offer): self
    {
        if(empty($offer))
        {
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variation(ProductVariationConst|string|null $variation): self
    {
        if(empty($variation))
        {
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modification(ProductModificationConst|string|null $modification): self
    {
        if(empty($modification))
        {
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }

    /**
     * Метод возвращает общее количество ДОСТУПНОЙ продукции на всех складах (за вычетом резерва)
     */
    public function get(): int
    {
        if(empty($this->product))
        {
            throw new InvalidArgumentException('Invalid Argument product');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('(SUM(stock.total) - SUM(stock.reserve))')
            ->from(ProductStockTotal::class, 'stock')
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);

        if($this->offer)
        {
            $dbal
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.offer IS NULL');
        }

        if($this->variation)
        {
            $dbal
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.variation IS NULL');
        }

        if($this->modification)
        {
            $dbal
                ->andWhere('stock.modification = :modification')
                ->setParameter('modification', $this->modification, ProductModificationConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }

        return $dbal->fetchOne() ?: 0;
    }
}
