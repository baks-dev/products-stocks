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

namespace BaksDev\Products\Stocks\Repository\ProductStocksByOrder\Tests;

use BaksDev\DeliveryTransport\Type\ProductStockStatus\ProductStockStatusDelivery;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
#[Group('products-stocks')]
class ProductStockByOrderRepositoryTest extends KernelTestCase
{

    public function testFindAll(): void
    {
        self::assertTrue(true);

        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');


        /** @var ProductStocksByOrderInterface $ProductStocksByOrderInterface */
        $ProductStocksByOrderInterface = self::getContainer()->get(ProductStocksByOrderInterface::class);


        $orderID = new OrderUid('019cb527-ef55-7f86-b1e2-8bb5949f750e');
        $status = ProductStockStatusPackage::class;

        $result = $ProductStocksByOrderInterface
            ->onOrder($orderID)
            ->onStatus($status)
            ->findAll();

        if(false === empty($result))
        {
            foreach($result as $ProductStockEvent)
            {
                // Вызываем все геттеры
                $reflectionClass = new ReflectionClass(ProductStockEvent::class);
                $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

                foreach($methods as $method)
                {
                    // Методы без аргументов
                    if($method->getNumberOfParameters() === 0)
                    {
                        // Вызываем метод
                        $data = $method->invoke($ProductStockEvent);
                        // dump($data);
                    }
                }
            }
        }
    }
}
