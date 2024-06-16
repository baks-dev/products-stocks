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

namespace BaksDev\Products\Stocks\UseCase\Admin\Edit;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\Products\Recalculate\RecalculateProductMessage;
use Doctrine\ORM\EntityManagerInterface;

final class ProductStockTotalEditHandler
{

    private EntityManagerInterface $entityManager;
    private ValidatorCollectionInterface $validatorCollection;
    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorCollectionInterface $validatorCollection,
        MessageDispatchInterface $messageDispatch
    )
    {
        $this->entityManager = $entityManager;
        $this->validatorCollection = $validatorCollection;
        $this->messageDispatch = $messageDispatch;
    }

    /** @see ProductStock */
    public function handle(
        ProductStockTotalEditDTO $command
    ): string|ProductStockTotal
    {
        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        $ProductStockTotal = $this->entityManager->getRepository(ProductStockTotal::class)->find($command->getId());

        if(
            !$ProductStockTotal || false === $this->validatorCollection->add($ProductStockTotal, context: [
                __FILE__.':'.__LINE__,
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

        $this->entityManager->flush();

        /** Отправляем сообщение в шину для пересчета продукции */
        $this->messageDispatch->dispatch(new RecalculateProductMessage(
            $command->getProduct(),
            $command->getOffer(),
            $command->getVariation(),
            $command->getModification(),
        ), transport: 'products-stocks');

        return $ProductStockTotal;
    }
}