<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Stock;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotalInterface;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

final class MovingProductToStockDTO implements ProductStockTotalInterface
{
    /** С какого места складирования ID  */
    private ProductStockTotalUid $fromId;

    /** На какое место складирования ID  */
    private ?ProductStockTotalUid $toId = null;

    /** ID пользователя */
    private ?UserUid $usr = null;

    /** ID профиля (склад) */
    private UserProfileUid $profile;

    /** ID продукта */
    private ProductUid $product;

    /** Постоянный уникальный идентификатор ТП */
    private ?ProductOfferConst $offer;

    /** Постоянный уникальный идентификатор варианта */
    private ?ProductVariationConst $variation;

    /** Постоянный уникальный идентификатор модификации */
    private ?ProductModificationConst $modification;

    /** Комментарий */
    private ?string $comment = null;

    /** Место складирования */
    private ?string $storage = null;

    /** Общее количество на данном складе */
    #[Assert\When(expression: 'this.isValidTotal() === true', constraints: new Assert\NotBlank())]
    private int $total = 0;


    /** Общее количество на данном складе */
    private int $reserve = 0;

    /** Количество продукции для перемещения */
    private int $totalToMove = 0;

    public function getFromId(): ProductStockTotalUid
    {
        return $this->fromId;
    }

    public function getToId(): ?ProductStockTotalUid
    {
        return $this->toId;
    }

    public function getUsr(): ?UserUid
    {
        return $this->usr;
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }

    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation;
    }

    public function getModification(): ?ProductModificationConst
    {
        return $this->modification;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getReserve(): int
    {
        return $this->reserve;
    }

    public function setFromId(ProductStockTotalUid $fromId): self
    {
        $this->fromId = $fromId;
        return $this;
    }

    public function setToId(?ProductStockTotalUid $toId): self
    {
        $this->toId = $toId;
        return $this;
    }

    public function setUsr(?UserUid $usr): self
    {
        $this->usr = $usr;
        return $this;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function setProduct(ProductUid $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function setOffer(?ProductOfferConst $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    public function setVariation(?ProductVariationConst $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    public function setModification(?ProductModificationConst $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function setStorage(?string $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getTotalToMove(): int
    {
        return $this->totalToMove;
    }

    public function setTotalToMove(int $totalToMove): self
    {
        $this->totalToMove = $totalToMove;
        return $this;
    }

    public function isValidTotal() : bool
    {
        $move = ($this->total - $this->totalToMove) >= $this->reserve;

        $move ?: throw new \InvalidArgumentException('Invalid Argument Total');

        return $move;
    }
}