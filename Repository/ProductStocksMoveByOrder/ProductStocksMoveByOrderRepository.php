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

namespace BaksDev\Products\Stocks\Repository\ProductStocksMoveByOrder;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use Doctrine\ORM\EntityManagerInterface;

final class ProductStocksMoveByOrderRepository implements ProductStocksMoveByOrderInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Метод получает заявку на перемещение заказа на указанный склад
     */
    public function getProductStocksEventByOrderAndWarehouse(
        OrderUid $order,
        ContactsRegionCallConst $warehouse
    ): ?ProductStockEvent
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('event');

        $qb->from(ProductStockMove::class, 'move');

        $qb->join(
            ProductStock::class,
            'stock',
            'WITH',
            'stock.event = move.event'
        );

        $qb->join(
            ProductStockEvent::class,
            'event',
            'WITH',
            'event.id = stock.event AND event.warehouse = :warehouse AND event.status = :status '
        );

        $qb->where('move.ord = :ord');

        $qb->setParameter('ord', $order, OrderUid::TYPE);
        $qb->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);
        $qb->setParameter('warehouse', $warehouse, ContactsRegionCallConst::TYPE);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
