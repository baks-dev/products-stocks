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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Type\UidType\Uid;
use BaksDev\Orders\Order\Entity\Event\OrderEventInterface;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariableInterface;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class PackageProductStockDTO implements ProductStockEventInterface, OrderEventInterface
{
    /** Идентификатор */
    private ?ProductStockEventUid $id = null;

    /**
     * Ответственное лицо (Профиль пользователя)
     * @deprecated Переносится в Invariable
     */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /** Статус заявки - УПАКОВКА */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /** Постоянная величина */
    #[Assert\Valid]
    private readonly Invariable\PackageOrderInvariableDTO $invariable;


    //    /** Константа Целевого склада */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private ?ContactsRegionCallConst $warehouse = null;

    //    /** Константа склада назначения при перемещении */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private ?ContactsRegionCallConst $destination = null;


    /** Идентификатор заказа на сборку */
    private Orders\ProductStockOrderDTO $ord;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    /** Вспомогательные свойства */
    private readonly UserUid $usr;


    public function __construct(User|UserUid $user)
    {
        $user = $user instanceof User ? $user->getId() : $user;

        $this->usr = $user;

        $this->status = new ProductStockStatus(ProductStockStatusPackage::class);
        $this->product = new ArrayCollection();

        //$this->number = number_format(microtime(true) * 100, 0, '.', '.');

        $this->ord = new Orders\ProductStockOrderDTO();


        $PackageOrderInvariable = new Invariable\PackageOrderInvariableDTO();
        $PackageOrderInvariable->setUsr($user);

        $this->invariable = $PackageOrderInvariable;

    }


    public function getEvent(): ?Uid
    {
        return null;
    }

    public function setId(ProductStockEventUid|OrderEventUid $id): void
    {
        if($id instanceof ProductStockEventUid)
        {
            $this->id = $id;
        }
    }

    public function resetId(): void
    {
        $this->id = null;
    }

    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(Products\ProductStockDTO $product): void
    {
        $this->product->add($product);
    }

    public function removeProduct(Products\ProductStockDTO $product): void
    {
        $this->product->removeElement($product);
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
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): void
    {

        /** Присваиваем постоянную величину  */
        $PackageOrderInvariable = $this->getInvariable();
        $PackageOrderInvariable->setProfile($profile);

        $this->profile = $profile;
    }

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /** Номер заявки */
    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->invariable->setNumber($number);
        $this->number = $number;
    }

    //    /** Константа Целевого склада */
    //    public function getWarehouse(): ?ContactsRegionCallConst
    //    {
    //        return $this->warehouse;
    //    }
    //
    //    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
    //    {
    //        $this->warehouse = $warehouse;
    //    }


    /** Идентификатор заказа на сборку */

    public function getOrd(): Orders\ProductStockOrderDTO
    {
        return $this->ord;
    }


    public function setOrd(Orders\ProductStockOrderDTO $ord): void
    {
        $this->ord = $ord;
    }


    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\PackageOrderInvariableDTO
    {
        return $this->invariable;
    }


}
