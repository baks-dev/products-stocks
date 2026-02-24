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

namespace BaksDev\Products\Stocks\Repository\AllProductStocks;

use BaksDev\Products\Product\Repository\ProductPriceResultInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;

final readonly class AllProductStocksResult implements ProductPriceResultInterface
{
    public function __construct(
        private string $stock_id, //" => "01960a8a-600d-7b9c-923b-389ad9cc3e8d"
        private ?int $stock_total, //" => 1100
        private ?string $stock_storage, //" => null
        private ?int $stock_reserve, //" => 4
        private ?string $stock_comment, //" => null
        private ?bool $stock_priority, //" => null

        private string $users_profile_id, //" => "019577a9-71a3-714b-a99c-0386833d802f"
        private string $product_id, //" => "01876b34-ed23-7c18-ba48-9071e8646a08"
        private string $product_event, //" => "01963548-294f-71a6-b4b5-705cc4c470bd"
        private string $product_url, //" => "triangle_advantex_tc101"
        private string $product_name, //" => "Triangle AdvanteX TC101"
        private string $modify, //" => "Triangle AdvanteX TC101"

        private ?string $product_offer_uid, //" => "01963548-2954-7b9a-a892-858b6f10f6c6"
        private ?string $product_offer_value, //" => "15"
        private ?string $product_offer_postfix, //" => null
        private ?string $product_offer_reference, //" => "tire_radius_field"
        private ?string $product_offer_const, //" => "01963548-2954-7b9a-a892-858b6f10f6c6"
        private ?string $product_variation_uid, //" => "01963548-2954-7b9a-a892-858b6f133327"
        private ?string $product_variation_value, //" => "185"
        private ?string $product_variation_postfix, //" => null
        private ?string $product_variation_reference, //" => "tire_width_field"
        private ?string $product_variation_const, //" => "01963548-2954-7b9a-a892-858b6f10f6c6"
        private ?string $product_modification_uid, //" => "01963548-2954-7b9a-a892-858b6fae243f"
        private ?string $product_modification_value, //" => "55"
        private ?string $product_modification_postfix, //" => "82V"
        private ?string $product_modification_reference, //" => "tire_profile_field"
        private ?string $product_modification_const, //" => "01963548-2954-7b9a-a892-858b6f10f6c6"
        private ?string $product_article, //" => "TC101-15-185-55-82V"
        private ?int $product_price, //" => 490000
        private ?int $old_product_price, //" => 490000

        private ?string $product_image, //" => "/upload/product_photo/6c0003c59af4454b3e3697ddec435f3f"
        private ?string $product_image_ext, //" => "webp"
        private ?bool $product_image_cdn, //" => true

        private string $category_name, //" => "Triangle"
        private string $category_url, //" => "triangle"

        private string $users_profile_username, //" => "admin"
        private ?string $users_profile_location, //" => null

        private string|null $profile_discount = null,
        private string|null $project_discount = null,

        private bool|null $promotion_active = null,
        private string|int|null $promotion_price = null,

        private bool|null $approve = true,

    ) {}

    public function getApprove(): bool
    {
        return $this->approve === true;
    }

    public function getStockId(): ProductStockTotalUid
    {
        return new ProductStockTotalUid($this->stock_id);
    }

    public function getStockTotal(): ?int
    {
        return $this->stock_total ?: 0;
    }

    public function getStockStorage(): string
    {
        return $this->stock_storage ?: '-';
    }

    public function getStockReserve(): ?int
    {
        return $this->stock_reserve ?: 0;
    }

    public function getQuantity(): int
    {
        return $this->getStockTotal() - $this->getStockReserve();
    }

    public function getStockComment(): ?string
    {
        return $this->stock_comment;
    }

    public function getUsersProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->users_profile_id);
    }

    public function getProductId(): ProductUid
    {
        return new ProductUid($this->product_id);
    }

    public function getProductEvent(): ProductEventUid
    {
        return new ProductEventUid($this->product_event);
    }

    public function getProductUrl(): string
    {
        return $this->product_url;
    }

    public function getProductName(): string
    {
        return $this->product_name;
    }

    /** Offer */

    public function getProductOfferUid(): ?ProductOfferUid
    {
        return $this->product_offer_uid ? new ProductOfferUid($this->product_offer_uid) : null;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value ?: null;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix ?: null;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference ?: null;
    }

    /** Variation */

    public function getProductVariationUid(): ?ProductVariationUid
    {
        return $this->product_variation_uid ? new ProductVariationUid($this->product_variation_uid) : null;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value ?: null;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix ?: null;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference ?: null;
    }

    /** Modification */

    public function getProductModificationUid(): ?ProductModificationUid
    {
        return $this->product_modification_uid ? new ProductModificationUid($this->product_modification_uid) : null;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value ?: null;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix ?: null;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference ?: null;
    }


    public function getProductArticle(): ?string
    {
        return $this->product_article;
    }


    public function getProductPrice(): Money|false
    {
        if(empty($this->product_price))
        {
            return false;
        }

        $price = new Money($this->product_price, true);

        /** Кастомная цена */
        if(false === empty($this->promotion_price) && true === $this->promotion_active)
        {
            $price->applyString($this->promotion_price);
        }

        /** Скидка магазина */
        if(false === empty($this->project_discount))
        {
            $price->applyString($this->project_discount);
        }

        /** Скидка пользователя */
        if(false === empty($this->profile_discount))
        {
            $price->applyString($this->profile_discount);
        }

        return $price;
    }


    public function getOldProductPrice(): Money|false
    {
        if(empty($this->old_product_price))
        {
            return false;
        }

        $oldPrice = new Money($this->old_product_price, true);

        /** Кастомная цена */
        if(false === empty($this->promotion_price) && true === $this->promotion_active)
        {
            $oldPrice->applyString($this->promotion_price);
        }

        /** Скидка магазина */
        if(false === empty($this->project_discount))
        {
            $oldPrice->applyString($this->project_discount);
        }

        /** Скидка пользователя */
        if(false === empty($this->profile_discount))
        {
            $oldPrice->applyString($this->profile_discount);
        }

        return $oldPrice;
    }

    /**
     * ProductImage
     */
    public function getProductImage(): ?string
    {
        return $this->product_image;
    }

    public function getProductImageExt(): ?string
    {
        return $this->product_image_ext;
    }

    public function getProductImageCdn(): bool
    {
        return $this->product_image_cdn === true;
    }

    public function getCategoryName(): string
    {
        return $this->category_name;
    }

    public function getCategoryUrl(): string
    {
        return $this->category_url;
    }

    public function getUsersProfileUsername(): string
    {
        return $this->users_profile_username;
    }

    public function getUsersProfileLocation(): ?string
    {
        return $this->users_profile_location;
    }

    public function getProductOldPrice(): Money|false
    {
        return false;
    }

    public function getStockPriority(): ?bool
    {
        return $this->stock_priority === true;
    }

    public function getProductOfferConst(): ?string
    {
        return $this->product_offer_const;
    }

    public function getProductVariationConst(): ?string
    {
        return $this->product_variation_const;
    }

    public function getProductModificationConst(): ?string
    {
        return $this->product_modification_const;
    }

    public function getDateModify(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->modify);
    }
}