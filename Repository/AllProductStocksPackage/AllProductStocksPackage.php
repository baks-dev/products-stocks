<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;

use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackage;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackageTransport;
use BaksDev\DeliveryTransport\Entity\Package\Stocks\DeliveryPackageStocks;
use BaksDev\DeliveryTransport\Type\ProductStockStatus\ProductStockStatusDivide;

use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;

use BaksDev\Products\Category\Entity\Offers\ProductCategoryOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\ProductCategoryOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\ProductCategoryModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\ProductCategoryModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\ProductCategoryVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\ProductCategoryVariationTrans;
use BaksDev\Products\Category\Entity\Trans\ProductCategoryTrans;
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
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Entity\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllProductStocksPackage implements AllProductStocksPackageInterface
{
    private PaginatorInterface $paginator;
    private DBALQueryBuilder $DBALQueryBuilder;

    private ?SearchDTO $search = null;

    private ?ProductsStocksFilterInterface $filter = null;

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

    public function filter(ProductsStocksFilterInterface $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /** Метод возвращает все заявки на упаковку заказов */
    public function fetchAllPackageAssociative(UserProfileUid $profile): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class);

        // ProductStock
        $dbal->select('stock.id');
        $dbal->addSelect('stock.event');

        $dbal->from(ProductStock::class, 'stock');

        // ProductStockEvent
        $dbal->addSelect('event.number');
        $dbal->addSelect('event.comment');
        $dbal->addSelect('event.status');

        $dbal->join(
            'stock',
            ProductStockEvent::class,
            'event',
            //'event.id = stock.event AND (event.status = :package OR event.status = :move) '.($profile ? ' AND event.profile = :profile' : '')
            'event.id = stock.event AND event.profile = :profile AND (event.status = :package OR event.status = :move) '
        );


        //if($profile)
        //{
        $dbal->setParameter('profile', $profile, UserProfileUid::TYPE);
        //}

        $dbal->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $dbal->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);

        // ProductStockModify
        $dbal->addSelect('modify.mod_date');
        $dbal->join(
            'stock',
            ProductStockModify::TABLE,
            'modify',
            'modify.event = stock.event'
        );

        return $this->paginator->fetchAllAssociative($dbal);
    }

    /**
     * Метод возвращает все заявки на упаковку заказов.
     */
    public function fetchAllProductStocksAssociative(UserProfileUid $profile): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // Stock

        // ProductStock
        $dbal->select('stock.id');
        $dbal->addSelect('stock.event');

        $dbal->from(ProductStock::TABLE, 'stock');

        // ProductStockEvent
        $dbal->addSelect('event.number');
        $dbal->addSelect('event.comment');
        $dbal->addSelect('event.status');

        $dbal->join(
            'stock',
            ProductStockEvent::TABLE,
            'event',
            '
            event.id = stock.event AND 
            event.profile = :profile AND  
            (
                event.status = :package OR 
                event.status = :move OR 
                event.status = :divide OR 
                event.status = :error
                
            )'
        );

        $dbal->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $dbal->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);
        $dbal->setParameter('error', new ProductStockStatus(new ProductStockStatus\ProductStockStatusError()), ProductStockStatus::TYPE);
        $dbal->setParameter('divide', new ProductStockStatus(new ProductStockStatusDivide()), ProductStockStatus::TYPE);

        $dbal->setParameter('profile', $profile, UserProfileUid::TYPE);


        /** Погрузка на доставку */

        if(defined(DeliveryPackage::class.'::TABLE'))
        {

            /** Подгружаем разделенные заказы */


            $existDeliveryPackage = $this->DBALQueryBuilder->builder();
            $existDeliveryPackage->select('1');
            $existDeliveryPackage->from(DeliveryPackage::TABLE, 'bGIuGLiNkf');
            $existDeliveryPackage->where('bGIuGLiNkf.event = delivery_stocks.event');

            $dbal->leftJoin(
                'stock',
                DeliveryPackageStocks::TABLE,
                'delivery_stocks',
                'delivery_stocks.stock = stock.id AND EXISTS('.$existDeliveryPackage->getSQL().')'
            );


            $dbal->leftJoin(
                'delivery_stocks',
                DeliveryPackage::TABLE,
                'delivery_package',
                'delivery_package.event = delivery_stocks.event'
            );

            $dbal->addSelect('delivery_transport.date_package');

            $dbal->leftJoin(
                'delivery_package',
                DeliveryPackageTransport::TABLE,
                'delivery_transport',
                'delivery_transport.package = delivery_package.id'
            );

            $dbal->addOrderBy('delivery_transport.date_package');

        }
        else
        {
            $dbal->addSelect('NULL AS date_package');
            $dbal->setParameter('divide', 'ntUIGnScMq');
        }


        $dbal
            ->addSelect('modify.mod_date')
            ->join(
                'stock',
                ProductStockModify::TABLE,
                'modify',
                'modify.event = stock.event'
            );


        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->join(
                'event',
                ProductStockProduct::TABLE,
                'stock_product',
                'stock_product.event = stock.event'
            );


        /* Получаем наличие на указанном складе */
        $dbal
            ->addSelect('total.total AS stock_total')
            ->addSelect('total.storage AS stock_storage')
            ->leftJoin(
                'stock_product',
                ProductStockTotal::TABLE,
                'total',
                '
                total.profile = event.profile AND
                total.product = stock_product.product AND 
                (total.offer IS NULL OR total.offer = stock_product.offer) AND 
                (total.variation IS NULL OR total.variation = stock_product.variation) AND 
                (total.modification IS NULL OR total.modification = stock_product.modification)
            '
            );


        $dbal->join(
            'stock',
            ProductStockOrder::TABLE,
            'ord',
            'ord.event = stock.event'
        );


        $dbal->leftJoin(
            'ord',
            Order::TABLE,
            'orders',
            'orders.id = ord.ord'
        );

        $dbal->leftJoin(
            'orders',
            OrderUser::TABLE,
            'order_user',
            'order_user.event = orders.event'
        );

        $dbal->leftJoin(
            'order_user',
            OrderDelivery::TABLE,
            'order_delivery',
            'order_delivery.usr = order_user.id'
        );

        $dbal->leftJoin(
            'order_delivery',
            DeliveryEvent::TABLE,
            'delivery_event',
            'delivery_event.id = order_delivery.event AND delivery_event.main = order_delivery.delivery'
        );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'delivery_event',
                DeliveryTrans::TABLE,
                'delivery_trans',
                'delivery_trans.event = delivery_event.id AND delivery_trans.local = :local'
            );


        // Product
        $dbal
            ->addSelect('product.id as product_id')
            ->addSelect('product.event as product_event')
            ->join(
                'stock_product',
                Product::TABLE,
                'product',
                'product.id = stock_product.product'
            );

        // Product Event
        $dbal->join(
            'product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = product.event'
        );

        // Product Info
        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product_event',
                ProductInfo::TABLE,
                'product_info',
                'product_info.product = product.id'
            );

        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product_event',
                ProductTrans::TABLE,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /*
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::TABLE,
                'product_offer',
                'product_offer.event = product_event.id AND product_offer.const = stock_product.offer'
            );

        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                ProductCategoryOffers::TABLE,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        $dbal
            ->addSelect('category_offer_trans.name as product_offer_name')
            ->leftJoin(
                'category_offer',
                ProductCategoryOffersTrans::TABLE,
                'category_offer_trans',
                'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
            );

        /*
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_offer_variation.id as product_variation_uid')
            ->addSelect('product_offer_variation.value as product_variation_value')
            ->addSelect('product_offer_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::TABLE,
                'product_offer_variation',
                'product_offer_variation.offer = product_offer.id AND product_offer_variation.const = stock_product.variation'
            );

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_offer_variation',
                ProductCategoryVariation::TABLE,
                'category_offer_variation',
                'category_offer_variation.id = product_offer_variation.category_variation'
            );

        $dbal
            ->addSelect('category_offer_variation_trans.name as product_variation_name')
            ->leftJoin(
                'category_offer_variation',
                ProductCategoryVariationTrans::TABLE,
                'category_offer_variation_trans',
                'category_offer_variation_trans.variation = category_offer_variation.id AND category_offer_variation_trans.local = :local'
            );

        /*
         * Модификация множественного варианта торгового предложения
         */

        $dbal
            ->addSelect('product_offer_modification.id as product_modification_uid')
            ->addSelect('product_offer_modification.value as product_modification_value')
            ->addSelect('product_offer_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_offer_variation',
                ProductModification::TABLE,
                'product_offer_modification',
                'product_offer_modification.variation = product_offer_variation.id AND product_offer_modification.const = stock_product.modification'
            );

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_offer_modification',
                ProductCategoryModification::TABLE,
                'category_offer_modification',
                'category_offer_modification.id = product_offer_modification.category_modification'
            );

        $dbal
            ->addSelect('category_offer_modification_trans.name as product_modification_name')
            ->leftJoin(
                'category_offer_modification',
                ProductCategoryModificationTrans::TABLE,
                'category_offer_modification_trans',
                'category_offer_modification_trans.modification = category_offer_modification.id AND category_offer_modification_trans.local = :local'
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
            ProductModificationImage::TABLE,
            'product_offer_modification_image',
            '
			product_offer_modification_image.modification = product_offer_modification.id AND
			product_offer_modification_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::TABLE,
            'product_offer_variation_image',
            '
			product_offer_variation_image.variation = product_offer_variation.id AND
			product_offer_variation_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::TABLE,
            'product_offer_images',
            '
			product_offer_variation_image.name IS NULL AND
			product_offer_images.offer = product_offer.id AND
			product_offer_images.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductPhoto::TABLE,
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
            ProductCategory::TABLE,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        $dbal->leftJoin(
            'product_event_category',
            \BaksDev\Products\Category\Entity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal->addSelect('category_trans.name AS category_name');
        $dbal->leftJoin(
            'category',
            ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $dbal->addSelect('users_profile.event as users_profile_event');
        $dbal->join(
            'event',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = event.profile'
        );

        // Info
        $dbal->join(
            'event',
            UserProfileInfo::TABLE,
            'users_profile_info',
            'users_profile_info.profile = event.profile'
        );

        // Event
        $dbal->join(
            'users_profile',
            UserProfileEvent::TABLE,
            'users_profile_event',
            'users_profile_event.id = users_profile.event'
        );

        // Personal
        $dbal->addSelect('users_profile_personal.username AS users_profile_username');

        $dbal->join(
            'users_profile_event',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile_event.id'
        );

        //        // Avatar
        //
        //        $dbal->addSelect("CONCAT ( '/upload/".UserProfileEntity\Avatar\UserProfileAvatar::TABLE."' , '/', users_profile_avatar.name) AS users_profile_avatar");
        //        $dbal->addSelect("CASE WHEN users_profile_avatar.cdn THEN  CONCAT ( 'small.', users_profile_avatar.ext) ELSE users_profile_avatar.ext END AS users_profile_avatar_ext");
        //        $dbal->addSelect('users_profile_avatar.cdn AS users_profile_avatar_cdn');

        $dbal->leftJoin(
            'users_profile_event',
            UserProfileAvatar::TABLE,
            'users_profile_avatar',
            'users_profile_avatar.event = users_profile_event.id'
        );

        // Группа


        //$dbal->addSelect('NULL AS group_name'); // Название группы

        /** Проверка перемещения по заказу */
        $dbalExist = $this->DBALQueryBuilder->builder();

        $dbalExist->select('1');
        $dbalExist->from(ProductStockMove::TABLE, 'exist_move');
        $dbalExist->where('exist_move.ord = ord.ord ');

        $dbalExist->join(
            'exist_move',
            ProductStockEvent::TABLE,
            'exist_move_event',
            'exist_move_event.id = exist_move.event AND  (
                exist_move_event.status != :incoming
            )'
        );


        $dbalExist->join(
            'exist_move_event',
            ProductStock::TABLE,
            'exist_move_stock',
            'exist_move_stock.event = exist_move_event.id'
        );

        $dbal->addSelect(sprintf('EXISTS(%s) AS products_move', $dbalExist->getSQL()));
        $dbal->setParameter('incoming', new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()), ProductStockStatus::TYPE);


        /** Пункт назначения при перемещении */

        $dbal->leftJoin(
            'event',
            ProductStockMove::TABLE,
            'move_stock',
            'move_stock.event = event.id'
        );


        // UserProfile
        $dbal->leftJoin(
            'move_stock',
            UserProfile::TABLE,
            'users_profile_move',
            'users_profile_move.id = move_stock.destination'
        );

        $dbal
            ->addSelect('users_profile_personal_move.username AS users_profile_destination')
            ->leftJoin(
                'users_profile_move',
                UserProfilePersonal::TABLE,
                'users_profile_personal_move',
                'users_profile_personal_move.event = users_profile_move.event'
            );


        /** Пункт назначения при перемещении */

        $dbal->leftOneJoin(
            'ord',
            ProductStockMove::TABLE,
            'destination_stock',
            'destination_stock.event != stock.event  AND destination_stock.ord = ord.ord',
            'event'
        );


        $dbal->leftJoin(
            'destination_stock',
            ProductStockEvent::TABLE,
            'destination_event',
            'destination_event.id = destination_stock.event'
        );

        // UserProfile
        $dbal->leftJoin(
            'destination_stock',
            UserProfile::TABLE,
            'users_profile_destination',
            'users_profile_destination.id = destination_event.profile'
        );

        $dbal
            ->addSelect('users_profile_personal_destination.username AS users_profile_move')
            ->leftJoin(
                'users_profile_destination',
                UserProfilePersonal::TABLE,
                'users_profile_personal_destination',
                'users_profile_personal_destination.event = users_profile_destination.event'
            );


        /*if($filter->getWarehouse())
        {
            $dbal->andWhere('warehouse.const = :warehouse_filter');
            $dbal->setParameter('warehouse_filter', $filter->getWarehouse(), ContactsRegionCallConst::TYPE);
        }*/

        // Поиск
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('event.number');
        }


        $dbal->addOrderBy('products_move', 'ASC');
        $dbal->addOrderBy('modify.mod_date', 'ASC');
        $dbal->addOrderBy('stock.id', 'ASC');


        // dd($dbal->fetchAllAssociative());

        return $this->paginator->fetchAllAssociative($dbal);

    }
}
