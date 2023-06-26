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

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

final class WarehouseProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    /** Склад */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ContactsRegionCallConst $warehouse;

    /** Целевой склад при перемещении */
    #[Assert\Uuid]
    private ?ContactsRegionCallConst $destination = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserProfileUid $profile;

    /** Статус заявки - ОТПАРВЛЕН НА СКЛАД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct(UserProfileUid $profile)
    {
        $this->profile = $profile;
        $this->status = new ProductStockStatus(new ProductStockStatus\ProductStockStatusWarehouse());
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }

    /** Комментарий */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    /** Ответственное лицо (Профиль пользователя) */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /** Склад */
    public function getWarehouse(): ContactsRegionCallConst
    {
        /* Если перемещение между складами - перемещаем */
        if ($this->destination !== null)
        {
            $this->warehouse = $this->destination;
            $this->destination = null;
        }

        return $this->warehouse;
    }

    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
    {
        $this->warehouse = $warehouse;
    }

    /** Целевой склад при перемещении */
    public function getDestination(): ?ContactsRegionCallConst
    {
        return $this->destination;
    }

    public function setDestination(?ContactsRegionCallConst $destination): void
    {
        $this->destination = $destination;
    }
}
