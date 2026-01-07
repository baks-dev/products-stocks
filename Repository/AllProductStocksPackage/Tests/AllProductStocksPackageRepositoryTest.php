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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage\Tests;

use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageRepository;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageResult;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AllProductStocksPackageRepositoryTest extends KernelTestCase
{
    #[Group('products-stocks')]
    #[Group('products-stocks-repository')]
    public function testRepository(): void
    {
        self::assertTrue(true);

        $AllProductStocksPackageRepository = self::getContainer()->get(AllProductStocksPackageInterface::class);

        /** @var AllProductStocksPackageRepository $AllProductStocksPackageRepository */
        $result = $AllProductStocksPackageRepository
            ->profile(new UserProfileUid())
            ->findPaginator()
            ->getData();

        foreach($result as $productStock)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(AllProductStocksPackageResult::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($productStock);
                    self::assertTrue(true);
                }
            }

            return;
        }
    }
}