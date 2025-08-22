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

namespace BaksDev\Products\Stocks\Repository\ProductsByProductStocks;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Delivery\Entity\Fields\DeliveryField;
use BaksDev\Delivery\Entity\Fields\Trans\DeliveryFieldTrans;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\Delivery\Field\OrderDeliveryField;
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
use BaksDev\Products\Product\Entity\Category\ProductCategory;
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
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;

final class ProductsByProductStocksRepository implements ProductsByProductStocksInterface
{

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Метод возвращает информацию о продукции в складской заявке.
     */
    public function fetchAllProductsByProductStocksAssociative(ProductStockUid $stock): array|bool
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(ProductStock::class, 'stock')
            ->where('stock.id = :stock')
            ->setParameter('stock', $stock, ProductStockUid::TYPE);


        $dbal
            ->addSelect('product_stock_invariable.number')
            ->join(
                'stock',
                ProductStocksInvariable::class,
                'product_stock_invariable',
                'product_stock_invariable.main = stock.id',
            );


        $dbal
            ->addSelect('stock_product.total')
            ->join(
                'stock',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            );


        /** Информация о заказе */

        $dbal->leftJoin(
            'stock',
            ProductStockOrder::class,
            'stock_order',
            'stock_order.event = stock.event',
        );


        $dbal
            ->leftJoin(
                'stock',
                ProductStockMove::class,
                'stock_move',
                'stock_move.event = stock.event',
            );

        $dbal->join(
            'stock_order',
            Order::class,
            'orders',
            'orders.id = 
            
                CASE
                    WHEN stock_move.ord IS NOT NULL THEN stock_move.ord
                   ELSE stock_order.ord
                END
            
            ');


        $dbal
            ->addSelect('destination.id AS destination')
            ->leftJoin(
                'stock_move',
                UserProfile::class,
                'destination',
                'destination.id = stock_move.destination '
            );


        $dbal
            ->addSelect('destination_personal.location AS destination_location')
            ->addSelect('destination_personal.latitude AS destination_latitude')
            ->addSelect('destination_personal.longitude AS destination_longitude')
            ->leftJoin(
                'destination',
                UserProfilePersonal::class,
                'destination_personal',
                'destination_personal.event = destination.event '
            );


        $dbal->leftJoin(
            'orders',
            OrderEvent::class,
            'orders_event',
            'orders_event.id = orders.event'
        );


        $dbal
            ->addSelect('order_user.profile AS order_client')
            ->leftJoin(
                'orders',
                OrderUser::class,
                'order_user',
                'order_user.event = orders.event'
            );


        $dbal->leftJoin(
            'order_user',
            OrderDelivery::class,
            'order_delivery',
            'order_delivery.usr = order_user.id'
        );

        $dbal->leftJoin(
            'order_delivery',
            OrderDeliveryField::class,
            'order_delivery_fields',
            'order_delivery_fields.delivery = order_delivery.id'
        );

        $dbal->leftJoin(
            'order_delivery',
            DeliveryField::class,
            'delivery_field',
            'delivery_field.id = order_delivery_fields.field'
        );

        $dbal->leftJoin(
            'delivery_field',
            DeliveryFieldTrans::class,
            'delivery_field_trans',
            'delivery_field_trans.field = delivery_field.id AND delivery_field_trans.local = :local'
        );


        /* Информация о доставке  */
        $dbal->addSelect(
            "JSON_AGG
            ( /*DISTINCT*/

                    JSONB_BUILD_OBJECT
                    (
                        'order_field_name', delivery_field_trans.name,
                        'order_field_type', delivery_field.type,
                        'order_field_value', order_delivery_fields.value
                    )
            )
			AS order_fields"
        );


        /**
         * Продукция
         */

        $dbal
            ->addSelect('product.id AS product_id')
            ->join(
                'stock',
                Product::class,
                'product',
                'product.id = stock_product.product'
            );


        $dbal->addSelect('product_info.url AS product_url');//->addGroupBy('product_info.url');
        $dbal->join(
            'product',
            ProductInfo::class,
            'product_info',
            'product_info.product = stock_product.product '
        );


        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );


        /* Торговое предложение */

        $dbal
            ->addSelect('product_offer.const AS product_offer_const')
            ->addSelect('product_offer.value AS product_offer_value')
            ->addSelect('product_offer.postfix AS product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.const = stock_product.offer AND product_offer.event = product.event'
            );


        /* Тип торгового предложения */

        $dbal
            ->addSelect('category_offer.reference AS product_offer_reference')
            ->addSelect('category_offer_trans.name AS product_offer_name')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        /* Название торгового предложения */
        $dbal->leftJoin(
            'category_offer',
            CategoryProductOffersTrans::class,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );


        /**
         * Множественный вариант
         */

        $dbal
            ->addSelect('product_variation.const AS product_variation_const')
            ->addSelect('product_variation.value AS product_variation_value')
            ->addSelect('product_variation.postfix AS product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'stock_product.variation IS NOT NULL AND  product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
            );

        /* Получаем тип множественного варианта */

        $dbal
            ->addSelect('category_variation.reference AS product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        /* Получаем название множественного варианта */
        $dbal
            ->addSelect('category_variation_trans.name AS product_variation_name')
            ->leftJoin(
                'category_variation',
                CategoryProductVariationTrans::class,
                'category_variation_trans',
                'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
            );


        /**
         * Модификация множественного варианта торгового предложения
         */

        $dbal
            ->addSelect('product_modification.const AS product_modification_const')
            ->addSelect('product_modification.value AS product_modification_value')
            ->addSelect('product_modification.postfix AS product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'stock_product.modification IS NOT NULL AND product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification'
            );


        $dbal
            ->addSelect('category_modification.reference AS product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );

        /* Получаем название типа модификации */
        $dbal
            ->addSelect('category_modification_trans.name AS product_modification_name')
            ->leftJoin(
                'category_modification',
                CategoryProductModificationTrans::class,
                'category_modification_trans',
                'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
            );


        /* Фото продукта */

        $dbal->leftJoin(
            'product',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product.event AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_image',
            'product_offer_image.offer = product_offer.id AND product_offer_image.root = true'
        );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $dbal
            ->addSelect("
         CASE
               WHEN product_modification_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)
               WHEN product_variation_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
               WHEN product_offer_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_image.name)
               WHEN product_photo.name IS NOT NULL THEN
                    CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
               ELSE NULL
            END
           AS product_image")
            ->addGroupBy('product_modification_image.name')
            ->addGroupBy('product_variation_image.name')
            ->addGroupBy('product_offer_image.name')
            ->addGroupBy('product_photo.name');

        $dbal->addSelect('
         CASE
                WHEN product_modification_image.name IS NOT NULL THEN
                    product_modification_image.ext
               WHEN product_variation_image.name IS NOT NULL THEN
                    product_variation_image.ext
               WHEN product_offer_image.name IS NOT NULL THEN
                    product_offer_image.ext
               WHEN product_photo.name IS NOT NULL THEN
                    product_photo.ext
               ELSE NULL
            END 
            AS product_image_ext')
            ->addGroupBy('product_modification_image.ext')
            ->addGroupBy('product_variation_image.ext')
            ->addGroupBy('product_offer_image.ext')
            ->addGroupBy('product_photo.ext');

        $dbal->addSelect('
        CASE
                WHEN product_modification_image.name IS NOT NULL THEN
                    product_modification_image.cdn
               WHEN product_variation_image.name IS NOT NULL THEN
                    product_variation_image.cdn
               WHEN product_offer_image.name IS NOT NULL THEN
                    product_offer_image.cdn
               WHEN product_photo.name IS NOT NULL THEN
                    product_photo.cdn
               ELSE NULL
            END
            AS product_image_cdn')
            ->addGroupBy('product_modification_image.cdn')
            ->addGroupBy('product_variation_image.cdn')
            ->addGroupBy('product_offer_image.cdn')
            ->addGroupBy('product_photo.cdn');


        /* Категория */
        $dbal->join(
            'product',
            ProductCategory::class,
            'product_category',
            'product_category.event = product.event AND product_category.root = true'
        );


        $dbal->join(
            'product_category',
            CategoryProduct::class,
            'category',
            'category.id = product_category.category'
        );


        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event'
            );


        /** Наличие и место на складе */

        $dbal
            ->addSelect("STRING_AGG(stock_total.storage, ', ') AS stock_total_storage")
            ->leftJoin(
                'product_modification',
                ProductStockTotal::class,
                'stock_total',
                '
                    stock_total.profile = product_stock_invariable.profile AND
                    stock_total.product = product.id AND
                    stock_total.offer = product_offer.const AND
                    stock_total.variation = product_variation.const AND
                    stock_total.modification = product_modification.const
                '
            );


        $dbal->allGroupByExclude();

        return $dbal
            ->enableCache('products-stocks', 86400)
            ->fetchAllAssociative();

    }
}
