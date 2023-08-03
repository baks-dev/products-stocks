<?php

/*
 * Copyright (c) 2023.  Baks.dev <admin@baks.dev>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Tests;

use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/** @group products-stocks */
#[When(env: 'test')]
final class IncomingProductStockTest extends KernelTestCase
{
    public function testProductStockDTO(): void
    {
        /** DATA */
        $ProductStockEventUid = new ProductStockEventUid();
        $UserProfileUid = new UserProfileUid();
        $status = new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming());
        $comment = 'comment';

        $newDTO = new IncomingProductStockDTO($UserProfileUid);
        $newDTO->setComment($comment);
        $newDTO->setId($ProductStockEventUid);

        /* Проверка заполнения */
        self::assertEquals($newDTO->getProfile(), $UserProfileUid);
        self::assertEquals($newDTO->getStatus(), $status);
        self::assertEquals($newDTO->getComment(), $comment);

        /* Проверка маппинга на сущность и обратно */
        $entity = new ProductStockEvent();
        $entity->setEntity($newDTO);

        $editDTO = new IncomingProductStockDTO($UserProfileUid);
        $entity->getDto($editDTO);

        self::assertTrue($editDTO->getEvent()->equals($entity->getId()));

        self::assertNotEquals($editDTO->getEvent(), $newDTO->getEvent());
        self::assertEquals($editDTO->getComment(), $newDTO->getComment());
        self::assertEquals($editDTO->getProfile(), $newDTO->getProfile());
        self::assertEquals($editDTO->getStatus(), $newDTO->getStatus());

    }
}
