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
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Print\Tests;


use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\UseCase\Admin\Print\ProductStockEventPrintDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Print\ProductStockEventPrintHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Tests\PurchaseProductStockTest;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('products-stocks')]
#[When(env: 'test')]
final class ProductStockEventPrintHandlerTest extends KernelTestCase
{
    #[DependsOnClass(PurchaseProductStockTest::class)]
    public function testHandle(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $ProductStock = $em->getRepository(ProductStock::class)->find(ProductStockUid::TEST);
        self::assertInstanceOf(ProductStock::class, $ProductStock);

        /** @var ProductStockEventPrintHandler $ProductStockEventPrintHandler */
        $ProductStockEventPrintHandler = self::getContainer()->get(ProductStockEventPrintHandler::class);
        $ProductStockEventPrintDTO = new ProductStockEventPrintDTO($ProductStock->getEvent());
        $result = $ProductStockEventPrintHandler->handle($ProductStockEventPrintDTO);

        self::assertTrue($result);
    }
}