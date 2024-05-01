<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPickup;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\DeliveryTransport\BaksDevDeliveryTransportBundle;
use BaksDev\DeliveryTransport\Entity\Package\Stocks\DeliveryPackageStocks;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
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
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Entity\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Entity\Value\UserProfileValue;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllProductStocksPickupRepository implements AllProductStocksPickupInterface
{
    private PaginatorInterface $paginator;

    private DBALQueryBuilder $DBALQueryBuilder;

    private ?SearchDTO $search = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function findAll(UserProfileUid $profile): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class)->bindLocal();

        // ProductStock
        $dbal
            ->select('stock.id AS stock_id')
            ->addSelect('stock.event AS stock_event')
            ->from(ProductStock::class, 'stock');

        // ProductStockEvent
        // $dbal->addSelect('event.total');
        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->addSelect('event.number')
            ->join(
                'stock',
                ProductStockEvent::class,
                'event',
                'event.id = stock.event AND event.status = :status AND event.profile = :profile'
            )
            ->setParameter('profile', $profile, UserProfileUid::TYPE)
            ->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusExtradition()), ProductStockStatus::TYPE);


        // ProductStockModify
        $dbal->addSelect('modify.mod_date')
            ->join(
                'event',
                ProductStockModify::class,
                'modify',
                'modify.event = stock.event'
            );

        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->addSelect('stock_product.storage')
            ->join(
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
        $dbal->join(
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
            ->join(
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
					CONCAT ( '/upload/".ProductModificationImage::TABLE."' , '/', product_offer_modification_image.name)
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductVariationImage::TABLE."' , '/', product_offer_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductOfferImage::TABLE."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductPhoto::TABLE."' , '/', product_photo.name)
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


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $dbal
            ->addSelect('users_profile.event as users_profile_event')
            ->join(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = event.profile'
            );

        // Info
        $dbal->join(
            'event',
            UserProfileInfo::class,
            'users_profile_info',
            'users_profile_info.profile = event.profile'
        );

        // Event
        $dbal->join(
            'users_profile',
            UserProfileEvent::class,
            'users_profile_event',
            'users_profile_event.id = users_profile.event'
        );

        // Personal
        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->join(
                'users_profile_event',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile_event.id'
            );


        $dbal->join(
            'stock',
            ProductStockOrder::class,
            'product_stock_order',
            'product_stock_order.event = stock.event'
        );


        $dbal
            ->addSelect('ord.id AS order_id')
            ->join(
                'product_stock_order',
                Order::class,
                'ord',
                'ord.id = product_stock_order.ord'
            );

        $dbal
            ->addSelect('ord_client.profile AS client_profile_event')
            ->leftJoin(
                'ord',
                OrderUser::class,
                'ord_client',
                'ord_client.event = ord.event'
            );



        if($this->search->getQuery())
        {
            $dbal->join(
                'ord_client',
                UserProfileValue::class,
                'client_profile_value',
                'client_profile_value.event = ord_client.profile'
            );
        }

        if(class_exists(BaksDevDeliveryTransportBundle::class))
        {
            /** Проверяем, чтобы не было на доставке упаковки */
            $dbal->andWhereNotExists(DeliveryPackageStocks::class, 'tmp', 'tmp.stock = stock.id');
        }

        // Поиск
        if($this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('event.number')
                ->addSearchLike('client_profile_value.value');
        }

        $dbal->orderBy('modify.mod_date', 'DESC');

        return $this->paginator->fetchAllAssociative($dbal);
    }
}
