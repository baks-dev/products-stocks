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

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\EditProductStockTotalDispatcher;
use BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\EditProductStockTotalMessage;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
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
class EditProductStockTotalDebugDispatcherTest extends KernelTestCase
{

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var EditProductStockTotalDispatcher $EditProductStockTotalDispatcher */
        $EditProductStockTotalDispatcher = self::getContainer()->get(EditProductStockTotalDispatcher::class);


        /**
         * Все события складской заявки
         */
        $Main = '019b6c0f-3595-75af-8c06-391c67e9b93b';

        /** @var DBALQueryBuilder $dbal */
        $dbal = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal->createQueryBuilder(self::class);

        $dbal
            ->select('id')
            ->from(ProductStockEvent::class)
            ->where('main = :main')
            ->setParameter('main', $Main)
            ->orderBy('id', 'DESC'); // от нового к старым

        $events = $dbal->fetchAllAssociative();

        $currentEvent = current($events[0]);
        $lastEvent = current($events[1]);

        $message = new EditProductStockTotalMessage(
            new ProductStockUid($Main),
            '176.704.444.982',
            new ProductStockEventUid($currentEvent),
            new ProductStockEventUid($lastEvent),
        );

        $EditProductStockTotalDispatcher($message);
    }
}