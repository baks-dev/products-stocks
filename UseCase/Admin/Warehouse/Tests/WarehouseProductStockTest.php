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

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Tests;

use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Tests\PurchaseProductStockTest;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-warehouse
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Purchase\Tests\PurchaseProductStockTest::class
 * @see     PurchaseProductStockTest
 */
#[When(env: 'test')]
final class WarehouseProductStockTest extends KernelTestCase
{
    /**
     * Тест нового закупочного листа
     */
    public function testUseCase(): void
    {

        /** @var ProductStockStatusCollection $ProductStockStatusCollection */

        $ProductStockStatusCollection = self::getContainer()->get(ProductStockStatusCollection::class);
        $ProductStockStatusCollection->cases();


        /** @var CurrentProductStocksInterface $CurrentProductStocksInterface */
        $CurrentProductStocksInterface = self::getContainer()->get(CurrentProductStocksInterface::class);
        $ProductStockEvent = $CurrentProductStocksInterface->getCurrentEvent(new ProductStockUid());

        /** @var WarehouseProductStockDTO $WarehouseProductStockDTO */
        $WarehouseProductStockDTO = new WarehouseProductStockDTO(new UserUid());
        $ProductStockEvent->getDto($WarehouseProductStockDTO);

        self::assertNotEquals(new UserProfileUid(), $WarehouseProductStockDTO->getProfile());
        $WarehouseProductStockDTO->setProfile(new UserProfileUid());

        self::assertEquals('Comment', $WarehouseProductStockDTO->getComment());
        $WarehouseProductStockDTO->setComment('WarehouseComment');

        self::assertInstanceOf(ProductStockStatus::class, $WarehouseProductStockDTO->getStatus());
        self::assertTrue($WarehouseProductStockDTO->getStatus()->equals(ProductStockStatus\ProductStockStatusWarehouse::class));

        /** @var WarehouseProductStockHandler $WarehouseProductStockHandler */
        $WarehouseProductStockHandler = self::getContainer()->get(WarehouseProductStockHandler::class);
        $handle = $WarehouseProductStockHandler->handle($WarehouseProductStockDTO);

        self::assertTrue(($handle instanceof ProductStock), $handle.': Ошибка ProductStock');

    }
}