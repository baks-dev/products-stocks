<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package;

use BaksDev\Core\Services\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity as ProductStockEntity;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PackageProductStockHandler
{
    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    private LoggerInterface $logger;

    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->messageDispatch = $messageDispatch;
    }

    public function handle(
        PackageProductStockDTO $command,
        //?UploadedFile $cover = null
    ): string|ProductStockEntity\ProductStock {
        /* Валидация */
        $errors = $this->validator->validate($command);

        if (count($errors) > 0)
        {
            $uniqid = uniqid('', false);
            $errorsString = (string) $errors;
            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }

        if ($command->getEvent())
        {
            $EventRepo = $this->entityManager->getRepository(ProductStockEntity\Event\ProductStockEvent::class)->find(
                $command->getEvent()
            );

            if ($EventRepo === null)
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Not found %s by id: %s',
                    ProductStockEntity\Event\ProductStockEvent::class,
                    $command->getEvent()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }

            $Event = $EventRepo->cloneEntity();
        }
        else
        {
            $Event = new ProductStockEntity\Event\ProductStockEvent();
            $this->entityManager->persist($Event);
        }

        $this->entityManager->clear();

        /* @var ProductStockEntity\ProductStock $Main */
        if ($Event->getMain())
        {
            $Main = $this->entityManager->getRepository(ProductStockEntity\ProductStock::class)->findOneBy(
                ['event' => $command->getEvent()]
            );

            if (empty($Main))
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Not found %s by event: %s',
                    ProductStockEntity\ProductStock::class,
                    $command->getEvent()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }
        }
        else
        {
            $Main = new ProductStockEntity\ProductStock();
            $this->entityManager->persist($Main);
            $Event->setMain($Main);
        }

        $Event->setEntity($command);
        $this->entityManager->persist($Event);

        /** Валидация Event */
        $errors = $this->validator->validate($Event);

        if (count($errors) > 0)
        {
            $uniqid = uniqid('', false);
            $errorsString = (string) $errors;
            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }

        /* присваиваем событие корню */
        $Main->setEvent($Event);

        /** Валидация Main */
        $errors = $this->validator->validate($Main);

        if (count($errors) > 0)
        {
            $uniqid = uniqid('', false);
            $errorsString = (string) $errors;
            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }

        $this->entityManager->flush();

        /* Отправляем событие в шину  */
        $this->messageDispatch->dispatch(
            message: new ProductStockMessage($Main->getId(), $Main->getEvent(), $command->getEvent()),
            transport: 'products-stocks'
        );

        return $Main;
    }
}
