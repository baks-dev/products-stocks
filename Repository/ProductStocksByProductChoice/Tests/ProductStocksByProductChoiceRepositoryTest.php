<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\ProductStocksByProductChoice\Tests;

use BaksDev\Products\Stocks\Repository\ProductStocksByProductChoice\ProductStocksByProductChoiceInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksByProductChoice\ProductStocksByProductChoiceRepository;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use ReflectionClass;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('products-stocks')]
#[Group('products-stocks-repository')]
final class ProductStocksByProductChoiceRepositoryTest extends KernelTestCase
{
    public function testRepository(): void
    {
        $ProductStocksByProductChoiceRepository = self::getContainer()
            ->get(ProductStocksByProductChoiceInterface::class);

        /** @var ProductStocksByProductChoiceRepository $ProductStocksByProductChoiceRepository */
        $result = $ProductStocksByProductChoiceRepository
            ->product(new ProductUid('01876b34-ed23-7c18-ba48-9071e8646a08'))
            ->profile(new UserProfileUid('01941715-9d2a-7d23-8bef-2f7dbc98331a'))
            ->fetchStocksByProduct();

        foreach($result as $stock)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(ProductStockUid::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($stock);
                    self::assertTrue(true);
                }
            }

            return;
        }
    }
}