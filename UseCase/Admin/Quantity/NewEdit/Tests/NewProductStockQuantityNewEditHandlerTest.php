<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Tests;

use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Tests\ProductsProductNewAdminUseCaseTest;
use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEvent;
use BaksDev\Products\Stocks\Entity\Quantity\ProductStockQuantity;
use BaksDev\Products\Stocks\Type\Quantity\Id\ProductStockQuantityUid;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Comment\ProductStockQuantityNewEditCommentDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\ProductStockQuantityNewEditDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\ProductStockQuantityNewEditHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('products-stocks')]
#[Group('products-stocks-repository')]
#[Group('products-stocks-usecase')]
final class NewProductStockQuantityNewEditHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        /** Подчищаем тестовые данные, которые могли остаться с предыдущих запусков тестов */
        $container = self::getContainer();

        /** @var EntityManagerInterface $EntityManager */
        $EntityManager = $container->get(EntityManagerInterface::class);
        $productStockQuantity = $EntityManager
            ->getRepository(ProductStockQuantity::class)
            ->find(ProductStockQuantityUid::TEST);

        if($productStockQuantity)
        {
            $EntityManager->remove($productStockQuantity);
        }

        $productStockQuantityEvent = $EntityManager
            ->getRepository(ProductStockQuantityEvent::class)
            ->findBy(['main' => ProductStockQuantityUid::TEST]);

        foreach($productStockQuantityEvent as $remove)
        {
            $EntityManager->remove($remove);
        }

        $EntityManager->flush();


        /** Создаем тестовый продукт */
        ProductsProductNewAdminUseCaseTest::setUpBeforeClass();
        new ProductsProductNewAdminUseCaseTest('')->testUseCase();
    }

    public function testUseCase(): void
	{
        $ProductStockQuantityNewHandler = self::getContainer()->get(ProductStockQuantityNewEditHandler::class);

        $productStockQuantityNewDTO = new ProductStockQuantityNewEditDTO();


        /** Approve */
        $productStockQuantityNewDTO
            ->getApprove()
            ->setValue(true);


        /** Comment */
        $productStockQuantityNewCommentDTO = new ProductStockQuantityNewEditCommentDTO()->setValue('test');
        $productStockQuantityNewDTO->setComment($productStockQuantityNewCommentDTO);


        /** Invariable */
        $productStockQuantityNewDTO
            ->getInvariable()
            ->setInvariable(new ProductInvariableUid())
            ->setUsr(new UserUid())
            ->setProfile(new UserProfileUid())
            ->setStorage('test')
            ->setPriority(false);


        /** Total/Reserve */
        $productStockQuantityNewDTO
            ->setTotal(10000)
            ->setReserve(1);


        /** @var ProductStockQuantityNewEditHandler $ProductStockQuantityNewHandler */
        $handle = $ProductStockQuantityNewHandler->handle($productStockQuantityNewDTO);

        self::assertInstanceOf(ProductStockQuantity::class, $handle);
	}
}