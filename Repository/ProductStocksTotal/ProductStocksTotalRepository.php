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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotal;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Warehouse\UserProfileWarehouse;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use InvalidArgumentException;

final class ProductStocksTotalRepository implements ProductStocksTotalInterface
{
    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    private ProductUid $product;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    private bool $isOnlyLogisticWarehouse = false;

    public function product(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function offer(ProductOfferConst|string|null|false $offer): self
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

    public function variation(ProductVariationConst|string|null|false $variation): self
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

    public function modification(ProductModificationConst|string|null|false $modification): self
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

    /** Только на логистических складах */
    public function onlyLogisticWarehouse(): self
    {
        $this->isOnlyLogisticWarehouse = true;
        return $this;
    }

    /**
     * Метод возвращает общее количество продукции на всех складах (без учета резерва)
     */
    public function get(): int
    {
        if(empty($this->product))
        {
            throw new InvalidArgumentException('Invalid Argument product');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('SUM(stock.total)')
            ->from(ProductStockTotal::class, 'stock')
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);

        if(true === $this->isOnlyLogisticWarehouse)
        {
            $dbal->join(
                'stock',
                UserProfile::class,
                'profile',
                'profile.id = stock.profile',
            );

            $dbal->join(
                'profile',
                UserProfileWarehouse::class,
                'profile_warehouse',
                'profile_warehouse.event = profile.event AND profile_warehouse.value IS TRUE',
            );
        }

        if($this->offer instanceof ProductOfferConst)
        {
            $dbal
                ->andWhere('stock.offer = :offer')
                ->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.offer IS NULL');
        }

        if($this->variation instanceof ProductVariationConst)
        {
            $dbal
                ->andWhere('stock.variation = :variation')
                ->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
        }
        else
        {
            $dbal->andWhere('stock.variation IS NULL');
        }

        if($this->modification instanceof ProductModificationConst)
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
