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

namespace BaksDev\Products\Stocks\Repository\UpdateProductStock;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

final class SubProductStockRepository implements SubProductStockInterface
{
    private int|false $total = false;

    private int|false $reserve = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /** Указываем количество снятия резерва */
    public function reserve(int|false $reserve): self
    {
        $this->reserve = $reserve ?: null;
        return $this;
    }

    /** Указываем количество снятия остатка */
    public function total(int|false $total): self
    {
        $this->total = $total ?: false;
        return $this;
    }


    /** Метод СНИМАЕТ со складского учета резерв либо остаток */
    public function updateById(ProductStockTotal|ProductStockTotalUid|string $id): int
    {
        if(is_string($id))
        {
            $id = new ProductStockTotalUid($id);
        }

        if($id instanceof ProductStockTotal)
        {
            $id = $id->getId();
        }

        if(empty($this->total) && empty($this->reserve))
        {
            throw new InvalidArgumentException('Not empty total and reserve');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->update(ProductStockTotal::class)
            ->where('id = :identifier')
            ->setParameter(
                key: 'identifier',
                value: $id,
                type: ProductStockTotalUid::TYPE
            );

        /** Если указан остаток - снимаем */
        if($this->total)
        {
            $dbal
                ->set('total', 'total - :total')
                ->setParameter(
                    key: 'total',
                    value: $this->total,
                    type: ParameterType::INTEGER
                );

            $dbal->andWhere('total != 0');

        }

        /** Если указан резерв - снимаем selection */
        if($this->reserve)
        {
            $dbal
                ->set('reserve', 'reserve - :reserve')
                ->setParameter(
                    key: 'reserve',
                    value: $this->reserve,
                    type: ParameterType::INTEGER
                );

            $dbal->andWhere('reserve != 0');
        }

        $rows = (int) $dbal->executeStatement();

        /**
         * Удаляем пустое место со склада
         */
        if($rows)
        {
            $this->delete($id);
        }

        return $rows;
    }

    /**
     * Метод удалят складское место при условии, что остаток и резерв равен нулю
     */
    private function delete(ProductStockTotalUid $id): void
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->delete(ProductStockTotal::class)
            ->where('id = :identifier')
            ->andWhere('total = 0')
            ->andWhere('reserve = 0')
            ->setParameter(
                key: 'identifier',
                value: $id,
                type: ProductStockTotalUid::TYPE
            )
            ->executeStatement();
    }
}
