<?php

/*
 *  Copyright 2023-2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Tests;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
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
        /** TODO: Временно блокируем изменение прихода */
        //$ProductStockDTO->setTotal(200);


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

        self::assertNotNull($ProductStockTotal);

        /** Общий остаток 200 */
        /** TODO: Временно блокируем изменение прихода */
        //self::assertEquals(200, $ProductStockTotal->getTotal());
        self::assertEquals(100, $ProductStockTotal->getTotal());


        self::assertEquals(0, $ProductStockTotal->getReserve());

        $em->clear();
        //$em->close();

    }
}
