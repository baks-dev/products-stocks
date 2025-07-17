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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackage;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackageTransport;
use BaksDev\DeliveryTransport\Entity\Package\Stocks\DeliveryPackageStocks;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Print\OrderPrint;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\CategoryProductOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\CategoryProductModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\CategoryProductVariationTrans;
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
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Forms\PackageFilter\ProductStockPackageFilterInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;

final class AllProductStocksPackageRepository implements AllProductStocksPackageInterface
{
    private ?SearchDTO $search = null;

    private ?ProductStockPackageFilterInterface $filter = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductStockPackageFilterInterface $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->paginator->setLimit($limit);
        return $this;
    }

    private function builder(): DBALQueryBuilder
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // Stock

        // ProductStock
        $dbal->select('stock.id');
        $dbal->addSelect('stock.event');

        $dbal->from(ProductStock::class, 'stock');

        $dbal
            ->addSelect('invariable.number')
            ->join(
                'stock',
                ProductStocksInvariable::class,
                'invariable',
                '
                invariable.main = stock.id AND 
                invariable.profile = :profile
            '
            )
            ->setParameter(
                key: 'profile',
                value: $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE
            );


        // ProductStockEvent
        //$dbal->addSelect('event.number');
        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->join(
                'stock',
                ProductStockEvent::class,
                'event',
                '
                event.id = stock.event AND 
                event.status = :package
                ');


        /* (
                event.status = :package OR
                event.status = :move OR
                event.status = :divide OR
                event.status = :error

            ) */

        $dbal->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);

        //$dbal->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);
        ///$dbal->setParameter('error', new ProductStockStatus(new ProductStockStatus\ProductStockStatusError()), ProductStockStatus::TYPE);
        //$dbal->setParameter('divide', new ProductStockStatus(new ProductStockStatusDivide()), ProductStockStatus::TYPE);


        /** Погрузка на доставку */

        if(class_exists(DeliveryPackage::class))
        {

            /** Подгружаем разделенные заказы */


            $existDeliveryPackage = $this->DBALQueryBuilder->createQueryBuilder(self::class);
            $existDeliveryPackage->select('1');
            $existDeliveryPackage->from(DeliveryPackage::class, 'bGIuGLiNkf');
            $existDeliveryPackage->where('bGIuGLiNkf.event = delivery_stocks.event');

            $dbal->leftJoin(
                'stock',
                DeliveryPackageStocks::class,
                'delivery_stocks',
                'delivery_stocks.stock = stock.id AND EXISTS('.$existDeliveryPackage->getSQL().')'
            );


            $dbal->leftJoin(
                'delivery_stocks',
                DeliveryPackage::class,
                'delivery_package',
                'delivery_package.event = delivery_stocks.event'
            );

            $dbal->addSelect('delivery_transport.date_package');

            $dbal->leftJoin(
                'delivery_package',
                DeliveryPackageTransport::class,
                'delivery_transport',
                'delivery_transport.package = delivery_package.id'
            );

            //$dbal->addOrderBy('delivery_transport.date_package');

        }
        else
        {
            $dbal->addSelect('NULL AS date_package');
            $dbal->setParameter('divide', 'ntUIGnScMq');
        }


        $dbal
            ->addSelect('modify.mod_date')
            ->leftJoin(
                'stock',
                ProductStockModify::class,
                'modify',
                'modify.event = stock.event'
            );


        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->join(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            );


        $dbal
            ->addSelect('SUM(total.total) AS stock_total')
            ->addSelect("STRING_AGG(CONCAT(total.storage, ': [', total.total, ']'), ', ' ORDER BY total.total) AS stock_storage")
            ->leftJoin(
                'stock_product',
                ProductStockTotal::class,
                'total',
                '
                total.profile = invariable.profile AND
                total.product = stock_product.product AND 
                (total.offer IS NULL OR total.offer = stock_product.offer) AND 
                (total.variation IS NULL OR total.variation = stock_product.variation) AND 
                (total.modification IS NULL OR total.modification = stock_product.modification) AND
                total.total > 0
            '
            );

        $dbal->join(
            'stock',
            ProductStockOrder::class,
            'ord',
            'ord.event = stock.event'
        );


        $dbal
            ->addSelect('orders.id AS order_id')
            ->leftJoin(
                'ord',
                Order::class,
                'orders',
                'orders.id = ord.ord'
            );

        $dbal
            ->addSelect('order_event.danger AS order_danger')
            ->addSelect('order_event.comment AS order_comment')
            ->leftJoin(
                'ord',
                OrderEvent::class,
                'order_event',
                'order_event.id = orders.event'
            );

        $dbal
            ->addSelect('order_print.printed as printed')
            ->leftJoin(
                'order_event',
                OrderPrint::class,
                'order_print',
                'order_print.event = order_event.id'
            );

        $dbal->leftJoin(
            'orders',
            OrderUser::class,
            'order_user',
            'order_user.event = orders.event'
        );


        $dbal->addSelect('order_delivery.delivery_date');

        $delivery_condition = 'order_delivery.usr = order_user.id';

        if($this->filter !== null)
        {
            if($this->filter->getDate() instanceof DateTimeImmutable)
            {
                $delivery_condition .= ' AND order_delivery.delivery_date >= :delivery_date_start AND order_delivery.delivery_date < :delivery_date_end';
                $dbal->setParameter('delivery_date_start', $this->filter->getDate(), Types::DATE_IMMUTABLE);
                $dbal->setParameter('delivery_date_end', $this->filter->getDate()?->modify('+1 day'), Types::DATE_IMMUTABLE);
            }

            if($this->filter->getDelivery() instanceof DeliveryUid)
            {
                $delivery_condition .= ' AND order_delivery.delivery = :delivery';
                $dbal->setParameter('delivery', $this->filter->getDelivery(), DeliveryUid::TYPE);
            }
        }

        $dbal
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                $delivery_condition
            );

        $dbal->leftJoin(
            'order_delivery',
            DeliveryEvent::class,
            'delivery_event',
            'delivery_event.id = order_delivery.event AND delivery_event.main = order_delivery.delivery'
        );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'delivery_event',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = delivery_event.id AND delivery_trans.local = :local'
            );


        // Product
        $dbal
            ->addSelect('product.id as product_id')
            ->addSelect('product.event as product_event')
            ->leftJoin(
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

        // Product Info
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

        /*
         * Торговое предложение
         */

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

        $dbal
            ->addSelect('category_offer_trans.name as product_offer_name')
            ->leftJoin(
                'category_offer',
                CategoryProductOffersTrans::class,
                'category_offer_trans',
                'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
            );

        /*
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
            );

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        $dbal
            ->addSelect('category_variation_trans.name as product_variation_name')
            ->leftJoin(
                'category_variation',
                CategoryProductVariationTrans::class,
                'category_variation_trans',
                'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
            );

        /*
         * Модификация множественного варианта торгового предложения
         */

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification'
            );

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );

        $dbal
            ->addSelect('category_modification_trans.name as product_modification_name')
            ->leftJoin(
                'category_modification',
                CategoryProductModificationTrans::class,
                'category_modification_trans',
                'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
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
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            '
                product_variation_image.variation = product_variation.id AND
                product_variation_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            '
                product_variation_image.name IS NULL AND
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
		"
        );

        // Расширение файла
        $dbal->addSelect(
            "
			CASE
			
			   WHEN product_modification_image.name IS NOT NULL THEN  product_modification_image.ext
			   WHEN product_variation_image.name IS NOT NULL THEN product_variation_image.ext
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
			   WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.cdn
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.cdn
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.cdn
			   ELSE NULL
			END AS product_image_cdn
		'
        );


        /*$dbal->addSelect("
			COALESCE(
                NULLIF(COUNT(product_offer), 0),
                NULLIF(COUNT(product_variation), 0),
                NULLIF(COUNT(product_modification), 0),
                0
            ) AS offer_count
		");*/


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

        $dbal->addSelect('category_trans.name AS category_name');
        $dbal->leftJoin(
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
        $dbal->addSelect('users_profile.event as users_profile_event');
        $dbal->leftJoin(
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
            'users_profile_info.profile = invariable.profile'
        );

        // Event
        $dbal->leftJoin(
            'users_profile',
            UserProfileEvent::class,
            'users_profile_event',
            'users_profile_event.id = users_profile.event'
        );

        // Personal
        $dbal->addSelect('users_profile_personal.username AS users_profile_username');

        $dbal->leftJoin(
            'users_profile_event',
            UserProfilePersonal::class,
            'users_profile_personal',
            'users_profile_personal.event = users_profile_event.id'
        );

        $dbal->leftJoin(
            'users_profile_event',
            UserProfileAvatar::class,
            'users_profile_avatar',
            'users_profile_avatar.event = users_profile_event.id'
        );

        // Группа


        /** Проверка перемещения по заказу */
        $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbalExist->select('1');
        $dbalExist->from(ProductStockMove::class, 'exist_move');
        $dbalExist->where('exist_move.ord = ord.ord ');

        $dbalExist->join(
            'exist_move',
            ProductStockEvent::class,
            'exist_move_event',
            'exist_move_event.id = exist_move.event AND  (
                exist_move_event.status != :incoming
            )'
        );


        $dbalExist->join(
            'exist_move_event',
            ProductStock::class,
            'exist_move_stock',
            'exist_move_stock.event = exist_move_event.id'
        );


        $dbal->addSelect(sprintf('EXISTS(%s) AS products_move', $dbalExist->getSQL()));
        $dbal->setParameter('incoming', new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()), ProductStockStatus::TYPE);


        /** Пункт назначения при перемещении */

        $dbal->leftJoin(
            'event',
            ProductStockMove::class,
            'move_stock',
            'move_stock.event = event.id'
        );


        // UserProfile
        $dbal->leftJoin(
            'move_stock',
            UserProfile::class,
            'users_profile_move',
            'users_profile_move.id = move_stock.destination'
        );

        $dbal
            ->addSelect('users_profile_personal_move.username AS users_profile_destination')
            ->leftJoin(
                'users_profile_move',
                UserProfilePersonal::class,
                'users_profile_personal_move',
                'users_profile_personal_move.event = users_profile_move.event'
            );

        /** Пункт назначения при перемещении */

        $dbal->leftOneJoin(
            'ord',
            ProductStockMove::class,
            'destination_stock',
            'destination_stock.event != stock.event AND destination_stock.ord = ord.ord',
            'event'
        );


        $dbal->leftJoin(
            'destination_stock',
            ProductStockEvent::class,
            'destination_event',
            'destination_event.id = destination_stock.event'
        );

        // UserProfile
        $dbal->leftJoin(
            'destination_stock',
            UserProfile::class,
            'users_profile_destination',
            'users_profile_destination.id = destination_event.profile'
        );

        $dbal
            ->addSelect('users_profile_personal_destination.username AS users_profile_move')
            ->leftJoin(
                'users_profile_destination',
                UserProfilePersonal::class,
                'users_profile_personal_destination',
                'users_profile_personal_destination.event = users_profile_destination.event'
            );


        // Поиск
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('event.number')
                ->addSearchLike('invariable.number')
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_variation.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_info.article');
        }


        $dbal->addOrderBy('products_move', 'ASC');
        $dbal->addOrderBy('order_delivery.delivery_date', 'ASC');
        $dbal->addOrderBy('stock.id', 'ASC');

        $dbal->addGroupBy('ord.ord');
        $dbal->allGroupByExclude();
//        dd($dbal->fetchAllHydrate(AllProductStocksPackageResult::class)->current());
//dd($this->paginator->fetchAllHydrate($dbal, AllProductStocksPackageResult::class)->getData());
        return $dbal;
    }


    /**
     * Метод возвращает все заявки на упаковку заказов в виде массива.
     */
    public function findPaginator(): PaginatorInterface
    {
        $dbal = $this->builder();

        return $this->paginator->fetchAllAssociative($dbal);
    }

    /**
     * Метод возвращает все заявки на упаковку заказов в виде коллекции объектов.
     */
    public function findResultPaginator(): PaginatorInterface
    {
        $dbal = $this->builder();

        return $this->paginator->fetchAllHydrate($dbal, AllProductStocksPackageResult::class);
    }


    /**
     * Метод возвращает всю продукцию требующая сборки
     */
    public function findAllProducts(UserProfileUid $profile): ?array
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // Stock

        // ProductStock
        //$dbal->select('stock.id');
        //$dbal->addSelect('stock.event');

        $dbal->from(ProductStock::class, 'stock');

        // ProductStockEvent
        //$dbal->addSelect('event.number');
        //$dbal->addSelect('event.comment');
        //$dbal->addSelect('event.status');

        $dbal->join(
            'stock',
            ProductStockEvent::class,
            'event',
            '
            event.id = stock.event AND 
            event.profile = :profile AND  
            (
                event.status = :package OR 
                event.status = :move
                
            )'
        );

        $dbal->setParameter('package', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPackage()), ProductStockStatus::TYPE);
        $dbal->setParameter('move', new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving()), ProductStockStatus::TYPE);
        $dbal->setParameter('profile', $profile, UserProfileUid::TYPE);


        /** Погрузка на доставку */

        //        if(defined(DeliveryPackage::class.'::class'))
        //        {
        //
        //            /** Подгружаем разделенные заказы */
        //
        //
        //            $existDeliveryPackage = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        //            $existDeliveryPackage->select('1');
        //            $existDeliveryPackage->from(DeliveryPackage::class, 'bGIuGLiNkf');
        //            $existDeliveryPackage->where('bGIuGLiNkf.event = delivery_stocks.event');
        //
        //            $dbal->leftJoin(
        //                'stock',
        //                DeliveryPackageStocks::class,
        //                'delivery_stocks',
        //                'delivery_stocks.stock = stock.id AND EXISTS('.$existDeliveryPackage->getSQL().')'
        //            );
        //
        //
        //            $dbal->leftJoin(
        //                'delivery_stocks',
        //                DeliveryPackage::class,
        //                'delivery_package',
        //                'delivery_package.event = delivery_stocks.event'
        //            );
        //
        //            //$dbal->addSelect('delivery_transport.date_package');
        //
        //            $dbal->leftJoin(
        //                'delivery_package',
        //                DeliveryPackageTransport::class,
        //                'delivery_transport',
        //                'delivery_transport.package = delivery_package.id'
        //            );
        //
        //            //$dbal->addOrderBy('delivery_transport.date_package');
        //
        //        }
        //        else
        //        {
        //            $dbal->addSelect('NULL AS date_package');
        //            $dbal->setParameter('divide', 'ntUIGnScMq');
        //        }

        //
        //        $dbal
        //            ->addSelect('modify.mod_date')
        //            ->join(
        //                'stock',
        //                ProductStockModify::class,
        //                'modify',
        //                'modify.event = stock.event'
        //            );


        $dbal
            ->addSelect('SUM(stock_product.total) AS total')
            ->leftJoin(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            );


        /* Получаем наличие на указанном складе */

        $storage = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        $storage->select("STRING_AGG(DISTINCT CONCAT(total.storage, ' [', total.total, ']'), ', ' ) AS stock_storage");
        $storage
            ->from(ProductStockTotal::class, 'total')
            ->where('total.profile = :profile')
            ->andWhere('total.product = stock_product.product')
            ->andWhere('total.offer = stock_product.offer')
            ->andWhere('total.variation = stock_product.variation')
            ->andWhere('total.modification = stock_product.modification')
            ->andWhere('total.total > 0');


        $dbal->addSelect('('.$storage->getSQL().') AS stock_storage');


        $dbal
            ->addGroupBy('stock_product.product')
            ->addGroupBy('stock_product.offer')
            ->addGroupBy('stock_product.variation')
            ->addGroupBy('stock_product.modification');

        $dbal->join(
            'stock',
            ProductStockOrder::class,
            'ord',
            'ord.event = stock.event'
        );


        $dbal
            //->addSelect('orders.id AS order_id')
            ->leftJoin(
                'ord',
                Order::class,
                'orders',
                'orders.id = ord.ord'
            );

        $dbal->leftJoin(
            'orders',
            OrderUser::class,
            'order_user',
            'order_user.event = orders.event'
        );


        $delivery_condition = 'order_delivery.usr = order_user.id';

        if($this->filter !== null)
        {
            if($this->filter->getDate() instanceof DateTimeImmutable)
            {
                $delivery_condition .= ' AND order_delivery.delivery_date >= :delivery_date_start AND order_delivery.delivery_date < :delivery_date_end';
                $dbal->setParameter('delivery_date_start', $this->filter->getDate(), Types::DATE_IMMUTABLE);
                $dbal->setParameter('delivery_date_end', $this->filter->getDate()->modify('+1 day'), Types::DATE_IMMUTABLE);
            }


            if($this->filter->getDelivery() instanceof DeliveryUid)
            {
                $delivery_condition .= ' AND order_delivery.delivery = :delivery';
                $dbal->setParameter('delivery', $this->filter->getDelivery(), DeliveryUid::TYPE);
            }

        }

        $dbal
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                $delivery_condition
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


        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );

        /*
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event AND product_offer.const = stock_product.offer'
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


        /*
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
            );

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        //        $dbal
        //            ->addSelect('category_variation_trans.name as product_variation_name')
        //            ->leftJoin(
        //                'category_variation',
        //                CategoryProductVariationTrans::class,
        //                'category_variation_trans',
        //                'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
        //            );

        /*
         * Модификация множественного варианта торгового предложения
         */

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification'
            );

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );

        //        $dbal
        //            ->addSelect('category_modification_trans.name as product_modification_name')
        //            ->leftJoin(
        //                'category_modification',
        //                CategoryProductModificationTrans::class,
        //                'category_modification_trans',
        //                'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
        //            );

        // Артикул продукта

        //        $dbal->addSelect(
        //            '
        //			CASE
        //			   WHEN product_modification.article IS NOT NULL THEN product_modification.article
        //			   WHEN product_variation.article IS NOT NULL THEN product_variation.article
        //			   WHEN product_offer.article IS NOT NULL THEN product_offer.article
        //			   WHEN product_info.article IS NOT NULL THEN product_info.article
        //			   ELSE NULL
        //			END AS product_article
        //		'
        //        );


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        //$dbal->addSelect('users_profile.event as users_profile_event');
        $dbal->join(
            'event',
            UserProfile::class,
            'users_profile',
            'users_profile.id = event.profile'
        );

        // Info
        //        $dbal->leftJoin(
        //            'event',
        //            UserProfileInfo::class,
        //            'users_profile_info',
        //            'users_profile_info.profile = event.profile'
        //        );

        // Event
        //        $dbal->leftJoin(
        //            'users_profile',
        //            UserProfileEvent::class,
        //            'users_profile_event',
        //            'users_profile_event.id = users_profile.event'
        //        );

        // Personal
        $dbal->addSelect('users_profile_personal.username AS users_profile_username');

        $dbal->leftJoin(
            'users_profile',
            UserProfilePersonal::class,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );


        // Группа


        //$dbal->addSelect('NULL AS group_name'); // Название группы

        //        /** Проверка перемещения по заказу */
        //        $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        //
        //        $dbalExist->select('1');
        //        $dbalExist->from(ProductStockMove::class, 'exist_move');
        //        $dbalExist->where('exist_move.ord = ord.ord ');
        //
        //        $dbalExist->join(
        //            'exist_move',
        //            ProductStockEvent::class,
        //            'exist_move_event',
        //            'exist_move_event.id = exist_move.event AND  (
        //                exist_move_event.status != :incoming
        //            )'
        //        );
        //
        //
        //        $dbalExist->join(
        //            'exist_move_event',
        //            ProductStock::class,
        //            'exist_move_stock',
        //            'exist_move_stock.event = exist_move_event.id'
        //        );
        //
        //
        //        $dbal->addSelect(sprintf('EXISTS(%s) AS products_move', $dbalExist->getSQL()));
        //        $dbal->setParameter('incoming', new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()), ProductStockStatus::TYPE);


        /** Пункт назначения при перемещении */

        $dbal->leftJoin(
            'event',
            ProductStockMove::class,
            'move_stock',
            'move_stock.event = event.id'
        );


        // UserProfile
        $dbal->leftJoin(
            'move_stock',
            UserProfile::class,
            'users_profile_move',
            'users_profile_move.id = move_stock.destination'
        );

        $dbal
            //->addSelect('users_profile_personal_move.username AS users_profile_destination')
            ->leftJoin(
                'users_profile_move',
                UserProfilePersonal::class,
                'users_profile_personal_move',
                'users_profile_personal_move.event = users_profile_move.event'
            );

        /** Пункт назначения при перемещении */

        $dbal->leftOneJoin(
            'ord',
            ProductStockMove::class,
            'destination_stock',
            'destination_stock.event != stock.event AND destination_stock.ord = ord.ord',
            'event'
        );


        $dbal->leftJoin(
            'destination_stock',
            ProductStockEvent::class,
            'destination_event',
            'destination_event.id = destination_stock.event'
        );

        // UserProfile
        $dbal->leftJoin(
            'destination_stock',
            UserProfile::class,
            'users_profile_destination',
            'users_profile_destination.id = destination_event.profile'
        );

        $dbal
            //->addSelect('users_profile_personal_destination.username AS users_profile_move')
            ->leftJoin(
                'users_profile_destination',
                UserProfilePersonal::class,
                'users_profile_personal_destination',
                'users_profile_personal_destination.event = users_profile_destination.event'
            );


        // Поиск
        //        if($this->search?->getQuery())
        //        {
        //            $dbal
        //                ->createSearchQueryBuilder($this->search)
        //                ->addSearchLike('event.number')
        //                ->addSearchLike('product_modification.article')
        //                ->addSearchLike('product_variation.article')
        //                ->addSearchLike('product_offer.article')
        //                ->addSearchLike('product_info.article');
        //        }


        //$dbal->addOrderBy('stock_storage', 'ASC');

        //$dbal->addOrderBy('order_delivery.delivery_date', 'ASC');
        //$dbal->addOrderBy('stock.id', 'ASC');

        //$dbal->addGroupBy('ord.ord');
        $dbal->allGroupByExclude();
        $dbal->addOrderBy('stock_storage', 'ASC');

        ///$dbal->addGroupBy('ord.ord');
        //$dbal->allGroupByExclude();

        //        $dbal->setMaxResults(24);
        //        dd($dbal->fetchAllAssociative());
        //        dd($this->paginator->fetchAllAssociative($dbal));


        return $dbal
            //->enableCache('products-stocks')
            ->fetchAllAssociative();

    }

}
