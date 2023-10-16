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
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllProductStocks implements AllProductStocksInterface
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

    /** Метод возвращает полное состояние складских остатков продукции */
    public function fetchAllProductStocksAssociative(
        SearchDTO $search,
        ProductsStocksFilterInterface $filter,
        ?UserProfileUid $profile
    ): PaginatorInterface
    {
        /* */
        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal()
        ;

        $qb->select('stock_product.total AS stock_total');
        $qb->addSelect('stock_product.reserve AS stock_reserve');
        $qb->from(ProductStockTotal::TABLE, 'stock_product');

        // Warehouse
        $exist = $this->DBALQueryBuilder->builder();
        $exist->select('1');
        $exist->from(ContactsRegionEntity\ContactsRegion::TABLE, 'tmp');
        $exist->where('tmp.event = warehouse.event');

        $qb->addSelect('warehouse.id as warehouse_id'); //->addGroupBy('warehouse.id');
        $qb->addSelect('warehouse.event as warehouse_event'); //->addGroupBy('warehouse.event');
        $qb->join(
            'stock_product',
            ContactsRegionEntity\Call\ContactsRegionCall::TABLE,
            'warehouse',
            'warehouse.const = stock_product.warehouse AND EXISTS('.$exist->getSQL().')'.($profile ? ' AND warehouse.profile = :profile' : '')
        );

        if($profile)
        {
            $qb->setParameter('profile', $profile, UserProfileUid::TYPE);
        }


        // Product Warehouse Trans
        $qb->addSelect('warehouse_trans.name AS warehouse_name'); //->addGroupBy('warehouse_trans.name');

        $qb->join(
            'warehouse',
            ContactsRegionEntity\Call\Trans\ContactsRegionCallTrans::TABLE,
            'warehouse_trans',
            'warehouse_trans.call = warehouse.id AND warehouse_trans.local = :local'
        );

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

        // Получаем тип торгового предложения
        $qb->addSelect('category_offer.reference as product_offer_reference'); //->addGroupBy('category_offer.reference');
        $qb->leftJoin(
            'product_offer',
            CategoryEntity\Offers\ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        // Множественные варианты торгового предложения

        $qb->addSelect('product_offer_variation.id as product_variation_uid'); //->addGroupBy('product_offer_variation.id');
        $qb->addSelect('product_offer_variation.value as product_variation_value'); //->addGroupBy('product_offer_variation.value');
        $qb->addSelect('product_offer_variation.postfix as product_variation_postfix'); //->addGroupBy('product_offer_variation.postfix');

        $qb->leftJoin(
            'product_offer',
            ProductEntity\Offers\Variation\ProductVariation::TABLE,
            'product_offer_variation',
            'product_offer_variation.offer = product_offer.id AND product_offer_variation.const = stock_product.variation'
        );

        // Получаем тип множественного варианта
        $qb->addSelect('category_offer_variation.reference as product_variation_reference'); //->addGroupBy('category_offer_variation.reference');
        $qb->leftJoin(
            'product_offer_variation',
            CategoryEntity\Offers\Variation\ProductCategoryVariation::TABLE,
            'category_offer_variation',
            'category_offer_variation.id = product_offer_variation.category_variation'
        );

        // Модификация множественного варианта торгового предложения

        $qb->addSelect('product_offer_modification.id as product_modification_uid'); //->addGroupBy('product_offer_modification.id');
        $qb->addSelect('product_offer_modification.value as product_modification_value'); //->addGroupBy('product_offer_modification.value');
        $qb->addSelect('product_offer_modification.postfix as product_modification_postfix'); //->addGroupBy('product_offer_modification.postfix');

        $qb->leftJoin(
            'product_offer_variation',
            ProductEntity\Offers\Variation\Modification\ProductModification::TABLE,
            'product_offer_modification',
            'product_offer_modification.variation = product_offer_variation.id  AND product_offer_modification.const = stock_product.modification'
        );

        // Получаем тип модификации множественного варианта
        $qb->addSelect('category_offer_modification.reference as product_modification_reference'); //->addGroupBy('category_offer_modification.reference');
        $qb->leftJoin(
            'product_offer_modification',
            CategoryEntity\Offers\Variation\Modification\ProductCategoryModification::TABLE,
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
        )
            //            ->addGroupBy('product_offer_modification.article')
            //            ->addGroupBy('product_offer_variation.article')
            //            ->addGroupBy('product_offer.article')
            //            ->addGroupBy('product_info.article')
        ;

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

        $qb->addSelect('category_trans.name AS category_name'); //->addGroupBy('category_trans.name');
        $qb->leftJoin(
            'category',
            CategoryEntity\Trans\ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );

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
                ->addSearchEqualUid('warehouse.id')
                ->addSearchEqualUid('warehouse.event')
                ->addSearchLike('warehouse_trans.name')
                ->addSearchLike('category_trans.name');

        }

        return $this->paginator->fetchAllAssociative($qb);

    }
}
