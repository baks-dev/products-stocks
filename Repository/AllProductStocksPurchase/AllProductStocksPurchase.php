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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPurchase;

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Core\Services\Switcher\SwitcherInterface;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Products\Category\Entity as CategoryEntity;
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Stocks\Entity as ProductStockEntity;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Groups\Group\Entity as GroupEntity;
use BaksDev\Users\Groups\Users\Entity as CheckUsersEntity;
use BaksDev\Users\Profile\UserProfile\Entity as UserProfileEntity;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AllProductStocksPurchase implements AllProductStocksPurchaseInterface
{
    private Connection $connection;
    private TranslatorInterface $translator;
    private SwitcherInterface $switcher;
    private PaginatorInterface $paginator;

    public function __construct(
        Connection          $connection,
        TranslatorInterface $translator,
        SwitcherInterface   $switcher,
        PaginatorInterface  $paginator,
    )
    {
        $this->connection = $connection;
        $this->translator = $translator;
        $this->switcher = $switcher;
        $this->paginator = $paginator;
    }

    public function fetchAllProductStocksAssociative(SearchDTO $search, ?UserProfileUid $profile): PaginatorInterface
    {
        $qb = $this->connection->createQueryBuilder();

        // Stock

        // ProductStock
        $qb->select('stock.id');
        $qb->addSelect('stock.event');

        $qb->from(ProductStockEntity\ProductStock::TABLE, 'stock');

        // ProductStockEvent
        // $qb->addSelect('event.total');
        $qb->addSelect('event.number');
        $qb->addSelect('event.comment');
        $qb->addSelect('event.status');
        $qb->join(
            'stock',
            ProductStockEntity\Event\ProductStockEvent::TABLE,
            'event',
            'event.id = stock.event AND event.status = :status' . ($profile ? ' AND event.profile = :profile' : '')
        );



        if ($profile) {
            $qb->setParameter('profile', $profile, UserProfileUid::TYPE);
        }

        $qb->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusPurchase()), ProductStockStatus::TYPE);




        // ProductStockModify
        $qb->addSelect('modify.mod_date');
        $qb->join('event', ProductStockEntity\Modify\ProductStockModify::TABLE, 'modify', 'modify.event = stock.event');

        $qb->addSelect('stock_product.id as product_stock_id');
        $qb->addSelect('stock_product.total');
        //$qb->addSelect('stock_product.package');
        $qb->join(
            'event',
            ProductStockEntity\Products\ProductStockProduct::TABLE,
            'stock_product',
            'stock_product.event = stock.event'
        );

        $qb->setParameter('local', new Locale($this->translator->getLocale()), Locale::TYPE);


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

        $qb->addSelect('product_info.url AS product_url');

        $qb->leftJoin(
            'product_event',
            ProductEntity\Info\ProductInfo::TABLE,
            'product_info',
            'product_info.product = product.id'
        );

        // Product Trans
        $qb->addSelect('product_trans.name as product_name');
        $qb->addSelect('product_trans.description as product_description');
        $qb->join(
            'product_event',
            ProductEntity\Trans\ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        // Торговое предложение

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

        // Множественные варианты торгового предложения

        $qb->addSelect('product_offer_variation.id as product_variation_uid');
        $qb->addSelect('product_offer_variation.value as product_variation_value');
        $qb->addSelect('product_offer_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\ProductOfferVariation::TABLE,
            'product_offer_variation',
            'product_offer_variation.offer = product_offer.id AND product_offer_variation.const = stock_product.variation'
        );

        // Получаем тип множественного варианта
        $qb->addSelect('category_offer_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_offer_variation',
            CategoryEntity\Offers\Variation\ProductCategoryOffersVariation::TABLE,
            'category_offer_variation',
            'category_offer_variation.id = product_offer_variation.category_variation'
        );

        // Модификация множественного варианта торгового предложения

        $qb->addSelect('product_offer_modification.id as product_modification_uid');
        $qb->addSelect('product_offer_modification.value as product_modification_value');
        $qb->addSelect('product_offer_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'product_offer_variation',
            ProductEntity\Offers\Variation\Modification\ProductOfferVariationModification::TABLE,
            'product_offer_modification',
            'product_offer_modification.variation = product_offer_variation.id AND product_offer_modification.const = stock_product.modification'
        );

        // Получаем тип модификации множественного варианта
        $qb->addSelect('category_offer_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_offer_modification',
            CategoryEntity\Offers\Variation\Modification\ProductCategoryOffersVariationModification::TABLE,
            'category_offer_modification',
            'category_offer_modification.id = product_offer_modification.category_modification'
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
            ProductEntity\Offers\Variation\Modification\Image\ProductOfferVariationModificationImage::TABLE,
            'product_offer_modification_image',
            '
			product_offer_modification_image.modification = product_offer_modification.id AND
			product_offer_modification_image.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\Image\ProductOfferVariationImage::TABLE,
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
					CONCAT ( '/upload/" . ProductEntity\Offers\Variation\Modification\Image\ProductOfferVariationModificationImage::TABLE . "' , '/', product_offer_modification_image.dir, '/', product_offer_modification_image.name, '.')
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/" . ProductEntity\Offers\Variation\Image\ProductOfferVariationImage::TABLE . "' , '/', product_offer_variation_image.dir, '/', product_offer_variation_image.name, '.')
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/" . ProductEntity\Offers\Image\ProductOfferImage::TABLE . "' , '/', product_offer_images.dir, '/', product_offer_images.name, '.')
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/" . ProductEntity\Photo\ProductPhoto::TABLE . "' , '/', product_photo.dir, '/', product_photo.name, '.')
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

        // Product Cover
//        $qb->addSelect('product_photo.name AS cover');
//        $qb->addSelect('product_photo.ext');
//        $qb->addSelect('product_photo.cdn');
//        $qb->addSelect('product_photo.dir');
//        $qb->leftJoin(
//            'product_event',
//            ProductEntity\Photo\ProductPhoto::TABLE,
//            'product_photo',
//            'product_photo.event = product_event.id');

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

        $qb->addSelect("CONCAT ( '/upload/" . UserProfileEntity\Avatar\UserProfileAvatar::TABLE . "' , '/', users_profile_avatar.dir, '/', users_profile_avatar.name, '.') AS users_profile_avatar");
        $qb->addSelect("CASE WHEN users_profile_avatar.cdn THEN  CONCAT ( 'small.', users_profile_avatar.ext) ELSE users_profile_avatar.ext END AS users_profile_avatar_ext");
        $qb->addSelect('users_profile_avatar.cdn AS users_profile_avatar_cdn');

        $qb->leftJoin(
            'users_profile_event',
            UserProfileEntity\Avatar\UserProfileAvatar::TABLE,
            'users_profile_avatar',
            'users_profile_avatar.event = users_profile_event.id'
        );

        // Группа

        $qb->join(
            'users_profile_info',
            CheckUsersEntity\CheckUsers::TABLE,
            'check_user',
            'check_user.user_id = users_profile_info.user_id'
        );

        $qb->join(
            'check_user',
            CheckUsersEntity\Event\CheckUsersEvent::TABLE,
            'check_user_event',
            'check_user_event.id = check_user.event'
        );

        $qb->leftJoin(
            'check_user_event',
            GroupEntity\Group::TABLE,
            'groups',
            'groups.id = check_user_event.group_id'
        );

        $qb->addSelect('groups_trans.name AS group_name'); // Название группы

        $qb->leftJoin(
            'groups',
            GroupEntity\Trans\GroupTrans::TABLE,
            'groups_trans',
            'groups_trans.event = groups.event AND groups_trans.local = :local'
        );

        // Поиск
        if ($search->query) {
            $search->query = mb_strtolower($search->query);

            $searcher = $this->connection->createQueryBuilder();

            $searcher->orWhere('LOWER(event.number) LIKE :query');

//            $searcher->orWhere('LOWER(region_trans.name) LIKE :query');
//            $searcher->orWhere('LOWER(region_trans.name) LIKE :switcher');

            $qb->andWhere('(' . $searcher->getQueryPart('where') . ')');
            $qb->setParameter('query', '%' . $this->switcher->toRus($search->query) . '%');
            $qb->setParameter('switcher', '%' . $this->switcher->toEng($search->query) . '%');
        }

        $qb->orderBy('modify.mod_date', 'DESC');
        //$qb->addOrderBy('stock.number', 'DESC');

        return $this->paginator->fetchAllAssociative($qb);
    }
}
