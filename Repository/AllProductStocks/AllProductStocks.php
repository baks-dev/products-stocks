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

namespace BaksDev\Products\Stocks\Repository\AllProductStocks;

use BaksDev\Contacts\Region\Entity as ContactsRegionEntity;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Products\Category\Entity as CategoryEntity;
use BaksDev\Products\Category\Type\Id\ProductCategoryUid;
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final class AllProductStocks implements AllProductStocksInterface
{
    private PaginatorInterface $paginator;
    private DBALQueryBuilder $DBALQueryBuilder;

    private ?ProductFilterDTO $filter = null;
    private ?SearchDTO $search = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function search(SearchDTO $search): static
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductFilterDTO $filter): static
    {
        $this->filter = $filter;
        return $this;
    }

    /** Метод возвращает полное состояние складских остатков продукции */
    public function fetchAllProductStocksAssociative(
        UserUid $user,
        UserProfileUid $profile,
    ): PaginatorInterface
    {
        /* */
        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $qb
            ->select('stock_product.total AS stock_total')
            ->addSelect('stock_product.reserve AS stock_reserve')
            ->from(ProductStockTotal::TABLE, 'stock_product')
            ->andWhere('(stock_product.usr = :usr OR stock_product.profile = :profile)')
            ->setParameter('usr', $user, UserUid::TYPE)
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        // Product
        $qb->addSelect('product.id as product_id'); //->addGroupBy('product.id');
        $qb->addSelect('product.event as product_event'); //->addGroupBy('product.event');
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

        $qb->addSelect('product_info.url AS product_url'); //->addGroupBy('product_info.url');

        $qb->leftJoin(
            'product_event',
            ProductEntity\Info\ProductInfo::TABLE,
            'product_info',
            'product_info.product = product.id'
        );

        // Product Trans
        $qb->addSelect('product_trans.name as product_name'); //->addGroupBy('product_trans.name');
        //$qb->addSelect('product_trans.description as product_description'); //->addGroupBy('product_trans.description');
        $qb->join(
            'product_event',
            ProductEntity\Trans\ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        // Торговое предложение

        $qb->addSelect('product_offer.id as product_offer_uid'); //->addGroupBy('product_offer.id');
        $qb->addSelect('product_offer.value as product_offer_value'); //->addGroupBy('product_offer.value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix'); //->addGroupBy('product_offer.postfix');

        $qb->leftJoin(
            'product_event',
            ProductEntity\Offers\ProductOffer::TABLE,
            'product_offer',
            'product_offer.event = product_event.id AND product_offer.const = stock_product.offer'
        );

        if($this->filter?->getOffer())
        {
            $qb->andWhere('product_offer.value = :offer');
            $qb->setParameter('offer', $this->filter->getOffer());
        }


        // Получаем тип торгового предложения
        $qb->addSelect('category_offer.reference as product_offer_reference'); //->addGroupBy('category_offer.reference');
        $qb->leftJoin(
            'product_offer',
            CategoryEntity\Offers\ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        // Множественные варианты торгового предложения

        $qb->addSelect('product_variation.id as product_variation_uid'); //->addGroupBy('product_variation.id');
        $qb->addSelect('product_variation.value as product_variation_value'); //->addGroupBy('product_variation.value');
        $qb->addSelect('product_variation.postfix as product_variation_postfix'); //->addGroupBy('product_variation.postfix');

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\ProductVariation::TABLE,
            'product_variation',
            'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
        );

        if($this->filter?->getVariation())
        {
            $qb->andWhere('product_variation.value = :variation');
            $qb->setParameter('variation', $this->filter->getVariation());
        }

        // Получаем тип множественного варианта
        $qb->addSelect('category_variation.reference as product_variation_reference'); //->addGroupBy('category_variation.reference');
        $qb->leftJoin(
            'product_variation',
            CategoryEntity\Offers\Variation\ProductCategoryVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );

        // Модификация множественного варианта торгового предложения

        $qb->addSelect('product_modification.id as product_modification_uid'); //->addGroupBy('product_modification.id');
        $qb->addSelect('product_modification.value as product_modification_value'); //->addGroupBy('product_modification.value');
        $qb->addSelect('product_modification.postfix as product_modification_postfix'); //->addGroupBy('product_modification.postfix');

        $qb->leftJoin(
            'product_variation',
            ProductEntity\Offers\Variation\Modification\ProductModification::TABLE,
            'product_modification',
            'product_modification.variation = product_variation.id  AND product_modification.const = stock_product.modification'
        );

        if($this->filter?->getModification())
        {
            $qb->andWhere('product_modification.value = :modification');
            $qb->setParameter('modification', $this->filter->getModification());
        }

        // Получаем тип модификации множественного варианта
        $qb->addSelect('category_offer_modification.reference as product_modification_reference'); //->addGroupBy('category_offer_modification.reference');
        $qb->leftJoin(
            'product_modification',
            CategoryEntity\Offers\Variation\Modification\ProductCategoryModification::TABLE,
            'category_offer_modification',
            'category_offer_modification.id = product_modification.category_modification'
        );

        // Артикул продукта

        $qb->addSelect(
            '
			CASE
			   WHEN product_modification.article IS NOT NULL THEN product_modification.article
			   WHEN product_variation.article IS NOT NULL THEN product_variation.article
			   WHEN product_offer.article IS NOT NULL THEN product_offer.article
			   WHEN product_info.article IS NOT NULL THEN product_info.article
			   ELSE NULL
			END AS product_article
		'
        )
            //            ->addGroupBy('product_modification.article')
            //            ->addGroupBy('product_variation.article')
            //            ->addGroupBy('product_offer.article')
            //            ->addGroupBy('product_info.article')
        ;

        // Фото продукта

        $qb->leftJoin(
            'product_modification',
            ProductEntity\Offers\Variation\Modification\Image\ProductModificationImage::TABLE,
            'product_modification_image',
            '
			product_modification_image.modification = product_modification.id AND
			product_modification_image.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\Image\ProductVariationImage::TABLE,
            'product_variation_image',
            '
			product_variation_image.variation = product_variation.id AND
			product_variation_image.root = true
			'
        );

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Image\ProductOfferImage::TABLE,
            'product_offer_images',
            '
			product_variation_image.name IS NULL AND
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
			 
			 WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Offers\Variation\Modification\Image\ProductModificationImage::TABLE."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductEntity\Offers\Variation\Image\ProductVariationImage::TABLE."' , '/', product_variation_image.name)
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
			
			    WHEN product_modification_image.name IS NOT NULL THEN  product_modification_image.ext
			   WHEN product_variation_image.name IS NOT NULL THEN product_variation_image.ext
			   WHEN product_offer_images.name IS NOT NULL THEN product_offer_images.ext
			   WHEN product_photo.name IS NOT NULL THEN product_photo.ext
				
			   ELSE NULL
			   
			END AS product_image_ext
		"
        );

        // Флаг загрузки файла CDN
        $qb->addSelect(
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

        // Категория
        $qb->leftJoin(
            'product_event',
            ProductEntity\Category\ProductCategory::TABLE,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        if($this->filter?->getCategory())
        {
            $qb->andWhere('product_event_category.category = :category');
            $qb->setParameter('category', $this->filter->getCategory(), ProductCategoryUid::TYPE);
        }

        $qb->leftJoin(
            'product_event_category',
            CategoryEntity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $qb->addSelect('category_trans.name AS category_name'); //->addGroupBy('category_trans.name');
        $qb->leftJoin(
            'category',
            CategoryEntity\Trans\ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );

        //        if($filter->getWarehouse())
        //        {
        //            $qb->andWhere('warehouse.const = :warehouse_filter');
        //            $qb->setParameter('warehouse_filter', $filter->getWarehouse(), ContactsRegionCallConst::TYPE);
        //        }



        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        //$qb->addSelect('users_profile.event as users_profile_event');
        $qb->join(
            'stock_product',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = stock_product.profile'
        );

        // Info
//        $qb->join(
//            'event',
//            UserProfileEntity\Info\UserProfileInfo::TABLE,
//            'users_profile_info',
//            'users_profile_info.profile = event.profile'
//        );

//        // Event
//        $qb->join(
//            'users_profile',
//            UserProfileEvent::TABLE,
//            'users_profile_event',
//            'users_profile_event.id = users_profile.event'
//        );

        // Personal
        $qb->addSelect('users_profile_personal.username AS users_profile_username');
        $qb->addSelect('users_profile_personal.location AS users_profile_location');

        $qb->join(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );

        // Поиск
        if($this->search->getQuery())
        {
            $qb
                ->createSearchQueryBuilder($this->search)
                //->addSearchEqualUid('warehouse.id')
                //->addSearchEqualUid('warehouse.event')
                //->addSearchLike('warehouse_trans.name')
                ->addSearchLike('users_profile_personal.username')
                ->addSearchLike('users_profile_personal.location')
                ->addSearchLike('product_trans.name')
                ->addSearchLike('category_trans.name')
            ;

        }

        $qb->addOrderBy('stock_product.profile');

        return $this->paginator->fetchAllAssociative($qb);

    }
}
