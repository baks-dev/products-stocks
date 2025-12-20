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

namespace BaksDev\Products\Stocks\UseCase\Admin\Part;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Stocks\Entity\Stock\Event\Part\ProductStockPart;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;

final class ProductStockPartHandler extends AbstractHandler
{
    /** @see ProductStockPart */
    public function handle(ProductStockPartDTO $command): string|ProductStockPart
    {
        $this->setCommand($command);

        $ProductStockEvent = $this
            ->getRepository(ProductStockEvent::class)
            ->find($command->getProductStockEventUid());


        /** Партию возможно применить только один раз */
        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        if(true === $ProductStockEvent->isProductStockPart())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $ProductStockProductPart = new ProductStockPart($ProductStockEvent);
        $ProductStockProductPart->setEntity($command);
        $this->persist($ProductStockProductPart);


        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch
            ->addClearCacheOther('products-stocks');

        return $ProductStockProductPart;
    }
}