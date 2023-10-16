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

use BaksDev\Contacts\Region\Entity as ContactsRegionEntity;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Entity as DeliveryEntity;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackage;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackageTransport;
use BaksDev\DeliveryTransport\Entity\Package\Stocks\DeliveryPackageStocks;
use BaksDev\DeliveryTransport\Type\ProductStockStatus\ProductStockStatusDivide;
use BaksDev\Orders\Order\Entity as OrderEntity;
use BaksDev\Products\Category\Entity as CategoryEntity;
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Stocks\Entity as ProductStockEntity;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Entity as UserProfileEntity;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllProductStocksPackage implements AllProductStocksPackageInterface
{
    private PaginatorInterface $paginator;
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /** Метод возвращает все заявки на упаковку заказов */
    public function fetchAllPackageAssociative(
        SearchDTO $search,
        ProductsStocksFilterInterface $filter,
        ?UserProfileUid $profile
    ): PaginatorInterface
    {
        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
        ;

        // ProductStock
        $qb->select('stock.id');
        $qb->addSelect('stock.event');

        $qb->from(ProductStockEntity\ProductStock::TABLE, 'stock');

        // ProductStockEvent
        $qb->addSelect('event.number');
        $qb->addSelect('event.comment');
        $qb->addSelect('event.status');

        $qb->join(
            'stock',
            ProductStockEntity\Event\ProductStockEvent::TABLE,
            'event',
            'event.id = stock.event AND (event.status = :package OR event.status = :move) '.($profile ? ' AND event.profile = :profile' : '')
        );


        if($profile)
        {
            $qb->setParameter('profile', $profile, UserProfileUid::TYPE);
        }

        $qb->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $qb->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);

        // ProductStockModify
        $qb->addSelect('modify.mod_date');
        $qb->join(
            'stock',
            ProductStockEntity\Modify\ProductStockModify::TABLE,
            'modify',
            'modify.event = stock.event'
        );

        return $this->paginator->fetchAllAssociative($qb);
    }

    /**
     * Метод возвращает все заявки на упаковку заказов.
     */
    public function fetchAllProductStocksAssociative(
        SearchDTO $search,
        ProductsStocksFilterInterface $filter,
        ?UserProfileUid $profile
    ): PaginatorInterface
    {
        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal()
        ;

        // Stock

        // ProductStock
        $qb->select('stock.id');
        $qb->addSelect('stock.event');

        $qb->from(ProductStockEntity\ProductStock::TABLE, 'stock');

        // ProductStockEvent
        $qb->addSelect('event.number');
        $qb->addSelect('event.comment');
        $qb->addSelect('event.status');
        $qb->join(
            'stock',
            ProductStockEntity\Event\ProductStockEvent::TABLE,
            'event',
            'event.id = stock.event AND  
            (
            event.status = :package OR 
            event.status = :move OR 
            event.status = :divide OR 
            event.status = :error 
            ) '.($profile ? ' AND event.profile = :profile' : '')
        );

        $qb->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $qb->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);
        $qb->setParameter('error', new ProductStockStatus(new ProductStockStatus\ProductStockStatusError()), ProductStockStatus::TYPE);


        if($profile)
        {
            $qb->setParameter('profile', $profile, UserProfileUid::TYPE);
        }


        /** Погрузка на доставку */

        if(defined(DeliveryPackage::class.'::TABLE'))
        {

            /** Подгружаем разделенные заказы */
            $qb->setParameter('divide', new ProductStockStatus(new ProductStockStatusDivide()), ProductStockStatus::TYPE);


            // Warehouse
            $existDeliveryPackage = $this->DBALQueryBuilder->builder();
            $existDeliveryPackage->select('1');
            $existDeliveryPackage->from(DeliveryPackage::TABLE, 'bGIuGLiNkf');
            $existDeliveryPackage->where('bGIuGLiNkf.event = delivery_stocks.event');

            $qb->leftJoin(
                'stock',
                DeliveryPackageStocks::TABLE,
                'delivery_stocks',
                'delivery_stocks.stock = stock.id AND EXISTS('.$existDeliveryPackage->getSQL().')'
            );


            $qb->leftJoin(
                'delivery_stocks',
                DeliveryPackage::TABLE,
                'delivery_package',
                'delivery_package.event = delivery_stocks.event'
            );

            $qb->addSelect('delivery_transport.date_package'); //->addGroupBy('warehouse.id');

            $qb->leftJoin(
                'delivery_package',
                DeliveryPackageTransport::TABLE,
                'delivery_transport',
                'delivery_transport.package = delivery_package.id'
            );

            $qb->addOrderBy('delivery_transport.date_package');

        }
        else
        {
            $qb->addSelect('NULL AS date_package');
            $qb->setParameter('divide', 'ntUIGnScMq');
        }


        // ProductStockModify
        $qb->addSelect('modify.mod_date');
        $qb->join(
            'stock',
            ProductStockEntity\Modify\ProductStockModify::TABLE,
            'modify',
            'modify.event = stock.event'
        );

        // ProductStockProduct
        $qb->addSelect('stock_product.id as product_stock_id');
        $qb->addSelect('stock_product.total');
        $qb->join(
            'event',
            ProductStockEntity\Products\ProductStockProduct::TABLE,
            'stock_product',
            'stock_product.event = stock.event'
        );

        /* Получаем наличие на указанном складе */
        $qb->addSelect('total.total AS stock_total');
        $qb->leftJoin(
            'stock_product',
            ProductStockEntity\ProductStockTotal::TABLE,
            'total',
            '
            total.warehouse = event.warehouse AND
            total.product = stock_product.product AND 
            (total.offer IS NULL OR total.offer = stock_product.offer) AND 
            (total.variation IS NULL OR total.variation = stock_product.variation) AND 
            (total.modification IS NULL OR total.modification = stock_product.modification)
            '
        );

        // Целевой склад (Склад погрузки)

        // Product Warehouse
        $qb->addSelect('warehouse.id as warehouse_id');
        $qb->addSelect('warehouse.event as warehouse_event');

        $qb->join(
            'event',
            ContactsRegionEntity\Call\ContactsRegionCall::TABLE,
            'warehouse',
            'warehouse.const = event.warehouse AND EXISTS(SELECT 1 FROM '.ContactsRegionEntity\ContactsRegion::TABLE.' WHERE event = warehouse.event)'
        );

        $qb->addSelect('warehouse_trans.name AS warehouse_name');

        $qb->join(
            'warehouse',
            ContactsRegionEntity\Call\Trans\ContactsRegionCallTrans::TABLE,
            'warehouse_trans',
            'warehouse_trans.call = warehouse.id AND warehouse_trans.local = :local'
        );

        /* Способ доставки */

        $qb->leftJoin(
            'stock',
            ProductStockEntity\Orders\ProductStockOrder::TABLE,
            'ord',
            'ord.event = stock.event'
        );

        $qb->leftJoin(
            'ord',
            OrderEntity\Order::TABLE,
            'orders',
            'orders.id = ord.ord'
        );

        $qb->leftJoin(
            'orders',
            OrderEntity\User\OrderUser::TABLE,
            'order_user',
            'order_user.event = orders.event'
        );

        $qb->leftJoin(
            'order_user',
            OrderEntity\User\Delivery\OrderDelivery::TABLE,
            'order_delivery',
            'order_delivery.usr = order_user.id'
        );

        $qb->leftJoin(
            'order_delivery',
            DeliveryEntity\Event\DeliveryEvent::TABLE,
            'delivery_event',
            'delivery_event.id = order_delivery.event AND delivery_event.main = order_delivery.delivery'
        );

        $qb->addSelect('delivery_trans.name AS delivery_name');
        $qb->leftJoin(
            'delivery_event',
            DeliveryEntity\Trans\DeliveryTrans::TABLE,
            'delivery_trans',
            'delivery_trans.event = delivery_event.id AND delivery_trans.local = :local'
        );

        // Product
        $qb->addSelect('product.id as product_id');
        $qb->addSelect('product.event as product_event');
        $qb->join(
            'stock_product',
            ProductEntity\Product::TABLE,
            'product',
            'product.id = stock_product.product'
        );

        // Product Event
        $qb->join(
            'product',
            ProductEntity\Event\ProductEvent::TABLE,
            'product_event',
            'product_event.id = product.event'
        );

        // Product Info
        $qb->addSelect('product_info.url AS product_url');
        $qb->leftJoin(
            'product_event',
            ProductEntity\Info\ProductInfo::TABLE,
            'product_info',
            'product_info.product = product.id'
        );

        // Product Trans
        $qb->addSelect('product_trans.name as product_name');
        //$qb->addSelect('product_trans.description as product_description');
        $qb->join(
            'product_event',
            ProductEntity\Trans\ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        /*
         * Торговое предложение
         */

        $qb->addSelect('product_offer.id as product_offer_uid');
        $qb->addSelect('product_offer.value as product_offer_value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix');

        $qb->leftJoin(
            'product_event',
            ProductEntity\Offers\ProductOffer::TABLE,
            'product_offer',
            'product_offer.event = product_event.id AND product_offer.const = stock_product.offer'
        );

        // Получаем тип торгового предложения
        $qb->addSelect('category_offer.reference as product_offer_reference');
        $qb->leftJoin(
            'product_offer',
            CategoryEntity\Offers\ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        $qb->addSelect('category_offer_trans.name as product_offer_name');
        $qb->leftJoin(
            'category_offer_variation',
            CategoryEntity\Offers\Trans\ProductCategoryOffersTrans::TABLE,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );

        /*
         * Множественные варианты торгового предложения
         */

        $qb->addSelect('product_offer_variation.id as product_variation_uid');
        $qb->addSelect('product_offer_variation.value as product_variation_value');
        $qb->addSelect('product_offer_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\ProductVariation::TABLE,
            'product_offer_variation',
            'product_offer_variation.offer = product_offer.id AND product_offer_variation.const = stock_product.variation'
        );

        // Получаем тип множественного варианта
        $qb->addSelect('category_offer_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_offer_variation',
            CategoryEntity\Offers\Variation\ProductCategoryVariation::TABLE,
            'category_offer_variation',
            'category_offer_variation.id = product_offer_variation.category_variation'
        );

        $qb->addSelect('category_offer_variation_trans.name as product_variation_name');
        $qb->leftJoin(
            'category_offer_variation',
            CategoryEntity\Offers\Variation\Trans\ProductCategoryVariationTrans::TABLE,
            'category_offer_variation_trans',
            'category_offer_variation_trans.variation = category_offer_variation.id AND category_offer_variation_trans.local = :local'
        );

        /*
         * Модификация множественного варианта торгового предложения
         */

        $qb->addSelect('product_offer_modification.id as product_modification_uid');
        $qb->addSelect('product_offer_modification.value as product_modification_value');
        $qb->addSelect('product_offer_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'product_offer_variation',
            ProductEntity\Offers\Variation\Modification\ProductModification::TABLE,
            'product_offer_modification',
            'product_offer_modification.variation = product_offer_variation.id AND product_offer_modification.const = stock_product.modification'
        );

        // Получаем тип модификации множественного варианта
        $qb->addSelect('category_offer_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_offer_modification',
            CategoryEntity\Offers\Variation\Modification\ProductCategoryModification::TABLE,
            'category_offer_modification',
            'category_offer_modification.id = product_offer_modification.category_modification'
        );

        $qb->addSelect('category_offer_modification_trans.name as product_modification_name');
        $qb->leftJoin(
            'category_offer_modification',
            CategoryEntity\Offers\Variation\Modification\Trans\ProductCategoryModificationTrans::TABLE,
            'category_offer_modification_trans',
            'category_offer_modification_trans.modification = category_offer_modification.id AND category_offer_modification_trans.local = :local'
        );

        // Артикул продукта

        $qb->addSelect(
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

        $qb->leftJoin(
            'product_offer_modification',
            ProductEntity\Offers\Variation\Modification\Image\ProductModificationImage::TABLE,
            'product_offer_modification_image',
            '
			product_offer_modification_image.modification = product_offer_modification.id AND
			product_offer_modification_image.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\Image\ProductVariationImage::TABLE,
            'product_offer_variation_image',
            '
			product_offer_variation_image.variation = product_offer_variation.id AND
			product_offer_variation_image.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Image\ProductOfferImage::TABLE,
            'product_offer_images',
            '
			product_offer_variation_image.name IS NULL AND
			product_offer_images.offer = product_offer.id AND
			product_offer_images.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Photo\ProductPhoto::TABLE,
            'product_photo',
            '
			product_offer_images.name IS NULL AND
			product_photo.event = product_event.id AND
			product_photo.root = true
			'
        );

        $qb->addSelect(
            "
			CASE
			 
			 WHEN product_offer_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Offers\Variation\Modification\Image\ProductModificationImage::TABLE."' , '/', product_offer_modification_image.name)
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Offers\Variation\Image\ProductVariationImage::TABLE."' , '/', product_offer_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Offers\Image\ProductOfferImage::TABLE."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Photo\ProductPhoto::TABLE."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        // Расширение файла
        $qb->addSelect(
            "
			CASE
			
			    WHEN product_offer_modification_image.name IS NOT NULL THEN
                     CASE WHEN product_offer_modification_image.cdn THEN  CONCAT ( 'small.', product_offer_modification_image.ext) ELSE product_offer_modification_image.ext END

			   WHEN product_offer_variation_image.name IS NOT NULL THEN
			        CASE WHEN product_offer_variation_image.cdn THEN  CONCAT ( 'small.', product_offer_variation_image.ext) ELSE product_offer_variation_image.ext END

			   WHEN product_offer_images.name IS NOT NULL THEN
			        CASE WHEN product_offer_images.cdn THEN  CONCAT ( 'small.', product_offer_images.ext) ELSE product_offer_images.ext END

			   WHEN product_photo.name IS NOT NULL THEN
			        CASE WHEN product_photo.cdn THEN  CONCAT ( 'small.', product_photo.ext) ELSE product_photo.ext END
					
			   ELSE NULL
			   
			END AS product_image_ext
		"
        );

        // Флаг загрузки файла CDN
        $qb->addSelect(
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
        $qb->leftJoin(
            'product_event',
            ProductEntity\Category\ProductCategory::TABLE,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        $qb->leftJoin(
            'product_event_category',
            CategoryEntity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $qb->addSelect('category_trans.name AS category_name');
        $qb->leftJoin(
            'category',
            CategoryEntity\Trans\ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $qb->addSelect('users_profile.event as users_profile_event');
        $qb->join(
            'event',
            UserProfileEntity\UserProfile::TABLE,
            'users_profile',
            'users_profile.id = event.profile'
        );

        // Info
        $qb->join(
            'event',
            UserProfileEntity\Info\UserProfileInfo::TABLE,
            'users_profile_info',
            'users_profile_info.profile = event.profile'
        );

        // Event
        $qb->join(
            'users_profile',
            UserProfileEntity\Event\UserProfileEvent::TABLE,
            'users_profile_event',
            'users_profile_event.id = users_profile.event'
        );

        // Personal
        $qb->addSelect('users_profile_personal.username AS users_profile_username');

        $qb->join(
            'users_profile_event',
            UserProfileEntity\Personal\UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile_event.id'
        );

        // Avatar

        $qb->addSelect("CONCAT ( '/upload/".UserProfileEntity\Avatar\UserProfileAvatar::TABLE."' , '/', users_profile_avatar.name) AS users_profile_avatar");
        $qb->addSelect("CASE WHEN users_profile_avatar.cdn THEN  CONCAT ( 'small.', users_profile_avatar.ext) ELSE users_profile_avatar.ext END AS users_profile_avatar_ext");
        $qb->addSelect('users_profile_avatar.cdn AS users_profile_avatar_cdn');

        $qb->leftJoin(
            'users_profile_event',
            UserProfileEntity\Avatar\UserProfileAvatar::TABLE,
            'users_profile_avatar',
            'users_profile_avatar.event = users_profile_event.id'
        );

        // Группа



        $qb->addSelect('NULL AS group_name'); // Название группы


        /** Проверка перемещения по заказу */
        $qbExist = $this->DBALQueryBuilder->builder();

        $qbExist->select('1');
        $qbExist->from(ProductStockMove::TABLE, 'exist_move');
        $qbExist->where('exist_move.ord = ord.ord ');

        $qbExist->join(
            'exist_move',
            ProductStockEvent::TABLE,
            'exist_move_event',
            'exist_move_event.id = exist_move.event AND  (
                exist_move_event.status != :incoming
            )'
        );

        $qbExist->join(
            'exist_move_event',
            ProductStock::TABLE,
            'exist_move_stock',
            'exist_move_stock.event = exist_move_event.id'
        );

        $qb->addSelect(sprintf('EXISTS(%s) AS products_move', $qbExist->getSQL()));
        $qb->setParameter('incoming', new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()), ProductStockStatus::TYPE);

        if($filter->getWarehouse())
        {
            $qb->andWhere('warehouse.const = :warehouse_filter');
            $qb->setParameter('warehouse_filter', $filter->getWarehouse(), ContactsRegionCallConst::TYPE);
        }

        // Поиск
        if($search->getQuery())
        {
            $qb
                ->createSearchQueryBuilder($search)
                ->addSearchLike('event.number');
        }

        $qb->addOrderBy('products_move', 'ASC');
        $qb->addOrderBy('modify.mod_date', 'ASC');


        return $this->paginator->fetchAllAssociative($qb);

    }
}
