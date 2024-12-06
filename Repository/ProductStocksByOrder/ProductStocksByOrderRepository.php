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

namespace BaksDev\Products\Stocks\Repository\ProductStocksByOrder;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;

final class ProductStocksByOrderRepository implements ProductStocksByOrderInterface
{
    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    /**
     * Метод получает все заявки (может быть упаковка либо перемещение) по идентификатору заказа
     */
    public function findByOrder(Order|OrderUid|string $order): ?array
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('event');

        $orm->from(ProductStockOrder::class, 'ord');

        $orm->join(
            ProductStock::class,
            'stock',
            'WITH',
            'stock.event = ord.event'
        );

        $orm->join(
            ProductStockEvent::class,
            'event',
            'WITH',
            'event.id = stock.event'
        );

        $orm->where('ord.ord = :ord');

        $orm->setParameter('ord', $order, OrderUid::TYPE);

        return $orm->getResult();
    }
}
