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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksMove;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Product\ProductStockCollectionUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Event\UserProfileEventUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;

final readonly class AllProductStocksMoveResult
{
    public function __construct(
        private string $id,
        private string $event,
        private ?string $comment,
        private string $status,
        private ?string $fixed,
        private string $user_profile_id,
        private string $number,
        private ?string $is_warehouse,
        private string $mod_date,
        private ?string $product_stock_id,
        private ?int $total,
        private ?string $product_id,
        private ?string $product_event,
        private ?string $product_url,
        private ?string $product_name,
        private ?string $product_offer_uid,
        private ?string $product_offer_value,
        private ?string $product_offer_postfix,
        private ?string $product_offer_reference,
        private ?string $product_variation_uid,
        private ?string $product_variation_value,
        private ?string $product_variation_postfix,
        private ?string $product_variation_reference,
        private ?string $product_modification_uid,
        private ?string $product_modification_value,
        private ?string $product_modification_postfix,
        private ?string $product_modification_reference,
        private ?string $product_article,
        private ?string $product_image,
        private ?string $product_image_ext,
        private ?bool $product_image_cdn,
        private ?string $category_name,
        private ?string $category_url,
        private ?string $users_profile_event,
        private ?string $users_profile_username,
        private ?string $users_profile_destination,
        private ?int $stock_total,
        private ?string $stock_storage
    ) {}

    public function getId(): ProductStockUid
    {
        return new ProductStockUid($this->id);
    }

    public function getEvent(): ProductStockEventUid
    {
        return new ProductStockEventUid($this->event);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): ProductStockStatus
    {
        return new ProductStockStatus($this->status);
    }

    public function getFixed(): ?string
    {
        return $this->fixed;
    }

    public function getUserProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->user_profile_id);
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getIsWarehouse(): ?string
    {
        return $this->is_warehouse;
    }

    public function getModDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->mod_date);
    }

    public function getProductStockId(): ?ProductStockCollectionUid
    {
        return false === empty($this->product_stock_id) ? new ProductStockCollectionUid($this->product_stock_id) : null;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getProductId(): ?ProductUid
    {
        return new ProductUid($this->product_id);
    }

    public function getProductEvent(): ?ProductEventUid
    {
        return new ProductEventUid($this->product_event);
    }

    public function getProductUrl(): ?string
    {
        return $this->product_url;
    }

    public function getProductName(): ?string
    {
        return $this->product_name;
    }

    public function getProductOfferUid(): ?ProductOfferUid
    {
        return false === empty($this->product_offer_uid) ? new ProductOfferUid($this->product_offer_uid) : null;
    }

    public function getProductOfferValue(): ?string
    {
        return false === empty($this->product_offer_value) ? $this->product_offer_value : null;
    }

    public function getProductOfferPostfix(): ?string
    {
        return false === empty($this->product_offer_postfix) ? $this->product_offer_postfix : null;
    }

    public function getProductOfferReference(): ?string
    {
        return false === empty($this->product_offer_reference) ? $this->product_offer_reference : null;
    }


    /** Variation */
    public function getProductVariationUid(): ?ProductVariationUid
    {
        return false === empty($this->product_variation_uid) ? new ProductVariationUid($this->product_variation_uid) : null;
    }

    public function getProductVariationValue(): ?string
    {
        return false === empty($this->product_variation_value) ? $this->product_variation_value : null;
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
        return false === empty($this->product_modification_uid) ? new ProductModificationUid($this->product_modification_uid) : null;
    }

    public function getProductModificationValue(): ?string
    {
        return false === empty($this->product_modification_value) ? $this->product_modification_value : null;
    }

    public function getProductModificationPostfix(): ?string
    {
        return false === empty($this->product_modification_postfix) ? $this->product_modification_postfix : null;
    }

    public function getProductModificationReference(): ?string
    {
        return false === empty($this->product_modification_reference) ? $this->product_modification_reference : null;
    }

    public function getProductArticle(): ?string
    {
        return $this->product_article;
    }

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
        return true === $this->product_image_cdn;
    }

    public function getCategoryName(): ?string
    {
        return $this->category_name;
    }

    public function getCategoryUrl(): ?string
    {
        return $this->category_url;
    }

    public function getUsersProfileEvent(): ?UserProfileEventUid
    {
        return false === empty($this->users_profile_event) ? new UserProfileEventUid($this->users_profile_event) : null;
    }

    public function getUsersProfileUsername(): ?string
    {
        return $this->users_profile_username;
    }

    public function getUsersProfileDestination(): ?string
    {
        return $this->users_profile_destination;
    }

    public function getStockTotal(): ?int
    {
        return $this->stock_total;
    }

    public function getStockStorage(): ?string
    {
        return $this->stock_storage;
    }
}