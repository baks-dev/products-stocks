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

namespace BaksDev\Products\Stocks\Messenger\Orders\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Messenger\Orders\CancelProductStocksByCancelOrderDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
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
class CancelProductStocksByCancelOrderDispatcherDebugTest extends KernelTestCase
{

    public function testUseCase(): void
    {
        self::assertTrue(true);

        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var CancelProductStocksByCancelOrderDispatcher $CancelProductStocksByCancelOrderDispatcher */
        $CancelProductStocksByCancelOrderDispatcher = self::getContainer()->get(CancelProductStocksByCancelOrderDispatcher::class);

        // 'id' => '01992feb-aaf0-7e42-a242-c4d6de87db76',\n
        //   'event' => '01992fef-5037-7de4-a561-963a95d45750',\n
        //   'last' => '01992fed-96c0-7e8b-a048-ac743f3070c5'

        $OrderMessage = new OrderMessage(
            new OrderUid('01992feb-aaf0-7e42-a242-c4d6de87db76'),
            new OrderEventUid('01992fef-5037-7de4-a561-963a95d45750'),
            new OrderEventUid('01992fed-96c0-7e8b-a048-ac743f3070c5'),
        );

        $CancelProductStocksByCancelOrderDispatcher($OrderMessage);
    }

}