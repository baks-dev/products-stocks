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
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class MovingProductStockDTO
{

    /** Целевой склад */
    private ?UserProfileUid $targetWarehouse = null;

    /** Склад назначения */
    private ?UserProfileUid $destinationWarehouse = null;


    /** Продукт */
    private ?ProductUid $preProduct = null;

    /** Торговое предложение */
    private ?ProductOfferConst $preOffer = null;

    /** Множественный вариант */
    private ?ProductVariationConst $preVariation = null;

    /** Модификация множественного варианта */
    private ?ProductModificationConst $preModification = null;

    /** Количество */
    private ?int $preTotal = null;

    /** Коллекция перемещения  */
    #[Assert\Valid]
    private ArrayCollection $move;

    /** Комментарий */
    private ?string $comment = null;

    private UserUid $usr;



    public function __construct(User|UserUid $usr)
    {
        $this->usr = $usr instanceof User ? $usr->getId() : $usr;
        $this->move = new ArrayCollection();

    }



    // WAREHOUSE

    public function getTargetWarehouse(): ?UserProfileUid
    {
        return $this->targetWarehouse;
    }

    public function setTargetWarehouse(?UserProfileUid $warehouse): void
    {
        $this->targetWarehouse = $warehouse;
    }


    public function getDestinationWarehouse(): ?UserProfileUid
    {
        return $this->destinationWarehouse;
    }

    public function setDestinationWarehouse(?UserProfileUid $warehouse): void
    {
        $this->destinationWarehouse = $warehouse;
    }

    // PRODUCT

    public function getPreProduct(): ?ProductUid
    {
        return $this->preProduct;
    }

    public function setPreProduct(ProductUid $product): void
    {
        $this->preProduct = $product;
    }

    // OFFER

    public function getPreOffer(): ?ProductOfferConst
    {
        return $this->preOffer;
    }

    public function setPreOffer(ProductOfferConst $offer): void
    {
        $this->preOffer = $offer;
    }

    // VARIATION

    public function getPreVariation(): ?ProductVariationConst
    {
        return $this->preVariation;
    }

    public function setPreVariation(?ProductVariationConst $preVariation): void
    {
        $this->preVariation = $preVariation;
    }

    // MODIFICATION

    public function getPreModification(): ?ProductModificationConst
    {
        return $this->preModification;
    }

    public function setPreModification(?ProductModificationConst $preModification): void
    {
        $this->preModification = $preModification;
    }

    // TOTAL

    public function getPreTotal(): ?int
    {
        return $this->preTotal;
    }

    public function setPreTotal(int $total): void
    {
        $this->preTotal = $total;
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


    /** Коллекция продукции  */
    public function getMove(): ArrayCollection
    {
        return $this->move;
    }

    public function setMove(ArrayCollection $move): void
    {
        $this->move = $move;
    }

    public function addMove(ProductStockDTO $move): void
    {
        $this->move->add($move);
    }

    public function removeMove(ProductStockDTO $move): void
    {
        $this->move->removeElement($move);
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

}
