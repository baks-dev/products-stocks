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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\Tests;

use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\AllProductStocksOrdersProductInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\ProductStocksOrdersProductResult;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;


#[Group('all-product-stocks-orders-product-repository-test')]
#[When(env: 'test')]
class AllProductStocksOrdersProductRepositoryTest extends KernelTestCase
{
    public function testAllProductStocksOrdersProductRepository(): void
    {
        self::assertTrue(true);

        /** @var AllProductStocksOrdersProductInterface $AllProductStocksOrdersProductRepository */
        $AllProductStocksOrdersProductRepository = self::getContainer()->get(AllProductStocksOrdersProductInterface::class);

        $ids = [
            "0199fdd4-779b-7085-bdc9-a681bb6a7534",
            "0199f936-ddde-76bf-b0eb-406f42a409a4",
            "0199fdd4-746f-7f8a-bc5a-ea7f99e5c177",
            "019a06c9-368a-7b42-9164-1134523e6c63",
            "019a2c0d-b574-7862-bf08-f55c9996f036",
            "019a2c0b-f756-7401-a644-fb3e5b6401e2",
        ];

        $result = $AllProductStocksOrdersProductRepository
            ->forProfile(new UserProfileUid('019577a9-71a3-714b-a99c-0386833d802f'))
            ->findAll($ids)//->find()
        ;

        if(false === $result || false === $result->valid())
        {
            return;
        }

        foreach($result as $AllProductStocksOrdersProductResult)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(ProductStocksOrdersProductResult::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($AllProductStocksOrdersProductResult);
                    dump($data);
                }
            }

            break;
        }

    }

}