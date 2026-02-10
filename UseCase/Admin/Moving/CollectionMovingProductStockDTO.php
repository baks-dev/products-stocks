<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class CollectionMovingProductStockDTO
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

    public function setTargetWarehouse(?UserProfileUid $warehouse): self
    {
        $this->targetWarehouse = $warehouse;
        return $this;
    }

    public function getDestinationWarehouse(): ?UserProfileUid
    {
        return $this->destinationWarehouse;
    }

    public function setDestinationWarehouse(?UserProfileUid $warehouse): self
    {
        $this->destinationWarehouse = $warehouse;
        return $this;
    }

    // PRODUCT
    public function getPreProduct(): ?ProductUid
    {
        return $this->preProduct;
    }

    public function setPreProduct(?ProductUid $product): self
    {
        $this->preProduct = $product;
        return $this;
    }

    // OFFER
    public function getPreOffer(): ?ProductOfferConst
    {
        return $this->preOffer;
    }

    public function setPreOffer(?ProductOfferConst $offer): self
    {
        $this->preOffer = $offer;
        return $this;
    }

    // VARIATION
    public function getPreVariation(): ?ProductVariationConst
    {
        return $this->preVariation;
    }

    public function setPreVariation(?ProductVariationConst $preVariation): self
    {
        $this->preVariation = $preVariation;
        return $this;
    }

    // MODIFICATION
    public function getPreModification(): ?ProductModificationConst
    {
        return $this->preModification;
    }

    public function setPreModification(?ProductModificationConst $preModification): self
    {
        $this->preModification = $preModification;
        return $this;
    }

    // TOTAL
    public function getPreTotal(): ?int
    {
        return $this->preTotal;
    }

    public function setPreTotal(int $total): self
    {
        $this->preTotal = $total;
        return $this;
    }

    /** Комментарий */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Коллекция продукции
     *
     * @return ArrayCollection<MovingProductStockDTO>
     */
    public function getMove(): ArrayCollection
    {
        return $this->move;
    }

    public function setMove(ArrayCollection $move): self
    {
        $this->move = $move;
        return $this;
    }

    public function addMove(MovingProductStockDTO $move): self
    {
        $this->move->add($move);
        return $this;
    }

    public function removeMove(MovingProductStockDTO $move): self
    {
        $this->move->removeElement($move);
        return $this;
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }
}
