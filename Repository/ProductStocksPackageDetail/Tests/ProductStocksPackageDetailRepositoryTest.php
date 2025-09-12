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

namespace BaksDev\Products\Stocks\Repository\ProductStocksPackageDetail\Tests;

use BaksDev\Products\Stocks\Repository\ProductStocksPackageDetail\ProductStocksPackageDetailInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksPackageDetail\ProductStocksPackageDetailRepository;
use BaksDev\Products\Stocks\Repository\ProductStocksPackageDetail\ProductStocksPackageDetailResult;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ReflectionClass;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\Group;

final class ProductStocksPackageDetailRepositoryTest extends KernelTestCase
{
    #[Group('products-stocks')]
    #[Group('products-stocks-repository')]
    public function testRepository(): void
    {
        $ProductStocksPackageDetailRepository = self::getContainer()->get(ProductStocksPackageDetailInterface::class);

        /** @var ProductStocksPackageDetailRepository $ProductStocksPackageDetailRepository */
        $result = $ProductStocksPackageDetailRepository
            ->profile(new UserProfileUid('01941715-9d2a-7d23-8bef-2f7dbc98331a'))
            ->find(new ProductStockUid('0199330e-fdf6-7289-b982-3be3bc7e4187'));

        foreach($result as $product) {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(ProductStocksPackageDetailResult::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($product);
                    self::assertTrue(true);
                }
            }

            return;
        }
    }
}