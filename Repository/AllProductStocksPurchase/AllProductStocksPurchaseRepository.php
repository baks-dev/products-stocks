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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPurchase;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllProductStocksPurchaseRepository implements AllProductStocksPurchaseInterface
{
    private UserProfileUid|false $profile = false;

    private SearchDTO|false $search = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function profile(UserProfile|UserProfileUid|string $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function findPaginator(): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // Stock

        // ProductStock
        $dbal
            ->select('stock.id')
            ->addSelect('stock.event')
            ->from(ProductStock::class, 'stock');

        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->join(
                'stock',
                ProductStockEvent::class,
                'event',
                'event.id = stock.event AND event.status = :status'
            )
            ->setParameter(
                'status',
                ProductStockStatusPurchase::class,
                ProductStockStatus::TYPE
            );

        $dbal
            ->addSelect('invariable.number')
            ->leftJoin(
                'stock',
                ProductStocksInvariable::class,
                'invariable',
                'invariable.main = stock.id'.($this->profile ? ' AND invariable.profile = :profile' : '')
            );

        if($this->profile)
        {
            $dbal->setParameter('profile', $this->profile, UserProfileUid::TYPE);
        }

        // ProductStockModify
        $dbal
            ->addSelect('modify.mod_date')
            ->leftJoin(
                'event',
                ProductStockModify::class,
                'modify',
                'modify.event = stock.event'
            );

        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->leftJoin(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            );



        // Product
        $dbal
            ->addSelect('product.id as product_id')
            ->addSelect('product.event as product_event')
            ->join(
                'stock_product',
                Product::class,
                'product',
                'product.id = stock_product.product'
            );

        // Product Event
        $dbal->leftJoin(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event'
        );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product_event',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id'
            );

        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        // Торговое предложение

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product_event.id AND product_offer.const = stock_product.offer'
            );

        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        // Множественные варианты торгового предложения

        $dbal
            ->addSelect('product_offer_variation.id as product_variation_uid')
            ->addSelect('product_offer_variation.value as product_variation_value')
            ->addSelect('product_offer_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_offer_variation',
                'product_offer_variation.offer = product_offer.id AND product_offer_variation.const = stock_product.variation'
            );

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_offer_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_offer_variation.category_variation'
            );

        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('product_offer_modification.id as product_modification_uid')
            ->addSelect('product_offer_modification.value as product_modification_value')
            ->addSelect('product_offer_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_offer_variation',
                ProductModification::class,
                'product_offer_modification',
                'product_offer_modification.variation = product_offer_variation.id AND product_offer_modification.const = stock_product.modification'
            );

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_offer_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_offer_modification.category_modification'
            );

        // Артикул продукта

        $dbal->addSelect(
            '
			CASE
			   WHEN product_offer_modification.article IS NOT NULL THEN product_offer_modification.article
			   WHEN product_offer_variation.article IS NOT NULL THEN product_offer_variation.article
			   WHEN product_offer.article IS NOT NULL THEN product_offer.article
			   WHEN product_info.article IS NOT NULL THEN product_info.article
			   ELSE NULL
			END AS product_article
		'
        );

        // Фото продукта

        $dbal->leftJoin(
            'product_offer_modification',
            ProductModificationImage::class,
            'product_offer_modification_image',
            '
			product_offer_modification_image.modification = product_offer_modification.id AND
			product_offer_modification_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_offer_variation_image',
            '
			product_offer_variation_image.variation = product_offer_variation.id AND
			product_offer_variation_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            '
			product_offer_variation_image.name IS NULL AND
			product_offer_images.offer = product_offer.id AND
			product_offer_images.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductPhoto::class,
            'product_photo',
            '
			product_offer_images.name IS NULL AND
			product_photo.event = product_event.id AND
			product_photo.root = true
			'
        );

        $dbal->addSelect(
            "
			CASE
			 
			 WHEN product_offer_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_offer_modification_image.name)
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_offer_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        // Расширение файла
        $dbal->addSelect(
            "
			CASE
			
			    WHEN product_offer_modification_image.name IS NOT NULL THEN  product_offer_modification_image.ext
			   WHEN product_offer_variation_image.name IS NOT NULL THEN product_offer_variation_image.ext
			   WHEN product_offer_images.name IS NOT NULL THEN product_offer_images.ext
			   WHEN product_photo.name IS NOT NULL THEN product_photo.ext
				
			   ELSE NULL
			   
			END AS product_image_ext
		"
        );

        // Флаг загрузки файла CDN
        $dbal->addSelect(
            '
			CASE
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					product_offer_variation_image.cdn
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.cdn
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.cdn
			   ELSE NULL
			END AS product_image_cdn
		'
        );

        // Категория
        $dbal->leftJoin(
            'product_event',
            ProductCategory::class,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        $dbal->leftJoin(
            'product_event_category',
            CategoryProduct::class,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );

        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event'
            );

        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $dbal
            ->addSelect('users_profile.event as users_profile_event')
            ->join(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = invariable.profile'
            );

        // Info
        $dbal->leftJoin(
            'event',
            UserProfileInfo::class,
            'users_profile_info',
            'users_profile_info.profile = event.profile'
        );

        // Event
        $dbal->leftJoin(
            'users_profile',
            UserProfileEvent::class,
            'users_profile_event',
            'users_profile_event.id = users_profile.event'
        );

        // Personal
        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile_event',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile_event.id'
            );

        // Avatar

        $dbal
            ->addSelect("CONCAT ('/upload/".$dbal->table(UserProfileAvatar::class)."' , '/', users_profile_avatar.name) AS users_profile_avatar")
            ->addSelect("CASE WHEN users_profile_avatar.cdn THEN  CONCAT ( 'small.', users_profile_avatar.ext) ELSE users_profile_avatar.ext END AS users_profile_avatar_ext")
            ->addSelect('users_profile_avatar.cdn AS users_profile_avatar_cdn')
            ->leftJoin(
                'users_profile_event',
                UserProfileAvatar::class,
                'users_profile_avatar',
                'users_profile_avatar.event = users_profile_event.id'
            );

        // Группа
        $dbal->addSelect('NULL AS group_name'); // Название группы

        // Поиск
        if($this->search && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('event.number');
        }

        $dbal->orderBy('modify.mod_date');

        return $this->paginator->fetchAllAssociative($dbal);

    }
}
