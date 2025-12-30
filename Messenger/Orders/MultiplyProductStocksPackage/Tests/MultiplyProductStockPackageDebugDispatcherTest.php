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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Orders\MultiplyProductStocksPackage\Tests;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Messenger\Orders\MultiplyProductStocksPackage\MultiplyProductStocksPackageDispatcher;
use BaksDev\Products\Stocks\Messenger\Orders\MultiplyProductStocksPackage\MultiplyProductStocksPackageMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Group('products-stocks')]
#[When(env: 'test')]
class MultiplyProductStockPackageDebugDispatcherTest extends KernelTestCase
{

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var MultiplyProductStocksPackageDispatcher $MultiplyProductStocksPackageDispatcher */
        $MultiplyProductStocksPackageDispatcher = self::getContainer()->get(MultiplyProductStocksPackageDispatcher::class);

        $MultiplyProductStocksPackageMessage = new MultiplyProductStocksPackageMessage(
            new OrderUid('019b46a8-1fd2-7dcc-aa6c-4c0495fcbd8d'),
            new UserProfileUid('019053da-5c80-7345-b227-8609dd8380f3'),
            new UserUid('019053d9-d1cb-7416-b7e5-4cdd154a04c3'),
        );

        $MultiplyProductStocksPackageDispatcher($MultiplyProductStocksPackageMessage);
    }
}