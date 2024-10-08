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

namespace BaksDev\Products\Stocks\UseCase\Admin\Purchase;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class PurchaseProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\Uuid]
    #[Assert\IsNull]
    private ?ProductStockEventUid $id = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserProfileUid $profile;

    /** Статус заявки - ПРИХОД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    // Вспомогательные свойства

    /** Категория */
    private ?CategoryProductUid $category = null;

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

    public function __construct()
    {
        $this->status = new ProductStockStatus(ProductStockStatusPurchase::class);
        $this->product = new ArrayCollection();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }

    /**
     * Category
     */
    public function getCategory(): ?CategoryProductUid
    {
        return $this->category;
    }

    public function setCategory(?CategoryProductUid $category): self
    {
        $this->category = $category;
        return $this;
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
        $filter = $this->product->filter(function (Products\ProductStockDTO $element) use ($product) {
            return $element->getProduct()->equals($product->getProduct()) &&
                $element->getOffer()?->equals($product->getOffer()) &&
                $element->getVariation()?->equals($product->getVariation()) &&
                $element->getModification()?->equals($product->getModification());
        });

        if($filter->isEmpty())
        {
            $this->product->add($product);
        }
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

    /** Номер заявки */

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }


    /** ВСПОМОГАТЕЛЬНЫЕ СВОЙСТВА */

    //    // WAREHOUSE
    //
    //    public function getPreWarehouse(): ?ContactsRegionCallUid
    //    {
    //        return $this->preWarehouse;
    //    }
    //
    //    public function setPreWarehouse(?ContactsRegionCallUid $warehouse): void
    //    {
    //        $this->preWarehouse = $warehouse;
    //    }

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


}
