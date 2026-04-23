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

namespace BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksMove;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;


final class ProductStocksMoveVerifyRepository implements ProductStocksMoveVerifyInterface
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

    private function builder(): DBALQueryBuilder
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class);

        $dbal->from(ProductStockEvent::class, 'event');

        $dbal
            ->join(
                'event',
                ProductStock::class,
                'stock',
                'stock.event = event.id',

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

        $dbal->addSelect('SUM(stock_product.total) AS total');

        return $dbal;

    }

    /** Метод возвращает количество продукции в резерве на перемещение */
    public function reserve(): int
    {
        $dbal = $this->builder();

        $dbal
            // номер ордера
            //->addSelect('product_stock_invariable.number')
            ->join(
                'event',
                ProductStocksInvariable::class,
                'product_stock_invariable',
                '
                    product_stock_invariable.event = event.id 
                    AND product_stock_invariable.profile = :profile
                ',
            )
            ->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE,
            );

        $dbal
            ->where('event.status = :moving')
            ->setParameter(
                'moving',
                ProductStockStatusMoving::class,
                ProductStockStatus::TYPE,
            );

        return $dbal->fetchOne() ?: 0;
    }

    /** Метод возвращает количество продукции отправленной на другой склад */
    public function move(): int
    {
        $dbal = $this->builder();

        $dbal
            //->addSelect('move.destination AS move_destination')
            ->join(
                'event',
                ProductStockMove::class,
                'move',
                '
                    move.event = event.id 
                    AND move.destination = :profile
                ',
            )->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE,
            );

        $dbal
            ->where('(event.status = :incoming OR event.status = :warehouse) ')
            ->setParameter(
                'incoming',
                ProductStockStatusIncoming::class,
                ProductStockStatus::TYPE,
            )
            ->setParameter(
                'warehouse',
                ProductStockStatusWarehouse::class,
                ProductStockStatus::TYPE,
            );

        return $dbal->fetchOne() ?: 0;
    }

}