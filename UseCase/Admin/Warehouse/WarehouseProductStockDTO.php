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
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class WarehouseProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

//    /** Склад */
//    #[Assert\NotBlank]
//    #[Assert\Uuid]
//    private ?ContactsRegionCallConst $warehouse = null;

//    /** Целевой склад при перемещении */
//    #[Assert\Uuid]
//    private ?UserProfileUid $destination = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;

    /** Статус заявки - ОТПАРВЛЕН НА СКЛАД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Комментарий */
    private ?string $comment = null;

    /** Вспомогательные свойства */
    private readonly UserUid $usr;

    /** Коллекция перемещения  */
    #[Assert\Valid]
    private ?Move\ProductStockMoveDTO $move;


    public function __construct(User|UserUid $usr)
    {
        $this->usr = $usr instanceof User ? $usr->getId() : $usr;
        $this->status = new ProductStockStatus(new ProductStockStatus\ProductStockStatusWarehouse());
        //$this->move = new ArrayCollection();
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

    /** Профиль назначения */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }


    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    /**
     * Move
     */
    public function getMove(): ?Move\ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(?Move\ProductStockMoveDTO $move): self
    {
        $this->move = $move;
        return $this;
    }







//    /** Коллекция продукции  */
//    public function getMove(): ArrayCollection
//    {
//        return $this->move;
//    }

//    public function setMove(ArrayCollection $move): void
//    {
//        $this->move = $move;
//    }
//
//    public function addMove(Move\ProductStockMoveDTO $move): void
//    {
//        $this->move->add($move);
//    }

//    /** Склад */
//    public function getWarehouse(): ContactsRegionCallConst
//    {
//        /* Если перемещение между складами - перемещаем */
//        if ($this->destination !== null)
//        {
//            $this->profile = $this->destination;
//            $this->destination = null;
//        }
//
//        return $this->profile;
//    }

//    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
//    {
//        $this->warehouse = $warehouse;
//    }

//    /** Целевой склад при перемещении */
//    public function getDestination(): ?UserProfileUid
//    {
//        return $this->destination;
//    }
//
//    public function setDestination(?UserProfileUid $destination): void
//    {
//        $this->destination = $destination;
//    }
}
