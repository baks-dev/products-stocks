<?php 
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Tests\PurchaseProductStockTest;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockHandler;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
#[Group('products-stocks')]
final class WarehouseProductStockTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');
    }


    /**
     * Тест нового закупочного листа
     */
    #[DependsOnClass(PurchaseProductStockTest::class)]
    public function testUseCase(): void
    {
        /** @var CurrentProductStocksInterface $CurrentProductStocksInterface */
        $CurrentProductStocksInterface = self::getContainer()->get(CurrentProductStocksInterface::class);
        $ProductStockEvent = $CurrentProductStocksInterface->getCurrentEvent(new ProductStockUid(ProductStockUid::TEST));

        /** @var WarehouseProductStockDTO $WarehouseProductStockDTO */
        $WarehouseProductStockDTO = new WarehouseProductStockDTO();
        $ProductStockEvent->getDto($WarehouseProductStockDTO);

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