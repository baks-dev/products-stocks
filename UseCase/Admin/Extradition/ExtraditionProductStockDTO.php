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

namespace BaksDev\Products\Stocks\UseCase\Admin\Extradition;

use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class ExtraditionProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserProfileUid $profile;

    /** Фиксация заявки пользователем  */
    #[Assert\IsNull]
    private readonly ?UserProfileUid $fixed;

    /** Статус заявки - Укомплектована для погрузки (выдачи) */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct()
    {
        $this->fixed = null;
        $this->status = new ProductStockStatus(ProductStockStatusExtradition::class);
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

    /** Склад (Профиль пользователя) */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /** Фиксация заявки пользователем  */
    public function getFixed(): ?UserProfileUid
    {
        return $this->fixed;
    }

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

}
