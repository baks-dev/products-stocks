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

namespace BaksDev\Products\Stocks\UseCase\Admin\Purchase;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Invariable\PurchaseProductStocksInvariableDTO;
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

    //    /** Ответственное лицо (Профиль пользователя) */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private readonly UserProfileUid $profile;

    /** Статус заявки - ПРИХОД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;


    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    private PurchaseProductStocksInvariableDTO $invariable;

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
        $this->invariable = new PurchaseProductStocksInvariableDTO();
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
        $filter = $this->product->filter(function(Products\ProductStockDTO $element) use ($product) {
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

    //    /** Ответственное лицо (Профиль пользователя) */
    //    public function getProfile(): UserProfileUid
    //    {
    //        return $this->profile;
    //    }
    //
    //    public function setProfile(UserProfileUid $profile): self
    //    {
    //        $this->profile = $profile;
    //        return $this;
    //    }


    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Invariable
     */
    public function getInvariable(): PurchaseProductStocksInvariableDTO
    {
        return $this->invariable;
    }



    //    /** Номер заявки */
    //
    //    public function getNumber(): string
    //    {
    //        return $this->number;
    //    }
    //
    //    public function setNumber(string $number): void
    //    {
    //        $this->number = $number;
    //    }


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
