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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package\Tests;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\Tests\IncomingProductStockTest;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Products\Price\PackageOrderPriceDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Products\ProductStockDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-package
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Incoming\Tests\IncomingProductStockTest::class
 * @see     IncomingProductStockTest
 */
#[When(env: 'test')]
final class PackageProductStockTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        /** @var ProductStockStatusCollection $ProductStockStatusCollection */

        $ProductStockStatusCollection = self::getContainer()->get(ProductStockStatusCollection::class);
        $ProductStockStatusCollection->cases();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $main = $em->getRepository(ProductStock::class)
            ->findBy(['id' => ProductStockUid::TEST]);

        foreach($main as $remove)
        {
            $em->remove($remove);
        }

        $event = $em->getRepository(ProductStockEvent::class)
            ->findBy(['main' => ProductStockUid::TEST]);

        foreach($event as $remove)
        {
            $em->remove($remove);
        }

//        $total = $em->getRepository(ProductStockTotal::class)
//            ->findBy(['profile' => UserProfileUid::TEST]);
//
//        foreach($total as $remove)
//        {
//            $em->remove($remove);
//        }

        $em->flush();
    }

    /**
     * Тест создания заказа на упаковку
     */
    public function testUseCase(): void
    {

        $PackageProductStockDTO = new PackageProductStockDTO();

        $UserProfileUid = new UserProfileUid();
        $PackageProductStockDTO->setProfile($UserProfileUid);
        self::assertSame($UserProfileUid, $PackageProductStockDTO->getProfile());

        $PackageProductStockDTO->setNumber('Number');
        self::assertEquals('Number', $PackageProductStockDTO->getNumber());

        $ProductStockOrderDTO = $PackageProductStockDTO->getOrd();

        $OrderUid = new OrderUid();
        $ProductStockOrderDTO->setOrd($OrderUid);
        self::assertSame($OrderUid, $ProductStockOrderDTO->getOrd());


        $PackageProductStockDTO->setComment('PackageComment');
        self::assertEquals('PackageComment', $PackageProductStockDTO->getComment());


        $ProductStockDTO = new ProductStockDTO();

        $ProductUid = new ProductUid();
        $ProductStockDTO->setProduct($ProductUid);
        self::assertSame($ProductUid, $ProductStockDTO->getProduct());

        $ProductOfferConst = new ProductOfferConst();
        $ProductStockDTO->setOffer($ProductOfferConst);
        self::assertSame($ProductOfferConst, $ProductStockDTO->getOffer());

        $ProductVariationConst = new ProductVariationConst();
        $ProductStockDTO->setVariation($ProductVariationConst);
        self::assertSame($ProductVariationConst, $ProductStockDTO->getVariation());

        $ProductModificationConst = new ProductModificationConst();
        $ProductStockDTO->setModification($ProductModificationConst);
        self::assertSame($ProductModificationConst, $ProductStockDTO->getModification());


        $PackageOrderPriceDTO = new PackageOrderPriceDTO();
        $PackageOrderPriceDTO->setTotal(123);
        $ProductStockDTO->setPrice($PackageOrderPriceDTO);

        self::assertEquals(123, $PackageOrderPriceDTO->getTotal());
        self::assertEquals(123, $ProductStockDTO->getTotal());


        $PackageProductStockDTO->addProduct($ProductStockDTO);
        self::assertCount(1, $PackageProductStockDTO->getProduct());


        /** @var PackageProductStockHandler $PackageProductStockHandler */
        $PackageProductStockHandler = self::getContainer()->get(PackageProductStockHandler::class);
        $handle = $PackageProductStockHandler->handle($PackageProductStockDTO);

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

        /** Общий остаток 200, резерв 123 */
        self::assertEquals(200, $ProductStockTotal->getTotal());
        self::assertEquals(123, $ProductStockTotal->getReserve());

    }
}