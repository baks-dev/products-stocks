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

namespace BaksDev\Products\Stocks\Repository\ProductStocksByOrder;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusInterface;
use InvalidArgumentException;

final class ProductStocksByOrderRepository implements ProductStocksByOrderInterface
{
    private OrderUid|false $order = false;

    private ProductStockStatus|false $status = false;

    public function __construct(
        private readonly ORMQueryBuilder $ORMQueryBuilder
    ) {}

    /**
     * Фильтр по заказу
     */
    public function onOrder(Order|OrderUid $order): self
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        $this->order = $order;
        return $this;
    }

    /**
     * Фильтр по статусу
     *
     * @param ProductStockStatusInterface|class-string $status
     */
    public function onStatus(ProductStockStatusInterface|string $status): self
    {
        $this->status = new ProductStockStatus($status);
        return $this;
    }

    /**
     * Метод получает все заявки (может быть упаковка либо перемещение) по идентификатору заказа
     *
     * @return array<int, ProductStockEvent>|false
     */
    public function findAll(): array|false
    {
        $builder = $this->builder();
        return $builder->getResult() ?? false;
    }

    /**
     * @return array<int, ProductStockEvent>|null
     * @deprecated
     * Метод получает все заявки (может быть упаковка либо перемещение) по идентификатору заказа
     *
     */
    public function findByOrder(Order|OrderUid|string $order): array|null
    {
        $this->onOrder($order);
        $builder = $this->builder();

        return $builder->getResult();
    }

    private function builder(): ORMQueryBuilder
    {
        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException(sprintf(
                'Некорректной тип для параметра $this->order: `%s`. Ожидаемый тип %s',
                var_export($this->order, true), OrderUid::class
            ));
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->from(ProductStockOrder::class, 'ord')
            ->where('ord.ord = :ord')
            ->setParameter(
                key: 'ord',
                value: $this->order,
                type: OrderUid::TYPE
            );

        $orm->join(
            ProductStock::class,
            'stock',
            'WITH',
            'stock.event = ord.event'
        );

        /** Статус заявки */
        if($this->status instanceof ProductStockStatus)
        {
            $orm
                ->join(
                    ProductStockEvent::class,
                    'event',
                    'WITH',
                    '
                        event.id = stock.event AND
                        event.status = :status
                        '
                )
                ->setParameter('status', $this->status, ProductStockStatus::TYPE);
        }
        else
        {
            $orm
                ->join(
                    ProductStockEvent::class,
                    'event',
                    'WITH',
                    'event.id = stock.event'
                );
        }

        $orm->select('event');

        return $orm;
    }
}
