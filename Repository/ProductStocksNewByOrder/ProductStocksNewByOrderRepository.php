<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\ProductStocksNewByOrder;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;

final class ProductStocksNewByOrderRepository implements ProductStocksNewByOrderInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Метод получает заявку на упаковку заказа
     */
    public function getProductStocksEventByOrderAndWarehouse(
        OrderUid $order,
        UserProfileUid $profile
    ): ?ProductStockEvent
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('event');

        $qb->from(ProductStockOrder::class, 'orders');

        $qb->join(
            ProductStock::class,
            'stock',
            'WITH',
            'stock.event = orders.event'
        );

        $qb->join(
            ProductStockEvent::class,
            'event',
            'WITH',
            'event.id = stock.event AND event.profile = :profile AND event.status = :status '
        );

        $qb->where('orders.ord = :ord');

        $qb->setParameter('ord', $order, OrderUid::TYPE);
        $qb->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $qb->setParameter('profile', $profile, UserProfileUid::TYPE);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
