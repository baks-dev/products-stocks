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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Repository\AllProductStocksWarehouse;

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
use Symfony\Component\Validator\Constraints as Assert;

/** @see AllProductStocksWarehouseRepository */
final readonly class ProductStocksWarehouseResult
{

    public function __construct(
        private string $number, // Номер заказа
        private string $user_profile_id, // ID профиля пользователя
        private string $id, // ID записи
        private string $event, // ID события
        private ?string $comment, // Комментарий к заказу
        private string $status, // Статус заказа
        private string $mod_date, // Дата модификации
        private string $product_stock_id, // ID складского запаса
        private int $total, // Общее количество
        private ?string $users_profile_destination, // Грузоотправитель
        private string $product_id, // ID продукта
        private string $product_event, // ID события продукта
        private string $product_url, // URL продукта
        private string $product_name, // Название продукта
        private string $product_offer_uid, // UID предложения продукта
        private string $product_offer_value, // Значение предложения
        private ?string $product_offer_postfix, // Постфикс предложения
        private string $product_offer_reference, // Ссылка на предложение
        private string $product_variation_uid, // UID вариации продукта
        private string $product_variation_value, // Значение вариации
        private ?string $product_variation_postfix, // Постфикс вариации
        private string $product_variation_reference, // Ссылка на вариацию
        private string $product_modification_uid, // UID модификации продукта
        private string $product_modification_value, // Значение модификации
        private string $product_modification_postfix, // Постфикс модификации
        private string $product_modification_reference, // Ссылка на модификацию
        private string $product_article, // Артикул продукта

        private ?string $product_image, // Изображение продукта
        private ?string $product_image_ext, // Расширение изображения
        private ?bool $product_image_cdn, // Использование CDN для изображения

        private string $category_name, // Название категории
        private string $category_url, // URL категории

        private string $users_profile_event, // Событие профиля пользователей
        private string $users_profile_username, // Имя пользователя

        private ?string $users_profile_avatar, // Аватар пользователя
        private ?string $users_profile_avatar_ext, // Расширение аватара
        private ?bool $users_profile_avatar_cdn, // Использование CDN для аватара

        private ?string $group_name // Название группы
    ) {}

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getUserProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->user_profile_id);
    }

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

    public function getDateModify(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->mod_date);
    }

    public function getProductStockId(): ProductStockCollectionUid
    {
        return new ProductStockCollectionUid($this->product_stock_id);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getStorage(): ?string
    {
        return $this->storage;
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

    /** Variation */

    public function getProductVariationUid(): ?ProductVariationUid
    {
        return $this->product_variation_uid ? new ProductVariationUid($this->product_variation_uid) : null;
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

    /** Modification */

    public function getProductModificationUid(): ?ProductModificationUid
    {
        return $this->product_modification_uid ? new ProductModificationUid($this->product_modification_uid) : null;
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


    public function getProductArticle(): string
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

    public function isProductImageCdn(): bool
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

    public function getUsersProfileEvent(): ?UserProfileEventUid
    {
        return $this->users_profile_event ? new UserProfileEventUid($this->users_profile_event) : null;
    }

    public function getUsersProfileUsername(): string
    {
        return $this->users_profile_username;
    }

    public function getUsersProfileAvatar(): ?string
    {
        return $this->users_profile_avatar;
    }

    public function getUsersProfileAvatarExt(): ?string
    {
        return $this->users_profile_avatar_ext;
    }

    public function getUsersProfileAvatarCdn(): bool
    {
        return $this->users_profile_avatar_cdn === true;
    }

    public function getGroupName(): ?string
    {
        return $this->group_name;
    }

    public function getUsersProfileDestination(): ?string
    {
        return $this->users_profile_destination;
    }


}