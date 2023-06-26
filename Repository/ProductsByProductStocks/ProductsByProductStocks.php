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

namespace BaksDev\Products\Stocks\Repository\ProductsByProductStocks;

use BaksDev\Contacts\Region\Entity\Call\ContactsRegionCall;
use BaksDev\Contacts\Region\Entity\Call\Trans\ContactsRegionCallTrans;
use BaksDev\Contacts\Region\Entity\ContactsRegion;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Delivery\Entity\Fields\DeliveryField;
use BaksDev\Delivery\Entity\Fields\Trans\DeliveryFieldTrans;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\Delivery\Field\OrderDeliveryField;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Category\Entity\Offers\ProductCategoryOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\ProductCategoryOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\ProductCategoryOffersVariationModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\ProductCategoryOffersVariationModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\ProductCategoryOffersVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\ProductCategoryOffersVariationTrans;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductOfferVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductOfferVariationModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductOfferVariationModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductOfferVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductsByProductStocks implements ProductsByProductStocksInterface
{
    private Connection $connection;

    private TranslatorInterface $translator;

    public function __construct(
        Connection $connection,
        TranslatorInterface $translator
    ) {
        $this->connection = $connection;
        $this->translator = $translator;
    }

    /**
     * Метод возвращает информацию о продукции в складской заявке.
     */
    public function fetchAllProductsByProductStocksAssociative(ProductStockUid $stock): array|bool
    {
        $qb = $this->connection->createQueryBuilder();
        $locale = new Locale($this->translator->getLocale());

       // $qb->select('*');

        $qb->from(ProductStock::TABLE, 'stock');
        $qb->where('stock.id = :stock');
        $qb->setParameter('stock', $stock, ProductStockUid::TYPE);



        $qb->addSelect('stock_event.number')->addGroupBy('stock_event.number');
        $qb->join(
            'stock',
            ProductStockEvent::TABLE,
            'stock_event',
            'stock_event.id = stock.event'
        );


        $qb->addSelect('stock_product.total')->addGroupBy('stock_product.total');
        $qb->join(
            'stock',
            ProductStockProduct::TABLE,
            'stock_product',
            'stock_product.event = stock.event'
        );


        /** Информация о заказе */

        //$qb->addSelect('stock_order.ord');
        $qb->leftJoin(
            'stock_event',
            ProductStockOrder::TABLE,
            'stock_order',
            'stock_order.event = stock_event.id'
        );


        $qb->leftJoin(
            'stock_event',
            ProductStockMove::TABLE,
            'stock_move',
            'stock_move.event = stock_event.id'
        );


        /*$qb->join(
            'stock_order',
            Order::TABLE,
            'orders',
            'orders.id = stock_order.ord OR orders.id = stock_move.ord'
        );*/


        $qb->join(
            'stock_order',
            Order::TABLE,
            'orders',
            'orders.id = 
            
            CASE
                WHEN stock_move.ord IS NOT NULL THEN stock_move.ord
               ELSE stock_order.ord
            END
            
            '
        );


        /*$qb->join(
            'stock_order',
            Order::TABLE,
            'orders',
            'CASE
                WHEN stock_move.ord IS NOT NULL THEN orders.id = stock_move.ord
               ELSE  orders.id = stock_order.ord
            END
            
        ');*/





        /** Пункт назначения при перемещении */

        $qb->addSelect('destination.id AS destination')->addGroupBy('destination.id');
        $qb->leftJoin(
            'stock_move',
            ContactsRegionCall::TABLE,
            'destination',
            'destination.const = stock_move.destination AND EXISTS(SELECT 1 FROM '.ContactsRegion::TABLE.' WHERE event = destination.event)'
        );



        $qb->leftJoin(
            'destination',
            ContactsRegionCallTrans::TABLE,
            'destination_trans',
            'destination_trans.call = destination.id AND destination_trans.local = :local'
        );

        $qb->setParameter('local', $locale, Locale::TYPE);



        $qb->leftJoin(
            'orders',
            OrderEvent::TABLE,
            'orders_event',
            'orders_event.id = orders.event'
        );


        $qb->addSelect('order_user.profile AS order_client')->addGroupBy('order_user.profile');


        $qb->leftJoin(
            'orders',
            OrderUser::TABLE,
            'order_user',
            'order_user.event = orders.event'
        );


        $qb->leftJoin(
            'order_user',
            OrderDelivery::TABLE,
            'order_delivery',
            'order_delivery.orders_user = order_user.id'
        );

        $qb->leftJoin(
            'order_delivery',
            OrderDeliveryField::TABLE,
            'order_delivery_fields',
            'order_delivery_fields.delivery = order_delivery.id'
        );

        $qb->leftJoin(
            'order_delivery',
            DeliveryField::TABLE,
            'delivery_field',
            'delivery_field.id = order_delivery_fields.field'
        );

        $qb->leftJoin(
            'delivery_field',
            DeliveryFieldTrans::TABLE,
            'delivery_field_trans',
            'delivery_field_trans.field = delivery_field.id AND delivery_field_trans.local = :local'
        );

        $qb->setParameter('local', $locale, Locale::TYPE);



        /* Информация о доставке  */
        $qb->addSelect(
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

        $qb->join('stock', Product::TABLE, 'product', 'product.id = stock_product.product');

        
        $qb->addSelect('product_info.url AS product_url')->addGroupBy('product_info.url');
        $qb->join(
            'product',
            ProductInfo::TABLE,
            'product_info',
            'product_info.product = stock_product.product '
        );


        $qb->addSelect('product_trans.name AS product_name')->addGroupBy('product_trans.name');
        $qb->leftJoin(
            'product',
            ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product.event AND product_trans.local = :local'
        );

        $qb->setParameter('local', $locale, Locale::TYPE);



        /* Торговое предложение */

        $qb->addSelect('product_offer.value AS product_offer_value')->addGroupBy('product_offer.value');
        $qb->addSelect('product_offer.postfix AS product_offer_postfix')->addGroupBy('product_offer.postfix');


        $qb->leftJoin(
            'product',
            ProductOffer::TABLE,
            'product_offer',
            'product_offer.const = stock_product.offer AND product_offer.event = product.event'
        );



        /* Тип торгового предложения */

        $qb->addSelect('category_offer.reference AS product_offer_reference')->addGroupBy('category_offer.reference');
        $qb->addSelect('category_offer_trans.name AS product_offer_name')->addGroupBy('category_offer_trans.name');

        $qb->leftJoin(
            'product_offer',
            ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        /* Название торгового предложения */
        $qb->leftJoin(
            'category_offer',
            ProductCategoryOffersTrans::TABLE,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );

        
        /**
         * Множественный вариант
         */

        $qb->addSelect('product_variation.value AS product_variation_value')->addGroupBy('product_variation.value');
        $qb->addSelect('product_variation.postfix AS product_variation_postfix')->addGroupBy('product_variation.postfix');
        $qb->addSelect('category_variation.reference AS product_variation_reference')->addGroupBy('category_variation.reference');
        $qb->addSelect('category_variation_trans.name AS product_variation_name')->addGroupBy('category_variation_trans.name');


        $qb->leftJoin(
            'product_offer',
            ProductOfferVariation::TABLE,
            'product_variation',
            'stock_product.variation IS NOT NULL AND  product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
        );

        /* Получаем тип множественного варианта */

        $qb->leftJoin(
            'product_variation',
            ProductCategoryOffersVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );

        /* Получаем название множественного варианта */
        $qb->leftJoin(
            'category_variation',
            ProductCategoryOffersVariationTrans::TABLE,
            'category_variation_trans',
            'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
        );




        /**
         * Модификация множественного варианта торгового предложения
         */

        $qb->addSelect('product_modification.value AS product_modification_value')->addGroupBy('product_modification.value');
        $qb->addSelect('product_modification.postfix AS product_modification_postfix')->addGroupBy('product_modification.postfix');


        $qb->leftJoin(
            'product_variation',
            ProductOfferVariationModification::TABLE,
            'product_modification',
            'stock_product.modification IS NOT NULL AND product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification'
        );




        $qb->addSelect('category_modification.reference AS product_modification_reference')->addGroupBy('category_modification.reference');
        $qb->addSelect('category_modification_trans.name AS product_modification_name')->addGroupBy('category_modification_trans.name');

        $qb->leftJoin(
            'product_modification',
            ProductCategoryOffersVariationModification::TABLE,
            'category_modification',
            'category_modification.id = product_modification.category_modification'
        );

        /* Получаем название типа модификации */
        $qb->leftJoin(
            'category_modification',
            ProductCategoryOffersVariationModificationTrans::TABLE,
            'category_modification_trans',
            'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
        );





        /* Фото продукта */

        $qb->leftJoin(
            'product',
            ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product.event AND product_photo.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            ProductOfferImage::TABLE,
            'product_offer_image',
            'product_offer_image.offer = product_offer.id AND product_offer_image.root = true'
        );

        $qb->leftJoin(
            'product_variation',
            ProductOfferVariationImage::TABLE,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );

        $qb->leftJoin(
            'product_modification',
            ProductOfferVariationModificationImage::TABLE,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $qb->addSelect("
         CASE
               WHEN product_modification_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".ProductOfferVariationModificationImage::TABLE."' , '/', product_modification_image.dir, '/', product_modification_image.name, '.')
               WHEN product_variation_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".ProductOfferVariationImage::TABLE."' , '/', product_variation_image.dir, '/', product_variation_image.name, '.')
               WHEN product_offer_image.name IS NOT NULL THEN
                    CONCAT ( '/upload/".ProductOfferImage::TABLE."' , '/', product_offer_image.dir, '/', product_offer_image.name, '.')
               WHEN product_photo.name IS NOT NULL THEN
                    CONCAT ( '/upload/".ProductPhoto::TABLE."' , '/', product_photo.dir, '/', product_photo.name, '.')
               ELSE NULL
            END
           AS product_image")
            ->addGroupBy('product_modification_image.dir')

            ->addGroupBy('product_modification_image.name')
            ->addGroupBy('product_variation_image.dir')

            ->addGroupBy('product_variation_image.name')
            ->addGroupBy('product_offer_image.dir')

            ->addGroupBy('product_offer_image.name')
            ->addGroupBy('product_photo.dir')

            ->addGroupBy('product_photo.name')
        ;

        $qb->addSelect('
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
            ->addGroupBy('product_photo.ext')
        ;

        $qb->addSelect('
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
            ->addGroupBy('product_photo.cdn')
        ;



        //dd($this->connection->prepare('EXPLAIN (ANALYZE)  '.$qb->getSQL())->executeQuery($qb->getParameters())->fetchAllAssociativeIndexed());


        /* Кешируем результат DBAL */
        $cacheFilesystem = new FilesystemAdapter('ProductStocks');

        $config = $this->connection->getConfiguration();
        $config?->setResultCache($cacheFilesystem);

        return $this->connection->executeCacheQuery(
            $qb->getSQL(),
            $qb->getParameters(),
            $qb->getParameterTypes(),
            new QueryCacheProfile((60 * 60 * 24))
        )->fetchAllAssociative();

    }
}
