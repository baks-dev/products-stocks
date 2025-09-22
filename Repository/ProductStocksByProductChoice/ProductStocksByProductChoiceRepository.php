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

namespace BaksDev\Products\Stocks\Repository\ProductStocksByProductChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductStocksByProductChoiceRepository implements ProductStocksByProductChoiceInterface
{
    private ?UserProfileUid $profile = null;
    private ?ProductUid $product = null;

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
            ->select('product_stock_product.storage AS attr')
            ->addSelect('product_stock_product.total AS option')
            ->from(ProductStockProduct::class, 'product_stock_product')
            ->where('product_stock_product.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);

        $dbal
            ->addSelect('product_stock_event.comment AS property')
            ->join(
                'product_stock_product',
                ProductStockEvent::class,
                'product_stock_event',
                'product_stock_event.id = product_stock_product.event'
            );

        $dbal
            ->join(
                'product_stock_event',
                ProductStocksInvariable::class,
                'product_stocks_invariable',
                'product_stocks_invariable.event = product_stock_event.id
                AND product_stocks_invariable.profile = :profile'
            )
            ->setParameter('profile', $this->profile, UserProfileUid::TYPE);

        $dbal
            ->addSelect('product_stock.id AS value')
            ->join(
                'product_stock_event',
                ProductStock::class,
                'product_stock',
                'product_stock.event = product_stock_event.id'
            );

        return $dbal->fetchAllHydrate(ProductStockUid::class);
    }
}