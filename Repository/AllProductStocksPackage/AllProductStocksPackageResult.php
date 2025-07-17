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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\Profile\UserProfile\Type\Event\UserProfileEventUid;

final readonly class AllProductStocksPackageResult
{
    public function __construct(
        private string $id,
        private string $event,
        private ?string $number,
        private ?string $comment,
        private string $status,
        private ?string $date_package,
        private ?string $mod_date,
        private string $product_stock_id,
        private int $total,
        private ?int $stock_total,
        private ?string $stock_storage,
        private ?string $order_id,
        private ?bool $order_danger,
        private ?string $order_comment,
        private string $delivery_date,
        private ?string $delivery_name,
        private ?string $product_id,
        private ?string $product_event,
        private ?string $product_url,
        private ?string $product_name,
        private ?string $product_offer_uid,
        private ?string $product_offer_value,
        private ?string $product_offer_postfix,
        private ?string $product_offer_reference,
        private ?string $product_offer_name,
        private ?string $product_variation_uid,
        private ?string $product_variation_value,
        private ?string $product_variation_postfix,
        private ?string $product_variation_reference,
        private ?string $product_variation_name,
        private ?string $product_modification_uid,
        private ?string $product_modification_value,
        private ?string $product_modification_postfix,
        private ?string $product_modification_reference,
        private ?string $product_modification_name,
        private ?string $product_article,
        private ?string $product_image,
        private ?string $product_image_ext,
        private ?bool $product_image_cdn,
        private ?string $category_name,
        private ?string $category_url,
        private ?string $users_profile_event,
        private ?string $users_profile_username,
        private bool $products_move,
        private ?string $users_profile_destination,
        private ?string $users_profile_move,
        private ?bool $printed,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getEvent(): ProductStockEventUid
    {
        return new ProductStockEventUid($this->event);
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDatePackage(): ?string
    {
        return $this->date_package;
    }

    public function getModDate(): ?string
    {
        return $this->mod_date;
    }

    public function getProductStockId(): ProductStockUid
    {
        return new ProductStockUid($this->product_stock_id);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getStockTotal(): ?int
    {
        return $this->stock_total;
    }

    public function getStockStorage(): ?string
    {
        return $this->stock_storage;
    }

    public function getOrderId(): ?OrderUid
    {
        return empty($this->order_id) ? null : new OrderUid($this->order_id);
    }

    public function isOrderDanger(): bool
    {
        return $this->order_danger === true;
    }

    public function getOrderComment(): ?string
    {
        return $this->order_comment;
    }

    public function getDeliveryDate(): string
    {
        return $this->delivery_date;
    }

    public function getDeliveryName(): ?string
    {
        return $this->delivery_name;
    }

    public function getProductId(): ?ProductUid
    {
        return empty($this->product_id) ? null : new ProductUid($this->product_id);
    }

    public function getProductEvent(): ?ProductEventUid
    {
        return empty($this->product_event) ? null : new ProductEventUid($this->product_event);
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
        return empty($this->product_offer_uid) ? null : new ProductOfferUid($this->product_offer_uid);
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }

    public function getProductOfferName(): ?string
    {
        return $this->product_offer_name;
    }

    public function getProductVariationUid(): ?ProductVariationUid
    {
        return empty($this->product_variation_uid) ? null : new ProductVariationUid($this->product_variation_uid);
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }

    public function getProductVariationName(): ?string
    {
        return $this->product_variation_name;
    }

    public function getProductModificationUid(): ?ProductModificationUid
    {
        return empty($this->product_modification_uid) ? null : new ProductModificationUid($this->product_modification_uid);
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }

    public function getProductModificationName(): ?string
    {
        return $this->product_modification_name;
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
        return $this->product_image_cdn === true;
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
        return empty($this->users_profile_event) ? null : new UserProfileEventUid($this->users_profile_event);
    }

    public function getUsersProfileUsername(): ?string
    {
        return $this->users_profile_username;
    }

    public function isProductsMove(): bool
    {
        return $this->products_move;
    }

    public function getUsersProfileDestination(): ?string
    {
        return $this->users_profile_destination;
    }

    public function getUsersProfileMove(): ?string
    {
        return $this->users_profile_move;
    }

    public function isPrinted(): bool
    {
        return $this->printed === true;
    }
}