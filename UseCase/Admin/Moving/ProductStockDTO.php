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

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class ProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    private ?ProductStockEventUid $id = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /** Статус заявки - ПЕРЕМЕЩЕНИЕ */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /** Константа Целевого склада */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?ContactsRegionCallConst $warehouse = null;

    /** Склад назначения при перемещении */
    #[Assert\Valid]
    private Move\ProductStockMoveDTO $move;

//    /** Константа склада назначения при перемещении */
//    #[Assert\NotBlank]
//    #[Assert\Uuid]
//    private ?ContactsRegionCallConst $destination = null;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct()
    {
        $this->status = new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving());
        $this->product = new ArrayCollection();
        $this->number = time().random_int(100, 999);
        $this->move = new Move\ProductStockMoveDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
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
        $containsProducts = $this->product->filter(function (Products\ProductStockDTO $element) use ($product) {

            return
                $element->getProduct()->equals($product->getProduct()) &&
                $element->getOffer()?->equals($product->getOffer()) &&
                $element->getVariation()?->equals($product->getVariation()) &&
                $element->getModification()?->equals($product->getModification());
        });


        if ($containsProducts->isEmpty()) {  $this->product->add($product); }
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
        $this->number = $number;
    }

    /** Константа Целевого склада */
    public function getWarehouse(): ?ContactsRegionCallConst
    {
        return $this->warehouse;
    }

    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
    {
        $this->warehouse = $warehouse;
    }

//    /** Константа склада назначения при перемещении */
//    public function getDestination(): ?ContactsRegionCallConst
//    {
//        return $this->destination;
//    }
//
//    public function setDestination(?ContactsRegionCallConst $destination): void
//    {
//        $this->destination = $destination;
//    }

    /** Склад назначения при перемещении */
    public function getMove(): Move\ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(Move\ProductStockMoveDTO $move): void
    {
        $this->move = $move;
    }
}
