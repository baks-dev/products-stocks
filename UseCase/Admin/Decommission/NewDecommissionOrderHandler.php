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

namespace BaksDev\Products\Stocks\UseCase\Admin\Decommission;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Products\NewDecommissionOrderProductDTO;

final class NewDecommissionOrderHandler extends AbstractHandler
{
    public function handle(NewDecommissionOrderDTO $command): string|Order
    {
        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(Order::class, OrderEvent::class);

        /**
         * Списываем остатки со склада
         */

        /** @var ProductStockTotal $ProductStockTotal */
        $ProductStockTotal = $this
            ->getRepository(ProductStockTotal::class)
            ->find($command->getStorage());

        if(false === ($ProductStockTotal instanceof ProductStockTotal))
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        /** Списание всегда только с одного места одного продукта */
        if($command->getProduct()->count() > 1)
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        /** @var NewDecommissionOrderProductDTO $NewDecommissionOrderProductDTO */
        $NewDecommissionOrderProductDTO = $command->getProduct()->current();
        $totalDecommission = $NewDecommissionOrderProductDTO->getPrice()->getTotal();
        $ProductStockTotal->subTotal($totalDecommission);

        /**
         * Создаем заказ со статусом Decommission «Списание»
         */


        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch
            ->addClearCacheOther('products-stocks')
            ->addClearCacheOther('products-product')
            ->dispatch(
                message: new OrderMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
                transport: 'orders-order',
            );

        return $this->main;
    }
}