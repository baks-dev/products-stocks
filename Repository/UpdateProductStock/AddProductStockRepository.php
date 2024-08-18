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

namespace BaksDev\Products\Stocks\Repository\UpdateProductStock;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

final class AddProductStockRepository implements AddProductStockInterface
{
    private ?int $total = null;

    private ?int $reserve = null;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /** Указываем количество снятия резерва */
    public function reserve(?int $reserve): self
    {
        $this->reserve = $reserve ?: null;
        return $this;
    }

    /** Указываем количество снятия остатка */
    public function total(?int $total): self
    {
        $this->total = $total ?: null;
        return $this;
    }


    /** Метод ДОБАВЛЯЕТ к складскому учету резерв либо остаток */
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
            ->setParameter('identifier', $id, ProductStockTotalUid::TYPE);

        /** Если указан остаток - добавляем */
        if($this->total)
        {
            $dbal
                ->set('total', 'total + :total')
                ->setParameter('total', $this->total, ParameterType::INTEGER);
        }

        /** Если указан резерв - добавляем */
        if($this->reserve)
        {
            $dbal
                ->set('reserve', 'reserve + :reserve')
                ->setParameter('reserve', $this->reserve, ParameterType::INTEGER);

            /** @note !!! Добавить резерв можно только если имеются остатки */
            $dbal->andWhere('(total - reserve) > 0');
        }

        return (int) $dbal->executeStatement();
    }
}
