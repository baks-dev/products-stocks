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

namespace BaksDev\Products\Stocks\UseCase\Admin\EditTotal;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\Products\Recalculate\RecalculateProductMessage;
use BaksDev\Products\Stocks\UseCase\Admin\Storage\ProductStockStorageEditDTO;

final class ProductStockTotalEditHandler extends AbstractHandler
{
    /** @see ProductStock */
    public function handle(ProductStockTotalEditDTO|ProductStockStorageEditDTO $command): string|ProductStockTotal
    {
        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        /** @var ProductStockTotal $ProductStockTotal */
        $ProductStockTotal = $this
            ->getRepository(ProductStockTotal::class)
            ->find($command->getId());

        if(false === ($ProductStockTotal instanceof ProductStockTotal))
        {
            return $this->validatorCollection->getErrorUniqid();
        }


        if(
            false === $this->validatorCollection->add($ProductStockTotal, context: [
                self::class.':'.__LINE__,
                'class' => ProductStockTotal::class,
                'id' => $command->getId(),
            ])
        )
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $ProductStockTotal->setEntity($command);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /**
         * Удаляем место складирования, если остаток и резерв равен нулю
         *
         * @var ProductStockTotal $isRemoveProductStockTotal
         */

        $isRemoveProductStockTotal = $this
            ->getRepository(ProductStockTotal::class)
            ->find($command->getId());

        if(empty($isRemoveProductStockTotal->getTotal()) && empty($isRemoveProductStockTotal->getReserve()))
        {
            $this->remove($isRemoveProductStockTotal);
            $this->flush();
        }

        $this->messageDispatch->addClearCacheOther('products-stocks');

        if($command instanceof ProductStockTotalEditDTO && $command->isRecalculate())
        {
            /** Отправляем сообщение в шину для пересчета продукции */

            $RecalculateProductMessage = new RecalculateProductMessage(
                $command->getProduct(),
                $command->getOffer(),
                $command->getVariation(),
                $command->getModification(),
            );

            $this->messageDispatch->dispatch(
                message: $RecalculateProductMessage,
                transport: 'products-stocks',
            );
        }

        return $ProductStockTotal;
    }
}
