<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\Controller\Admin\Warehouse\Tests;

use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Users\User\Tests\TestUserAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 *
 * @depends BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Tests\WarehouseProductStockTest::class
 * @see     WarehouseProductStockTest
 */
#[When(env: 'test')]
final class WarehouseControllerTest extends WebTestCase
{
    private const URL = '/admin/product/stock/warehouse/%s';

    private const ROLE = 'ROLE_PRODUCT_STOCK_WAREHOUSE_SEND';

    //    private static ?ProductStockEventUid $identifier;

    //    public static function setUpBeforeClass(): void
    //    {
    //        $em = self::getContainer()->get(EntityManagerInterface::class);
    //        self::$identifier = $em->getRepository(ProductStock::class)->findOneBy([], ['id' => 'DESC'])?->getEvent();
    //    }

    /** Доступ по роли */
    public function testRoleSuccessful(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getModer(self::ROLE);

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, ProductStockEventUid::TEST));

        self::assertResponseIsSuccessful();

    }

    // доступ по роли ROLE_ADMIN
    public function testRoleAdminSuccessful(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getAdmin();

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, ProductStockEventUid::TEST));

        self::assertResponseIsSuccessful();

    }

    // Закрытый доступ по роли ROLE_USER
    public function testRoleUserDeny(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();

        $usr = TestUserAccount::getUsr();

        $client->loginUser($usr, 'user');
        $client->request('GET', sprintf(self::URL, ProductStockEventUid::TEST));

        self::assertResponseStatusCodeSame(403);

    }

    /** Закрытый Доступ по без роли */
    public function testGuestFiled(): void
    {

        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', sprintf(self::URL, ProductStockEventUid::TEST));

        // Full authentication is required to access this resource
        self::assertResponseStatusCodeSame(401);

    }
}
