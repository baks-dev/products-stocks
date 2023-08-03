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

namespace BaksDev\Products\Stocks\Controller\Admin\Purchase\Tests;

use BaksDev\Users\User\Tests\TestUserAccount;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/** @group products-stocks */
#[When(env: 'test')]
final class IndexControllerTest extends WebTestCase
{
    private const URL = '/admin/product/stocks/purchase';

    private const ROLE = 'ROLE_PRODUCT_STOCK_PURCHASE';

    /** Доступ по роли ROLE_PRODUCT */
    public function testRoleProductSuccessful(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        foreach (TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);

            $user = TestUserAccount::getModer(self::ROLE);

            $client->loginUser($user, 'user');
            $client->request('GET', self::URL);
        }

        self::assertResponseIsSuccessful();
    }

    /** Доступ по роли ROLE_ADMIN */
    public function testRoleAdminSuccessful(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        foreach (TestUserAccount::getDevice() as $device)
        {
            $user = TestUserAccount::getAdmin();

            $client->loginUser($user, 'user');
            $client->request('GET', self::URL);

            self::assertResponseIsSuccessful();
        }
    }

    /** Доступ по роли ROLE_USER */
    public function testRoleUserFiled(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        
        foreach (TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);
            $user = TestUserAccount::getUser();
            $client->loginUser($user, 'user');
            $client->request('GET', self::URL);

            self::assertResponseStatusCodeSame(403);
        }
    }

    /** Доступ по без роли */
    public function testGuestFiled(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        foreach (TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);
            $client->request('GET', self::URL);

            self::assertResponseStatusCodeSame(401);
        }
    }
}
