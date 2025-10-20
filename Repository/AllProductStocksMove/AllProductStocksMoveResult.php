<?php
/*
<<<<<<< HEAD
 * Copyright 2025.  Baks.dev <admin@baks.dev>
 *
=======
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
>>>>>>> refs/remotes/origin/master
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
<<<<<<< HEAD
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
=======
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
>>>>>>> refs/remotes/origin/master
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
<<<<<<< HEAD
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
=======
use BaksDev\Users\Profile\UserProfile\Type\Event\UserProfileEventUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

/** @see AllProductStocksMoveResult */
final readonly class AllProductStocksMoveResult
{
    public function __construct(
        private string $id, // "0199caa9-8030-75ea-98db-649611a7abde" - уникальный идентификатор события
        private string $event, // "0199caa9-8030-75ea-98db-64961210f3f0" - идентификатор события
        private ?string $comment, // null - комментарий к событию
        private string $status, // "moving" - статус перемещения
        private ?string $fixed, // null - фиксированное значение
        private string $user_profile_id, // "0197f935-a3c8-701a-9dfb-5e6f951e4c6e" - идентификатор профиля пользователя
        private string $number, // "176.004.173.008" - номер
        private string $is_warehouse, // "0199caa9-8030-75ea-98db-64961210f3f0" - идентификатор склада
        private string $mod_date, // "2025-10-09 23:28:50" - дата модификации


        private string $product_id, // "01876b34-ed23-7c18-ba48-9071e8646a08" - идентификатор товара
        private string $product_event, // "0199e47a-1baf-7570-893d-30ac7dac43ce" - событие товара
        private string $product_url, // "triangle_advantex_tc101" - URL товара
        private string $product_name, // "Triangle AdvanteX TC101" - название товара

        private ?string $product_offer_uid, // "0199e47a-1bb3-7b05-8532-af323e8e703b" - UID предложения товара
        private ?string $product_offer_value, // "15" - значение предложения
        private ?string $product_offer_postfix, // null - постфикс предложения
        private ?string $product_offer_reference, // "tire_radius_field" - ссылка на предложение

        private ?string $product_variation_uid, // "0199e47a-1bb4-732b-9cd3-6ec9ba307690" - UID вариации товара
        private ?string $product_variation_value, // "185" - значение вариации
        private ?string $product_variation_postfix, // null - постфикс вариации
        private ?string $product_variation_reference, // "tire_width_field" - ссылка на вариацию

        private ?string $product_modification_uid, // "0199e47a-1bb4-732b-9cd3-6ec9ba4e9012" - UID модификации товара
        private ?string $product_modification_value, // "55" - значение модификации
        private ?string $product_modification_postfix, // "82V" - постфикс модификации
        private ?string $product_modification_reference, // "tire_profile_field" - ссылка на модификацию

        private ?string $product_article, // "TC101-15-185-55-82V" - артикул товара
        private ?string $product_image, // "/upload/product_photo/6c0003c59af4454b3e3697ddec435f3f" - изображение товара
        private ?string $product_image_ext, // "webp" - расширение изображения
        private ?bool $product_image_cdn, // true - использование CDN для изображения

        private string $category_name, // "Triangle" - название категории
        private string $category_url, // "triangle" - URL категории
        private string $users_profile_event, // "0199a537-c513-7892-9b17-2dc33bda7c71" - событие профиля пользователя

        private string $users_profile_username, // "ООО "Рога и копыта"" - имя пользователя профиля
        private string $users_profile_destination, // "admin" - назначение профиля пользователя

        private string $product_stock_id, // "0199caa9-8030-75ea-98db-6496128fbb2e" - идентификатор запаса товара

        private int $total, // 1 - количество в заявке
        private ?int $stock_total, // 1156 - общий запас
        private ?string $stock_storage // "10: [78], : [1078]" - складское хранение
>>>>>>> refs/remotes/origin/master
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

<<<<<<< HEAD
    public function getStatus(): ProductStockStatus
    {
        return new ProductStockStatus($this->status);
    }

    public function getFixed(): ?string
    {
        return $this->fixed;
=======
    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFixed(): ?UserProfileUid
    {
        return $this->fixed ? new UserProfileUid($this->fixed) : null;
>>>>>>> refs/remotes/origin/master
    }

    public function getUserProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->user_profile_id);
    }

    public function getNumber(): string
    {
        return $this->number;
    }

<<<<<<< HEAD
    public function getIsWarehouse(): ?string
    {
        return $this->is_warehouse;
=======
    public function getIsWarehouse(): ProductStockEventUid
    {
        return new ProductStockEventUid($this->is_warehouse);
>>>>>>> refs/remotes/origin/master
    }

    public function getModDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->mod_date);
    }

<<<<<<< HEAD
    public function getProductStockId(): ?ProductStockCollectionUid
    {
        return false === empty($this->product_stock_id) ? new ProductStockCollectionUid($this->product_stock_id) : null;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getProductId(): ?ProductUid
=======
    public function getProductStockId(): ProductStockCollectionUid
    {
        return new ProductStockCollectionUid($this->product_stock_id);
    }

    public function getProductId(): ProductUid
>>>>>>> refs/remotes/origin/master
    {
        return new ProductUid($this->product_id);
    }

<<<<<<< HEAD
    public function getProductEvent(): ?ProductEventUid
=======
    public function getProductEvent(): ProductEventUid
>>>>>>> refs/remotes/origin/master
    {
        return new ProductEventUid($this->product_event);
    }

<<<<<<< HEAD
    public function getProductUrl(): ?string
=======
    public function getProductUrl(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->product_url;
    }

<<<<<<< HEAD
    public function getProductName(): ?string
=======
    public function getProductName(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->product_name;
    }

<<<<<<< HEAD
    public function getProductOfferUid(): ?ProductOfferUid
    {
        return false === empty($this->product_offer_uid) ? new ProductOfferUid($this->product_offer_uid) : null;
=======
    /** Offer */

    public function getProductOfferUid(): ?ProductOfferUid
    {
        return $this->product_offer_uid ? new ProductOfferUid($this->product_offer_uid) : null;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductOfferValue(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_offer_value) ? $this->product_offer_value : null;
=======
        return $this->product_offer_value;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductOfferPostfix(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_offer_postfix) ? $this->product_offer_postfix : null;
=======
        return $this->product_offer_postfix;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductOfferReference(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_offer_reference) ? $this->product_offer_reference : null;
    }


    /** Variation */
    public function getProductVariationUid(): ?ProductVariationUid
    {
        return false === empty($this->product_variation_uid) ? new ProductVariationUid($this->product_variation_uid) : null;
=======
        return $this->product_offer_reference;
    }

    /** Variation */

    public function getProductVariationUid(): ?ProductVariationUid
    {
        return $this->product_variation_uid ? new ProductVariationUid($this->product_variation_uid) : null;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductVariationValue(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_variation_value) ? $this->product_variation_value : null;
=======
        return $this->product_variation_value;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductVariationPostfix(): ?string
    {
<<<<<<< HEAD
        return $this->product_variation_postfix ?: null;
=======
        return $this->product_variation_postfix;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductVariationReference(): ?string
    {
<<<<<<< HEAD
        return $this->product_variation_reference ?: null;
    }


    /** Modification */
    public function getProductModificationUid(): ?ProductModificationUid
    {
        return false === empty($this->product_modification_uid) ? new ProductModificationUid($this->product_modification_uid) : null;
=======
        return $this->product_variation_reference;
    }

    /** Modification */

    public function getProductModificationUid(): ?ProductModificationUid
    {
        return $this->product_modification_uid ? new ProductModificationUid($this->product_modification_uid) : null;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductModificationValue(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_modification_value) ? $this->product_modification_value : null;
=======
        return $this->product_modification_value;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductModificationPostfix(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_modification_postfix) ? $this->product_modification_postfix : null;
=======
        return $this->product_modification_postfix;
>>>>>>> refs/remotes/origin/master
    }

    public function getProductModificationReference(): ?string
    {
<<<<<<< HEAD
        return false === empty($this->product_modification_reference) ? $this->product_modification_reference : null;
    }

    public function getProductArticle(): ?string
=======
        return $this->product_modification_reference;
    }

    public function getProductArticle(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->product_article;
    }

<<<<<<< HEAD
    public function getProductImage(): ?string
=======
    public function getProductImage(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->product_image;
    }

<<<<<<< HEAD
    public function getProductImageExt(): ?string
=======
    public function getProductImageExt(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->product_image_ext;
    }

<<<<<<< HEAD
    public function getProductImageCdn(): bool
    {
        return true === $this->product_image_cdn;
    }

    public function getCategoryName(): ?string
=======
    public function isProductImageCdn(): bool
    {
        return $this->product_image_cdn === true;
    }

    public function getCategoryName(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->category_name;
    }

<<<<<<< HEAD
    public function getCategoryUrl(): ?string
=======
    public function getCategoryUrl(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->category_url;
    }

<<<<<<< HEAD
    public function getUsersProfileEvent(): ?UserProfileEventUid
    {
        return false === empty($this->users_profile_event) ? new UserProfileEventUid($this->users_profile_event) : null;
    }

    public function getUsersProfileUsername(): ?string
=======
    public function getUsersProfileEvent(): UserProfileEventUid
    {
        return new UserProfileEventUid($this->users_profile_event);
    }

    public function getUsersProfileUsername(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->users_profile_username;
    }

<<<<<<< HEAD
    public function getUsersProfileDestination(): ?string
=======
    public function getUsersProfileDestination(): string
>>>>>>> refs/remotes/origin/master
    {
        return $this->users_profile_destination;
    }

<<<<<<< HEAD
    public function getStockTotal(): ?int
    {
        return $this->stock_total;
    }

=======
    /** Количество в заявке для перемещения */
    public function getTotal(): int
    {
        return $this->total;
    }

    /** Доступное количество на складе */
    public function getStockTotal(): int
    {
        return $this->stock_total ?: 0;
    }

    /** Место хранения */
>>>>>>> refs/remotes/origin/master
    public function getStockStorage(): ?string
    {
        return $this->stock_storage;
    }
}