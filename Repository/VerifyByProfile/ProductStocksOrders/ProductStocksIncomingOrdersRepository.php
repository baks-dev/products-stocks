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

namespace BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksOrders;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusDecommission;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;


final class ProductStocksIncomingOrdersRepository implements ProductStocksIncomingOrdersInterface
{
    private UserProfileUid $profile;

    private ProductUid $product;

    private ProductOfferConst|false $offerConst = false;

    private ProductVariationConst|false $variationConst = false;

    private ProductModificationConst|false $modificationConst = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfileUid $profile): self
    {
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

    /** Получаем все ПРИХОДЫ на продукцию по профилю */
    public function find(): int
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class);

        $dbal
            ->from(ProductStockEvent::class, 'event')
            ->andWhere('(event.status = :completed OR event.status = :decommission)')
            ->setParameter(
                'completed',
                ProductStockStatusCompleted::class,
                ProductStockStatus::TYPE,
            )
            ->setParameter(
                'decommission',
                ProductStockStatusDecommission::class,
                ProductStockStatus::TYPE,
            );


        $dbal->join(
            'event',
            ProductStocksInvariable::class,
            'product_stock_invariable',
            'product_stock_invariable.event = event.id 
                    AND product_stock_invariable.profile = :profile',
        )
            ->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE,
            );


        $dbal
            ->join(
                'event',
                ProductStockProduct::class,
                'stock_product',
                '
                            stock_product.event = event.id
                            AND stock_product.product = :product
                        '
                .($this->offerConst ? ' AND stock_product.offer = :offer ' : ' AND stock_product.offer IS NULL ')
                .($this->variationConst ? ' AND stock_product.variation = :variation ' : ' AND stock_product.variation IS NULL ')
                .($this->modificationConst ? ' AND stock_product.modification = :modification ' : ' AND stock_product.modification IS NULL '),
            )
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );


        if($this->offerConst instanceof ProductOfferConst)
        {
            $dbal->setParameter(
                key: 'offer',
                value: $this->offerConst,
                type: ProductOfferConst::TYPE,
            );
        }

        if($this->offerConst instanceof ProductOfferConst)
        {
            $dbal->setParameter(
                key: 'variation',
                value: $this->variationConst,
                type: ProductVariationConst::TYPE,
            );
        }

        if($this->offerConst instanceof ProductOfferConst)
        {
            $dbal->setParameter(
                key: 'modification',
                value: $this->modificationConst,
                type: ProductModificationConst::TYPE,
            );
        }

        /** Расход */
        $dbal->addSelect('SUM(stock_product.total) AS total');

        return $dbal->fetchOne() ?: 0;
    }
}