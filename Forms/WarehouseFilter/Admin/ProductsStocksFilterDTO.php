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

namespace BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\HttpFoundation\Request;

final class ProductsStocksFilterDTO implements ProductsStocksFilterInterface
{
    public const warehouse = 'nQnbEasXVp';

    private Request $request;

    private ?UserProfileUid $profile;

    public function __construct(Request $request, ?UserProfileUid $profile)
    {
        $this->request = $request;
        $this->profile = $profile;
    }

    /** Склад */
    private ?ContactsRegionCallConst $warehouse = null;

    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
    {
        if ($warehouse === null)
        {
            $this->request->getSession()->remove(self::warehouse);
        }
        $this->warehouse = $warehouse;
    }

    public function getWarehouse(): ?ContactsRegionCallConst
    {
        return $this->warehouse ?: $this->request->getSession()->get(self::warehouse);
    }

    /** Профиль пользователя */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): void
    {
        $this->profile = $profile;
    }
}
