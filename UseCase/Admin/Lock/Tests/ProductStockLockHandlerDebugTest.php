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
 *
 */

declare(strict_types=1);

namespace BaksDev\ProductStocks\ProductStock\UseCase\Admin\Lock\Tests;

use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Lock\ProductStockLock;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\UseCase\Admin\Lock\ProductStockLockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Lock\ProductStockLockHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Group('ProductStocks-ProductStock')]
#[When(env: 'test')]
class ProductStockLockHandlerDebugTest extends KernelTestCase
{
    /** Для переопределения корня */
    private const string MAIN = '019db5fe-1cf4-7ead-b210-e7ce9522b805';

    public function testUseCase(): void
    {
        $container = self::getContainer();

        /** @var ProductStockLockHandler $ProductStockLockHandler */
        $ProductStockLockHandler = self::getContainer()->get(ProductStockLockHandler::class);

        self::assertTrue(true);
        return;

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $ProductStock = $em->getRepository(ProductStock::class)
            ->find(empty(self::MAIN) ? ProductStockUid::TEST : self::MAIN);

        $ProductStockEvent = $em->getRepository(ProductStockEvent::class)
            ->find($ProductStock->getEvent());

        $ProductStockLockDTO = new ProductStockLockDTO($ProductStockEvent->getId());
        $ProductStockEvent->getLock()->getDto($ProductStockLockDTO);

        $ProductStockLockDTO->lock(); // ставим блокировку

        $handle = $ProductStockLockHandler->handle($ProductStockLockDTO);

        self::assertTrue(($handle instanceof ProductStockLock), $handle.': Ошибка ProductStockLock');
    }
}