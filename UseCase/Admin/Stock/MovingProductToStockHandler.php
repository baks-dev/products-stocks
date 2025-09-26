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

namespace BaksDev\Products\Stocks\UseCase\Admin\Stock;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;

final class MovingProductToStockHandler extends AbstractHandler
{
    /** @see ProductStock */
    public function handle(MovingProductToStockDTO $command): ProductStockTotal|string
    {
        $this->setCommand($command);

        $command->setTotal($command->getTotal() - $command->getTotalToMove());

        $this->prePersistOrUpdate(ProductStockTotal::class, ['id' => $command->getFromId()]);

        $this->flush();


        if(false === ($command->getToId() instanceof ProductStockTotalUid))
        {
            /* Создаем новое место складирования на указанный профиль и пользователя  */
            $ProductStockTotal = new ProductStockTotal(
                $command->getUsr(),
                $command->getProfile(),
                $command->getProduct(),
                $command->getOffer(),
                $command->getVariation(),
                $command->getModification(),
                'new',
            )
                ->setTotal($command->getTotalToMove())
                ->setComment('Перемещено с другого места складирования');

            $this->persist($ProductStockTotal);

            /** Валидация всех объектов */
            if($this->validatorCollection->isInvalid())
            {
                return $this->validatorCollection->getErrorUniqid();
            }

            $this->flush();

            $this->messageDispatch->addClearCacheOther('products-stocks');

            return $this->main;
        }


        /** @var ProductStockTotal $storageToMove */
        $storageToMoveDTO = new MovingProductToStockDTO();
        $storageToMove = $this->getRepository(ProductStockTotal::class)->find($command->getToId());
        (false === $storageToMove instanceof ProductStockTotal) ?: $storageToMove->getDto($storageToMoveDTO);

        $storageToMoveDTO
            ->setTotal($command->getTotalToMove() + $storageToMove->getTotal());

        $this->setCommand($storageToMoveDTO);

        $this->prePersistOrUpdate(ProductStockTotal::class, ['id' => $command->getToId()]);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        $this->messageDispatch->addClearCacheOther('products-stocks');


        /**
         * Удаляем место складирования, если остаток и резерв равен нулю
         *
         * @var ProductStockTotal $isRemoveProductStockTotal
         */

        $isRemoveProductStockTotal = $this
            ->getRepository(ProductStockTotal::class)
            ->find($command->getFromId());

        if(empty($isRemoveProductStockTotal->getTotal()) && empty($isRemoveProductStockTotal->getReserve()))
        {
            $this->remove($isRemoveProductStockTotal);
            $this->flush();
        }

        return $this->main;
    }
}