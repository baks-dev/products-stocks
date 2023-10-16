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

namespace BaksDev\Products\Stocks\Repository\OrderMoveDetail;

use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Delivery\Entity as DeliveryEntity;
use BaksDev\Orders\Order\Entity as OrderEntity;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Category\Entity\Offers\ProductCategoryOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\ProductCategoryOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\ProductCategoryModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\ProductCategoryModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\ProductCategoryVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\ProductCategoryVariationTrans;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Address\Entity\GeocodeAddress;
use BaksDev\Users\Profile\TypeProfile\Entity as TypeProfileEntity;
use BaksDev\Users\Profile\UserProfile\Entity as UserProfileEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderMoveDetail implements OrderMoveDetailInterface
{
    private EntityManagerInterface $entityManager;

    private TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator)
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    public function fetchDetailOrderAssociative(OrderUid $order): ?array
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();

        /** ЛОКАЛЬ */
        $locale = new Locale($this->translator->getLocale());
        $qb->setParameter('local', $locale, Locale::TYPE);


        $qb->from(ProductStockMove::TABLE, 'move');

        $qb->addSelect('move_event.status AS move_status');
        $qb->join('move', ProductStockEvent::TABLE, 'move_event', 'move_event.id = move.event AND move_event.status = :status');

        /** Только собранная заявка */

        $qb->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusExtradition()), ProductStockStatus::TYPE);

        $qb->where('move.ord = :order');
        $qb->setParameter('order', $order, OrderUid::TYPE);

        //dd($qb->fetchAssociative());






        $qb->select('orders.id AS order_id')->addGroupBy('orders.id');
        $qb->addSelect('orders.event AS order_event')->addGroupBy('orders.event');
        $qb->addSelect('orders.number AS order_number')->addGroupBy('orders.number');

        $qb->from(OrderEntity\Order::TABLE, 'orders');



        $qb->addSelect('event.status AS order_status')->addGroupBy('event.status');

        $qb->join('orders',
            OrderEntity\Event\OrderEvent::TABLE, 'event', 'event.id = orders.event');

        $qb->leftJoin(
            'orders',
            OrderEntity\User\OrderUser::TABLE,
            'order_user',
            'order_user.event = orders.event'
        );


        /* Продукция в заказе  */

        $qb->leftJoin(
            'orders',
            OrderEntity\Products\OrderProduct::TABLE,
            'order_product',
            'order_product.event = orders.event'
        );

        $qb->leftJoin(
            'order_product',
            OrderEntity\Products\Price\OrderPrice::TABLE,
            'order_product_price',
            'order_product_price.product = order_product.id'
        );





        $qb->leftJoin(
            'order_product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = order_product.product'
        );




        $qb->leftJoin(
            'product_event',
            ProductInfo::TABLE,
            'product_info',
            'product_info.product = product_event.main '
        )->addGroupBy('product_info.article');

        

        $qb->leftJoin(
            'product_event',
            ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );




        /** Торговое предложение */
        $qb->leftJoin(
            'product_event',
            ProductOffer::TABLE,
            'product_offer',
            'product_offer.id = order_product.offer AND product_offer.event = product_event.id'
        );


        /** Тип торгового предложения */
        $qb->leftJoin(
            'product_offer',
            ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        /** Название торгового предложения */
        $qb->leftJoin(
            'category_offer',
            ProductCategoryOffersTrans::TABLE,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );



        /** Множественный вариант */



        $qb->leftJoin(
            'product_offer',
            ProductVariation::TABLE,
            'product_variation',
            'product_variation.id = order_product.variation AND product_variation.offer = product_offer.id'
        );

        /* Получаем тип множественного варианта */

        $qb->leftJoin(
            'product_variation',
            ProductCategoryVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );

        /* Получаем название множественного варианта */
        $qb->leftJoin(
            'category_variation',
            ProductCategoryVariationTrans::TABLE,
            'category_variation_trans',
            'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
        );




        /* Получаем тип модификации множественного варианта */

        $qb->leftJoin(
            'product_variation',
            ProductModification::TABLE,
            'product_modification',
            'product_modification.id = order_product.modification AND product_modification.variation = product_variation.id'
        );

        $qb->leftJoin(
            'product_modification',
            ProductCategoryModification::TABLE,
            'category_modification',
            'category_modification.id = product_modification.category_modification'
        );

        /* Получаем название типа модификации */
        $qb->leftJoin(
            'category_modification',
            ProductCategoryModificationTrans::TABLE,
            'category_modification_trans',
            'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
        );





        /* Фото продукта */

        $qb->leftJoin(
            'product_event',
            ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            ProductOfferImage::TABLE,
            'product_offer_image',
            'product_offer_image.offer = product_offer.id AND product_offer_image.root = true'
        );

        $qb->leftJoin(
            'product_variation',
            ProductVariationImage::TABLE,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );

        $qb->leftJoin(
            'product_modification',
            ProductModificationImage::TABLE,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );



        $qb->addSelect(
            "JSON_AGG
			( DISTINCT
				
					JSONB_BUILD_OBJECT
					(
						/* свойства для сортировки JSON */
						'product_id', order_product.id,
						'product_url', product_info.url,
						'product_name', product_trans.name,
						
						'product_offer_reference', category_offer.reference,
						'product_offer_name', category_offer_trans.name,
						'product_offer_value', product_offer.value,
						'product_offer_postfix', product_offer.postfix,
			
						
						'product_variation_reference', category_variation.reference,
						'product_variation_name', category_variation_trans.name,
						'product_variation_value', product_variation.value,
						'product_variation_postfix', product_variation.postfix,
						
						'product_modification_reference', category_modification.reference,
						'product_modification_name', category_modification_trans.name,
						'product_modification_value', product_modification.value,
						'product_modification_postfix', product_modification.postfix,
						
						'product_image', CASE
						                   WHEN product_modification_image.name IS NOT NULL THEN
                                                CONCAT ( '/upload/".ProductModificationImage::TABLE."' , '/',  product_modification_image.name)
                                           WHEN product_variation_image.name IS NOT NULL THEN
                                                CONCAT ( '/upload/".ProductVariationImage::TABLE."' , '/',  product_variation_image.name)
                                           WHEN product_offer_image.name IS NOT NULL THEN
                                                CONCAT ( '/upload/".ProductOfferImage::TABLE."' , '/', product_offer_image.name)
                                           WHEN product_photo.name IS NOT NULL THEN
                                                CONCAT ( '/upload/".ProductPhoto::TABLE."' , '/', product_photo.name)
                                           ELSE NULL
                                        END,
                                        
						'product_image_ext', CASE
						                        WHEN product_modification_image.name IS NOT NULL THEN
                                                    product_modification_image.ext
                                               WHEN product_variation_image.name IS NOT NULL THEN
                                                    product_variation_image.ext
                                               WHEN product_offer_image.name IS NOT NULL THEN
                                                    product_offer_image.ext
                                               WHEN product_photo.name IS NOT NULL THEN
                                                    product_photo.ext
                                               ELSE NULL
                                            END,
                                            
                        'product_image_cdn', CASE
                                                WHEN product_modification_image.name IS NOT NULL THEN
                                                    product_modification_image.cdn
                                               WHEN product_variation_image.name IS NOT NULL THEN
                                                    product_variation_image.cdn
                                               WHEN product_offer_image.name IS NOT NULL THEN
                                                    product_offer_image.cdn
                                               WHEN product_photo.name IS NOT NULL THEN
                                                    product_photo.cdn
                                               ELSE NULL
                                            END,

						'product_total', order_product_price.total,
						'product_price', order_product_price.price,
						'product_price_currency', order_product_price.currency
					)
			
			)
			AS order_products"
        );


        /* Доставка */

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
            'delivery_event.id = order_delivery.event'
        );

        $qb->addSelect('delivery_price.price AS delivery_price')
            ->addGroupBy('delivery_price.price');
        $qb->leftJoin(
            'delivery_event',
            DeliveryEntity\Price\DeliveryPrice::TABLE,
            'delivery_price',
            'delivery_price.event = delivery_event.id'
        );

        /* Адрес доставки */

        $qb->addSelect('delivery_geocode.longitude AS delivery_geocode_longitude')->addGroupBy('delivery_geocode.longitude');
        $qb->addSelect('delivery_geocode.latitude AS delivery_geocode_latitude')->addGroupBy('delivery_geocode.latitude');
        $qb->addSelect('delivery_geocode.address AS delivery_geocode_address')->addGroupBy('delivery_geocode.address');

        $qb->leftJoin(
            'order_delivery',
            GeocodeAddress::TABLE,
            'delivery_geocode',
            'delivery_geocode.latitude = order_delivery.latitude AND delivery_geocode.longitude = order_delivery.longitude'
        );

        /* Профиль пользователя */

        $qb->leftJoin(
            'order_user',
            UserProfileEntity\Event\UserProfileEvent::TABLE,
            'user_profile',
            'user_profile.id = order_user.profile'
        );

        $qb->addSelect('user_profile_info.discount AS order_profile_discount')->addGroupBy('user_profile_info.discount');

        $qb->leftJoin(
            'user_profile',
            UserProfileEntity\Info\UserProfileInfo::TABLE,
            'user_profile_info',
            'user_profile_info.profile = user_profile.profile'
        );

        $qb->leftJoin(
            'user_profile',
            UserProfileEntity\Value\UserProfileValue::TABLE,
            'user_profile_value',
            'user_profile_value.event = user_profile.id'
        );

        $qb->leftJoin(
            'user_profile',
            TypeProfileEntity\TypeProfile::TABLE,
            'type_profile',
            'type_profile.id = user_profile.type'
        );

        $qb->addSelect('type_profile_trans.name AS order_profile')->addGroupBy('type_profile_trans.name');
        $qb->leftJoin(
            'type_profile',
            TypeProfileEntity\Trans\TypeProfileTrans::TABLE,
            'type_profile_trans',
            'type_profile_trans.event = type_profile.event AND type_profile_trans.local = :local'
        );

        $qb->join(
            'user_profile_value',
            TypeProfileEntity\Section\Fields\TypeProfileSectionField::TABLE,
            'type_profile_field',
            'type_profile_field.id = user_profile_value.field AND type_profile_field.card = true'
        );

        $qb->leftJoin(
            'type_profile_field',
            TypeProfileEntity\Section\Fields\Trans\TypeProfileSectionFieldTrans::TABLE,
            'type_profile_field_trans',
            'type_profile_field_trans.field = type_profile_field.id AND type_profile_field_trans.local = :local'
        );

        /* Автарка профиля клиента */
        $qb->addSelect("CONCAT ( '/upload/".UserProfileEntity\Avatar\UserProfileAvatar::TABLE."' , '/', profile_avatar.name) AS profile_avatar_name")
            ->addGroupBy('profile_avatar.name');

        $qb->addSelect('profile_avatar.ext AS profile_avatar_ext')->addGroupBy('profile_avatar.ext');
        $qb->addSelect('profile_avatar.cdn AS profile_avatar_cdn')->addGroupBy('profile_avatar.cdn');

        $qb->leftJoin(
            'user_profile',
            UserProfileEntity\Avatar\UserProfileAvatar::TABLE,
            'profile_avatar',
            'profile_avatar.event = user_profile.id'
        );




        $qb->addSelect(
            "JSON_AGG
			( DISTINCT
				
					JSONB_BUILD_OBJECT
					(
						/* свойства для сортирвоки JSON */
						'0', type_profile_field.sort,

						'profile_type', type_profile_field.type,
						'profile_name', type_profile_field_trans.name,
						'profile_value', user_profile_value.value
					)
				
			)
			AS order_user"
        );




        $qb->where('orders.id = :order');
        $qb->setParameter('order', $order, OrderUid::TYPE);

        return $qb->fetchAssociative();
    }

    public function getDetailOrder(OrderUid $order): mixed
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('orders');
        $qb->from(OrderEntity\Order::class, 'orders');
        $qb->where('orders.id = :order');
        $qb->setParameter('order', $order, OrderUid::TYPE);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
