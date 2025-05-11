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

namespace BaksDev\Products\Stocks\Repository\ProductStocksInvariable;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use InvalidArgumentException;


final class ProductStocksInvariableRepository implements ProductStocksInvariableInterface
{
    private ProductStockUid|false $stocks = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forProductStocks(ProductStock|ProductStockUid|string $stocks): self
    {
        if(empty($stocks))
        {
            $this->stocks = false;
            return $this;
        }

        if(is_string($stocks))
        {
            $stocks = new ProductStockUid($stocks);
        }

        if($stocks instanceof ProductStock)
        {
            $stocks = $stocks->getId();
        }

        $this->stocks = $stocks;

        return $this;
    }

    /**
     * Метод получает активный Invariable складской заявки
     */
    public function find(): ProductStocksInvariable|false
    {
        if(false === ($this->stocks instanceof ProductStockUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductStock');
        }

        $dbal = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('invariable')
            ->from(ProductStocksInvariable::class, 'invariable')
            ->where('invariable.main = :id')
            ->setParameter(
                key: 'id',
                value: $this->stocks,
                type: ProductStockUid::TYPE
            );

        /** @var ProductStocksInvariable $ProductStocksInvariable */
        $ProductStocksInvariable = $dbal->getOneOrNullResult();

        if(false === ($ProductStocksInvariable instanceof ProductStocksInvariable))
        {
            return false;
        }

        return $ProductStocksInvariable;
    }
}