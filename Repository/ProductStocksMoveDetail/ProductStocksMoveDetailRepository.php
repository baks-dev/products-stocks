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

namespace BaksDev\Products\Stocks\Repository\ProductStocksMoveDetail;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Product\Entity\Category\ProductCategory as ProductCategoryRoot;
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
use BaksDev\Products\Stocks\Entity\Stock\Event\Print\ProductStockPrint;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\AllProductStocksMove\AllProductStocksMoveResult;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class ProductStocksMoveDetailRepository implements ProductStocksMoveDetailInterface
{

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorageInterface,
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function forProfile(UserProfileUid|UserProfile|false|null $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function find(ProductStockUid $id): AllProductStocksMoveResult|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('event.main AS id')
            ->addSelect('event.id AS event')
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->addSelect('event.fixed')
            ->from(ProductStockEvent::class, 'event')
            ->andWhere('event.status = :status ')
            ->andWhere('event.main = :main')
            ->setParameter(
                'status',
                ProductStockStatusMoving::class,
                ProductStockStatus::TYPE,
            )
            ->setParameter('main', $id, ProductStockUid::TYPE);


        $dbal
            ->addSelect('invariable.profile AS user_profile_id')
            ->addSelect('invariable.number')
            ->join(
                'event',
                ProductStocksInvariable::class,
                'invariable',
                'invariable.event = event.id',
            );


        $dbal->setParameter(
            key: 'profile',
            value: $this->profile instanceof UserProfileUid ? $this->profile : $this->UserProfileTokenStorageInterface->getProfile(),
            type: UserProfileUid::TYPE,
        );


        $dbal
            ->addSelect('stock.event AS is_warehouse')
            ->join(
                'event',
                ProductStock::class,
                'stock',
                'stock.event = event.id',
            );


        // ProductStockModify
        $dbal
            ->addSelect('modify.mod_date')
            ->leftJoin(
                'event',
                ProductStockModify::class,
                'modify',
                'modify.event = event.id',
            );


        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->leftJoin(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = event.id',
            );


        // Product
        $dbal
            ->addSelect('product.id as product_id')
            ->addSelect('product.event as product_event')
            ->leftJoin(
                'stock_product',
                Product::class,
                'product',
                'product.id = stock_product.product',
            );

        // Product Event
        $dbal->leftJoin(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event',
        );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product_event',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id',
            );

        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local',
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
                'product_offer.event = product_event.id AND product_offer.const = stock_product.offer',
            );


        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );


        // Множественные варианты торгового предложения

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation',
            );


        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_variation.category_variation',
            );

        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification',
            );


        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
            );

        // Артикул продукта

        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');

        // Фото продукта

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            '
                product_modification_image.modification = product_modification.id AND
                product_modification_image.root = true
			');

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            '
                product_variation_image.variation = product_variation.id AND
                product_variation_image.root = true
			');

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            '
                product_variation_image.name IS NULL AND
                product_offer_images.offer = product_offer.id AND
                product_offer_images.root = true
			');

        $dbal->leftJoin(
            'product_offer',
            ProductPhoto::class,
            'product_photo',
            '
                product_offer_images.name IS NULL AND
                product_photo.event = product_event.id AND
                product_photo.root = true
			');

        $dbal->addSelect(
            "
			CASE
			 
			 WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		",
        );

        // Расширение файла
        $dbal->addSelect(
            "
			CASE
		
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN  product_modification_image.ext
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.ext
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.ext
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.ext
				
			   ELSE NULL
			   
			END AS product_image_ext
		",
        );

        // Флаг загрузки файла CDN
        $dbal->addSelect(
            '
			CASE
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.cdn
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.cdn
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.cdn
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.cdn
			   
			   ELSE NULL
			END AS product_image_cdn
		',
        );

        // Категория
        $dbal->leftJoin(
            'product_event',
            ProductCategoryRoot::class,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true',
        );


        $dbal->leftJoin(
            'product_event_category',
            CategoryProduct::class,
            'category',
            'category.id = product_event_category.category',
        );


        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local',
            );


        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event',
            );

        /* Print */
        $dbal
            ->addSelect('product_stock_print.value as printed')
            ->leftJoin(
                'event',
                ProductStockPrint::class,
                'product_stock_print',
                'product_stock_print.event = event.id',
            );


        /** Целевой склад */

        // UserProfile
        $dbal->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = invariable.profile',
            );

        // Info
        $dbal->leftJoin(
            'event',
            UserProfileInfo::class,
            'users_profile_info',
            'users_profile_info.profile = users_profile.id',
        );


        // Personal
        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event',
            );


        // Пункт назначения перемещения

        $dbal->leftJoin(
            'event',
            ProductStockMove::class,
            'move',
            'move.event = event.id AND move.ord IS NULL',
        );

        $dbal->leftJoin(
            'move',
            UserProfile::class,
            'users_profile_destination',
            'users_profile_destination.id = move.destination',
        );


        // Personal
        $dbal
            ->addSelect('users_profile_personal_destination.username AS users_profile_destination')
            ->leftJoin(
                'users_profile_destination',
                UserProfilePersonal::class,
                'users_profile_personal_destination',
                'users_profile_personal_destination.event = users_profile_destination.event',
            );


        /** Место хранения на складе и количество */

        /* Получаем наличие на указанном складе */
        $dbal
            ->addSelect('SUM(total.total) AS stock_total')
            ->addSelect("STRING_AGG(CONCAT(total.storage, ': [', total.total, ']'), ', ' ORDER BY total.total) AS stock_storage")
            ->leftJoin(
                'stock_product',
                ProductStockTotal::class,
                'total',
                '
                    total.profile = :profile AND
                    total.product = stock_product.product AND 
                    (total.offer IS NULL OR total.offer = stock_product.offer) AND 
                    (total.variation IS NULL OR total.variation = stock_product.variation) AND 
                    (total.modification IS NULL OR total.modification = stock_product.modification) AND
                    total.total > 0
            ');


        $dbal->allGroupByExclude();

        return $dbal->fetchHydrate(AllProductStocksMoveResult::class);

    }
}