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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class ProductWarehouseByOrderRepository implements ProductWarehouseByOrderInterface
{
    private OrderUid|false $order = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forOrder(Order|OrderUid|string $order): self
    {

        if(empty($order))
        {
            $this->order = false;
            return $this;
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Метод возвращает идентификатор склада (профиля), на который была отправлена заявки для сборки
     */
    public function getWarehouseByOrder(): UserProfileUid|false
    {
        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(ProductStockOrder::class, 'ord')
            ->where('ord.ord = :order')
            ->setParameter(
                key: 'order',
                value: $this->order,
                type: OrderUid::TYPE
            );

        $dbal->join(
            'ord',
            ProductStock::class,
            'stock',
            'stock.event = ord.event'
        );

        $dbal
            ->addSelect('invariable.profile AS value')
            ->join(
                'stock',
                ProductStocksInvariable::class,
                'invariable',
                'invariable.main = stock.id'
            );

        $dbal->setMaxResults(1);

        return $dbal->fetchHydrate(UserProfileUid::class);
    }
}