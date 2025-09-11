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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage\Tests;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageRepository;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageResult;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Framework\Attributes\Group;
use DateTimeImmutable;

final class AllProductStocksPackageRepositoryTest extends KernelTestCase
{
    #[Group('products-stocks')]
    #[Group('products-stocks-repository')]
    public function testRepository(): void
    {
        $AllProductStocksPackageRepository = self::getContainer()->get(AllProductStocksPackageInterface::class);

        /** @var AllProductStocksPackageRepository $AllProductStocksPackageRepository */
        $result = $AllProductStocksPackageRepository->findResultPaginator()->getData();

        foreach($result as $productStock) {
            self::assertInstanceOf(AllProductStocksPackageResult::class, $productStock);

            self::assertInstanceOf(ProductStockUid::class, $productStock->getId());
            self::assertInstanceOf(ProductStockEventUid::class, $productStock->getEvent());

            self::assertTrue(null === $productStock->getNumber() || is_string($productStock->getNumber()));
            self::assertTrue(null === $productStock->getComment() || is_string($productStock->getComment()));

            self::assertInstanceOf(ProductStockStatus::class, $productStock->getStatus());

            self::assertTrue(
                null === $productStock->getDatePackage()
                || $productStock->getDatePackage() instanceof DateTimeImmutable
            );

            self::assertIsInt($productStock->getTotal());

            self::assertTrue(null === $productStock->getStockTotal() || is_int($productStock->getStockTotal()));

            self::assertTrue(
                null === $productStock->getStockStorage()
                || is_string($productStock->getStockStorage())
            );
            self::assertTrue(
                null === $productStock->getOrderId()
                || $productStock->getOrderId() instanceof OrderUid
            );

            return;
        }

        self::assertTrue(true);
    }
}