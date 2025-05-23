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

namespace BaksDev\Products\Stocks\Controller\Admin\Warehouse\Tests;

use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\User\Tests\TestUserAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Tests\WarehouseProductStockTest::class
 * @see     WarehouseMaterialStockTest
 */
#[When(env: 'test')]
final class WarehouseControllerTest extends WebTestCase
{
    private const string URL = '/admin/product/stock/warehouse/%s';

    private const string ROLE = 'ROLE_PRODUCT_STOCK_WAREHOUSE_SEND';

    private static ?ProductStockEventUid $identifier;

    public static function setUpBeforeClass(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $ProductStock = $em
            ->getRepository(ProductStock::class)
            ->find(ProductStockUid::TEST);

        self::$identifier = $ProductStock->getEvent();
    }

    /** Доступ по роли */
    public function testRoleSuccessful(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getModer(self::ROLE);

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, self::$identifier));

        self::assertResponseIsSuccessful();

    }

    // доступ по роли ROLE_ADMIN
    public function testRoleAdminSuccessful(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getAdmin();

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, self::$identifier));

        self::assertResponseIsSuccessful();

    }

    // Закрытый доступ по роли ROLE_USER
    public function testRoleUserDeny(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getUsr();

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, self::$identifier));

        self::assertResponseStatusCodeSame(403);

    }

    /** Закрытый Доступ по без роли */
    public function testGuestFiled(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', sprintf(self::URL, self::$identifier));

        // Full authentication is required to access this resource
        self::assertResponseStatusCodeSame(401);

    }
}
