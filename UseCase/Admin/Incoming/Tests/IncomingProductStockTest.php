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

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Tests\WarehouseProductStockTest;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-incoming
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Tests\WarehouseProductStockTest::class
 * @see     WarehouseProductStockTest
 */
#[When(env: 'test')]
final class IncomingProductStockTest extends KernelTestCase
{
    public function testProductStockDTO(): void
    {
        /** @var ProductStockStatusCollection $ProductStockStatusCollection */

        $ProductStockStatusCollection = self::getContainer()->get(ProductStockStatusCollection::class);
        $ProductStockStatusCollection->cases();

        /** @var CurrentProductStocksInterface $CurrentProductStocksInterface */
        $CurrentProductStocksInterface = self::getContainer()->get(CurrentProductStocksInterface::class);
        $ProductStockEvent = $CurrentProductStocksInterface->getCurrentEvent(new ProductStockUid());


        /** @var IncomingProductStockDTO $IncomingProductStockDTO */
        $IncomingProductStockDTO = $ProductStockEvent->getDto(IncomingProductStockDTO::class);

        self::assertEquals('WarehouseComment', $IncomingProductStockDTO->getComment());
        $IncomingProductStockDTO->setComment('IncomingComment');

        self::assertInstanceOf(ProductStockStatus::class, $IncomingProductStockDTO->getStatus());
        self::assertTrue($IncomingProductStockDTO->getStatus()->equals(ProductStockStatus\ProductStockStatusIncoming::class));

        self::assertCount(1, $IncomingProductStockDTO->getProduct());


        $ProductStockDTO = $IncomingProductStockDTO->getProduct()->current();

        $ProductUid = new ProductUid();
        self::assertTrue($ProductUid->equals($ProductStockDTO->getProduct()));

        $ProductOfferConst = new ProductOfferConst();
        self::assertTrue($ProductOfferConst->equals($ProductStockDTO->getOffer()));

        $ProductVariationConst = new ProductVariationConst();
        self::assertTrue($ProductVariationConst->equals($ProductStockDTO->getVariation()));

        $ProductModificationConst = new ProductModificationConst();
        self::assertTrue($ProductModificationConst->equals($ProductStockDTO->getModification()));

        self::assertEquals(100, $ProductStockDTO->getTotal());
        $ProductStockDTO->setTotal(200);


        /** @var IncomingProductStockHandler $IncomingProductStockHandler */
        $IncomingProductStockHandler = self::getContainer()->get(IncomingProductStockHandler::class);
        $handle = $IncomingProductStockHandler->handle($IncomingProductStockDTO);

        self::assertTrue(($handle instanceof ProductStock), $handle.': Ошибка ProductStock');


        /** @var ProductWarehouseTotalInterface $ProductWarehouseTotal */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        /** @var ProductStockTotal $ProductStockTotal */
        $ProductStockTotal = $em->getRepository(ProductStockTotal::class)->findOneBy(
            [
                'profile' => new UserProfileUid(),
                'product' => $ProductUid,
                'offer' => $ProductOfferConst,
                'variation' => $ProductVariationConst,
                'modification' => $ProductModificationConst,
            ]
        );

        /** Общий остаток 200 */
        self::assertEquals(200, $ProductStockTotal->getTotal());
        self::assertEquals(0, $ProductStockTotal->getReserve());

    }
}
