<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Extradition;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Products\Stocks\Entity;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Lock\ProductStockLock;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class ExtraditionProductStockHandler extends AbstractHandler
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,

        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload
    )
    {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);
    }

    public function handle(ExtraditionProductStockDTO $command): string|ProductStock
    {
        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(ProductStock::class, ProductStockEvent::class);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        if(false === $this->isPersist())
        {
            $this->flush();

            if($this->event instanceof ProductStockEvent)
            {
                $this->logger->info(
                    message: sprintf('%s: складская заявка => %s обновили статус на %s',
                        $this->event->getNumber(),
                        ($this->event->getLock() instanceof ProductStockLock) ?
                            ($this->event->getLock()->isLock() ? 'ЗАБЛОКИРОВАЛИ и' : 'НЕ БЛОКИРУЯ') : 'без блокировок',
                        $this->event->getStatus()->getProductStockStatusValue(),

                    ),
                    context: [
                        self::class,
                        (string) $this->main, (string) $this->event],
                );
            }
        }

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new ProductStockMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'products-stocks',
        );

        return $this->main;
    }
}
