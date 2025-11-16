<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\ProductStockInfo;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final readonly class ProductStockInfoResult
{
    public function __construct(
        //private string $stock_id,
        //private ?int $total,

        //private string $users_profile_id,

        private string $product_id,
        private string $product_name,
        private ?string $product_offer_uid,
        private ?string $product_offer_value,
        private ?string $product_offer_postfix,
        private ?string $product_offer_reference,
        private ?string $product_offer_const,
        private ?string $product_variation_uid,
        private ?string $product_variation_value,
        private ?string $product_variation_postfix,
        private ?string $product_variation_reference,
        private ?string $product_variation_const,
        private ?string $product_modification_uid,
        private ?string $product_modification_value,
        private ?string $product_modification_postfix,
        private ?string $product_modification_reference,
        private ?string $product_modification_const,
        private ?string $product_article,

        //private string $users_profile_username,

        private string $max_stock_profile,
        private int $max_stock_total,
        private string $max_stock_username,
        //private string $min_stock_profile,
        private int $min_stock_total,
    ) {}

    public function getStockId(): ProductStockTotalUid
    {
        return new ProductStockTotalUid($this->stock_id);
    }

    public function getUsersProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->users_profile_id);
    }

    /** Данные по товару */
    public function getProductId(): ProductUid
    {
        return new ProductUid($this->product_id);
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

    public function getUsersProfileUsername(): string
    {
        return $this->users_profile_username;
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

    /** Данные по максимальному и мниимальному кол-во */
    public function getMaxStockProfile(): UserProfileUid
    {
        return new UserProfileUid($this->max_stock_profile);
    }

    public function getMaxStockTotal(): int
    {
        return $this->max_stock_total;
    }

    public function getMaxStockUsername(): string
    {
        return $this->max_stock_username;
    }

    public function getMinStockProfile(): UserProfileUid
    {
        return new UserProfileUid($this->min_stock_profile);
    }

    public function getMinStockTotal(): ?int
    {
        return $this->min_stock_total;
    }

}