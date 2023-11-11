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

namespace BaksDev\Products\Stocks\UseCase\Admin\Extradition\Tests;

use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Tests\PackageProductStockTest;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-extradition
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Package\Tests\PackageProductStockTest::class
 * @see     PackageProductStockTest
 */
#[When(env: 'test')]
final class ExtraditionProductStockTest extends KernelTestCase
{
    /**
     * Тест упаковки заказа
     */
    public function testProductStockDTO(): void
    {
        /** @var ProductStockStatusCollection $ProductStockStatusCollection */

        $ProductStockStatusCollection = self::getContainer()->get(ProductStockStatusCollection::class);
        $ProductStockStatusCollection->cases();

        /** @var CurrentProductStocksInterface $CurrentProductStocksInterface */
        $CurrentProductStocksInterface = self::getContainer()->get(CurrentProductStocksInterface::class);
        $ProductStockEvent = $CurrentProductStocksInterface->getCurrentEvent(new ProductStockUid());


        /** @var ExtraditionProductStockDTO $ExtraditionProductStockDTO */
        $ExtraditionProductStockDTO = $ProductStockEvent->getDto(ExtraditionProductStockDTO::class);
        self::assertEquals(UserProfileUid::TEST, $ExtraditionProductStockDTO->getProfile());
        self::assertTrue($ExtraditionProductStockDTO->getStatus()->equals(ProductStockStatusExtradition::class));
        self::assertEquals('PackageComment', $ExtraditionProductStockDTO->getComment());
        $ExtraditionProductStockDTO->setComment('ExtraditionComment');



        /** @var ExtraditionProductStockHandler $ExtraditionProductStockHandler */
        $ExtraditionProductStockHandler = self::getContainer()->get(ExtraditionProductStockHandler::class);
        $handle = $ExtraditionProductStockHandler->handle($ExtraditionProductStockDTO);

        self::assertTrue(($handle instanceof ProductStock), $handle.': Ошибка ProductStock');

    }
}
