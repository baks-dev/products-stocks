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

namespace BaksDev\Products\Stocks\Repository\ExistProductStocksStatus;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusInterface;

final class ExistProductStocksStatusRepository implements ExistProductStocksStatusInterface
{
    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Метод проверяет, имеется ли другое событие с указанным статусом
     */
    public function isOtherExists(
        ProductStockUid|string $stock,
        ProductStockEventUid|string $event,
        ProductStockStatus|ProductStockStatusInterface|string $status
    ): bool {
        if(is_string($stock))
        {
            $stock = new ProductStockUid($stock);
        }

        if(is_string($event))
        {
            $event = new ProductStockEventUid($event);
        }

        if(is_string($status) || $status instanceof ProductStockStatusInterface)
        {
            $status = new ProductStockStatus($status);
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(ProductStock::class, 'main')
            ->where('main.id = :stock')
            ->setParameter('stock', $stock, ProductStockUid::TYPE);

        $dbal
            ->join(
                'main',
                ProductStockEvent::class,
                'event',
                'event.id != :event AND event.status = :status'
            )
            ->setParameter('event', $event, ProductStockEventUid::TYPE)
            ->setParameter('status', $status, ProductStockStatus::TYPE);

        $dbal->andWhere('main.event = event.id');

        return $dbal->fetchExist();
    }
}
