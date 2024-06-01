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

namespace BaksDev\Products\Stocks\UseCase\Admin\Purchase\Tests;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-purchase
 */
#[When(env: 'test')]
final class PurchaseProductStockTest extends KernelTestCase
{

    public static function setUpBeforeClass(): void
    {
        $ProductStockStatus = new ProductStockStatusPurchase();

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

        $total = $em->getRepository(ProductStockTotal::class)
            ->findBy(['profile' => UserProfileUid::TEST]);

        foreach($total as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();

        $em->clear();

    }




    /**
     * Тест нового закупочного листа
     */
    public function testUseCase(): void
    {
        $PurchaseProductStockDTO = new PurchaseProductStockDTO(clone new UserProfileUid());

        $PurchaseProductStockDTO->setComment('Comment');
        self::assertEquals('Comment', $PurchaseProductStockDTO->getComment());

        $PurchaseProductStockDTO->setNumber('Number');
        self::assertEquals('Number', $PurchaseProductStockDTO->getNumber());

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

        $ProductStockDTO->setTotal(100);
        self::assertEquals(100, $ProductStockDTO->getTotal());

        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
        self::assertCount(1, $PurchaseProductStockDTO->getProduct());


        $ProductStockDTO = new ProductStockDTO();
        $ProductStockDTO->setProduct(clone $ProductUid);
        $ProductStockDTO->setOffer(clone $ProductOfferConst);
        $ProductStockDTO->setVariation(clone $ProductVariationConst);
        $ProductStockDTO->setModification(clone $ProductModificationConst);
        $ProductStockDTO->setTotal(200);

        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
        self::assertCount(2, $PurchaseProductStockDTO->getProduct());

        $PurchaseProductStockDTO->removeProduct($ProductStockDTO);
        self::assertCount(1, $PurchaseProductStockDTO->getProduct());

        /** @var PurchaseProductStockHandler $PurchaseProductStockHandler */
        $PurchaseProductStockHandler = self::getContainer()->get(PurchaseProductStockHandler::class);
        $handle = $PurchaseProductStockHandler->handle($PurchaseProductStockDTO);

        self::assertTrue(($handle instanceof ProductStock), $handle.': Ошибка ProductStock');


    }
}
