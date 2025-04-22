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

namespace BaksDev\Products\Stocks\Repository\ProductStocksEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use InvalidArgumentException;

final class ProductStocksEventRepository implements ProductStocksEventInterface
{
    private ProductStockEventUid|false $event = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forEvent(ProductStock|ProductStockEvent|ProductStockEventUid|string $event): self
    {
        if(empty($event))
        {
            $this->event = false;
            return $this;
        }

        if($event instanceof ProductStockEvent)
        {
            $event = $event->getId();
        }

        if($event instanceof ProductStock)
        {
            $event = $event->getEvent();
        }

        $this->event = $event;

        return $this;
    }

    /**
     * Метод возвращает объект события складской заявки
     */
    public function find(): ProductStockEvent|false
    {
        if(false === ($this->event instanceof ProductStockEventUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductStockEvent');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('event')
            ->from(ProductStockEvent::class, 'event')
            ->where('event.id = :event')
            ->setParameter(
                key: 'event',
                value: $this->event,
                type: ProductStockEventUid::TYPE
            );

        return $orm
            ->enableCache('products-stocks', '1 day')
            ->getOneOrNullResult() ?: false;
    }
}
