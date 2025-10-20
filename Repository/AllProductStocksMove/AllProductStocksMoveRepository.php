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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksMove;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory as ProductCategoryRoot;
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
use BaksDev\Products\Product\Entity\Property\ProductProperty;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\Property\ProductFilterPropertyDTO;
use BaksDev\Products\Product\Type\SearchTags\ProductSearchTag;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Forms\MoveFilter\Admin\ProductStockMoveFilterDTO;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Search\Index\SearchIndexInterface;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Generator;

final class AllProductStocksMoveRepository implements AllProductStocksMoveInterface
{
    private ?ProductFilterDTO $productFilter = null;

    private ?ProductStockMoveFilterDTO $filter = null;

    private ?SearchDTO $search = null;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorageInterface,
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly ?SearchIndexInterface $SearchIndexHandler = null
    ) {}

    public function search(SearchDTO $search): static
    {
        $this->search = $search;
        return $this;
    }

    public function productFilter(ProductFilterDTO $filter): static
    {
        $this->productFilter = $filter;
        return $this;
    }

    public function filter(ProductStockMoveFilterDTO $filter): static
    {
        $this->filter = $filter;
        return $this;
    }

    public function forProfile(UserProfileUid|UserProfile|false|null $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    private function builder(UserProfileUid $profile): DBALQueryBuilder
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('event.main AS id')
            ->addSelect('event.id AS event')
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->addSelect('event.fixed')
            ->from(ProductStockEvent::class, 'event')
            ->andWhere('event.status = :status ')
            ->setParameter(
                'status',
                ProductStockStatusMoving::class,
                ProductStockStatus::TYPE,
            );


        $dbal
            ->addSelect('invariable.profile AS user_profile_id')
            ->addSelect('invariable.number')
            ->join(
                'event',
                ProductStocksInvariable::class,
                'invariable',
                'invariable.event = event.id',
            );

        if($this->filter?->getProfile() instanceof UserProfileUid)
        {
            $dbal
                ->andWhere('
                    (invariable.profile = :profile AND move.destination = :filter_profile)
                    OR (invariable.profile = :filter_profile AND move.destination = :profile)
                ')
                ->setParameter(
                    key: 'filter_profile',
                    value: $this->filter->getProfile(),
                    type: UserProfileUid::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('(invariable.profile = :profile OR move.destination = :profile)');
        }

        $dbal->setParameter(
            key: 'profile',
            value: $this->profile instanceof UserProfileUid ? $this->profile : $this->UserProfileTokenStorageInterface->getProfile(),
            type: UserProfileUid::TYPE,
        );


        $dbal
            ->addSelect('stock.event AS is_warehouse')
            ->join(
                'event',
                ProductStock::class,
                'stock',
                'stock.event = event.id',
            );


        // ProductStockModify
        $dbal
            ->addSelect('modify.mod_date')
            ->leftJoin(
                'event',
                ProductStockModify::class,
                'modify',
                'modify.event = event.id',
            );


        $dbal
            ->addSelect('stock_product.id as product_stock_id')
            ->addSelect('stock_product.total')
            ->leftJoin(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = event.id',
            );


        // Product
        $dbal
            ->addSelect('product.id as product_id')
            ->addSelect('product.event as product_event')
            ->leftJoin(
                'stock_product',
                Product::class,
                'product',
                'product.id = stock_product.product',
            );

        // Product Event
        $dbal->leftJoin(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event',
        );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product_event',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id',
            );

        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local',
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
                'product_offer.event = product_event.id AND product_offer.const = stock_product.offer',
            );

        if($this->productFilter?->getOffer())
        {
            $dbal
                ->andWhere('product_offer.value = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->productFilter->getOffer(),
                );
        }

        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );


        // Множественные варианты торгового предложения

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation',
            );


        if($this->productFilter?->getVariation())
        {
            $dbal
                ->andWhere('product_variation.value = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->productFilter->getVariation(),
                );
        }

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_variation.category_variation',
            );

        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification',
            );

        if($this->productFilter?->getModification())
        {
            $dbal
                ->andWhere('product_modification.value = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->productFilter->getModification(),
                );
        }


        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
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
			');

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            '
                product_variation_image.variation = product_variation.id AND
                product_variation_image.root = true
			');

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            '
                product_variation_image.name IS NULL AND
                product_offer_images.offer = product_offer.id AND
                product_offer_images.root = true
			');

        $dbal->leftJoin(
            'product_offer',
            ProductPhoto::class,
            'product_photo',
            '
                product_offer_images.name IS NULL AND
                product_photo.event = product_event.id AND
                product_photo.root = true
			');

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
		",
        );

        // Расширение файла
        $dbal->addSelect(
            "
			CASE
		
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN  product_modification_image.ext
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.ext
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.ext
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.ext
				
			   ELSE NULL
			   
			END AS product_image_ext
		",
        );

        // Флаг загрузки файла CDN
        $dbal->addSelect(
            '
			CASE
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.cdn
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.cdn
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.cdn
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.cdn
			   
			   ELSE NULL
			END AS product_image_cdn
		',
        );

        // Категория
        $dbal->leftJoin(
            'product_event',
            ProductCategoryRoot::class,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true',
        );

        if($this->productFilter?->getCategory())
        {
            $dbal
                ->andWhere('product_event_category.category = :category')
                ->setParameter(
                    key: 'category',
                    value: $this->productFilter->getCategory(),
                    type: CategoryProductUid::TYPE,
                );
        }

        $dbal->leftJoin(
            'product_event_category',
            CategoryProduct::class,
            'category',
            'category.id = product_event_category.category',
        );


        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local',
            );


        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event',
            );


        /** Целевой склад */

        // UserProfile
        $dbal->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = invariable.profile',
            );

        // Info
        $dbal->leftJoin(
            'event',
            UserProfileInfo::class,
            'users_profile_info',
            'users_profile_info.profile = users_profile.id',
        );


        // Personal
        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event',
            );


        // Пункт назначения перемещения

        $dbal->leftJoin(
            'event',
            ProductStockMove::class,
            'move',
            'move.event = event.id AND move.ord IS NULL',
        );

        $dbal->leftJoin(
            'move',
            UserProfile::class,
            'users_profile_destination',
            'users_profile_destination.id = move.destination',
        );


        // Personal
        $dbal
            ->addSelect('users_profile_personal_destination.username AS users_profile_destination')
            ->leftJoin(
                'users_profile_destination',
                UserProfilePersonal::class,
                'users_profile_personal_destination',
                'users_profile_personal_destination.event = users_profile_destination.event',
            );


        /** Место хранения на складе и количество */

        /* Получаем наличие на указанном складе */
        $dbal
            ->addSelect('SUM(total.total) AS stock_total')
            ->addSelect("STRING_AGG(CONCAT(total.storage, ': [', total.total, ']'), ', ' ORDER BY total.total) AS stock_storage")
            ->leftJoin(
                'stock_product',
                ProductStockTotal::class,
                'total',
                '
                    total.profile = :profile AND
                    total.product = stock_product.product AND 
                    (total.offer IS NULL OR total.offer = stock_product.offer) AND 
                    (total.variation IS NULL OR total.variation = stock_product.variation) AND 
                    (total.modification IS NULL OR total.modification = stock_product.modification) AND
                    total.total > 0
            ');

        /**
         * Фильтр по свойства продукта
         */
        if($this->productFilter?->getProperty())
        {
            /** @var ProductFilterPropertyDTO $property */
            foreach($this->productFilter->getProperty() as $property)
            {
                if($property->getValue())
                {
                    $dbal->join(
                        'product',
                        ProductProperty::class,
                        'product_property_'.$property->getType(),
                        'product_property_'.$property->getType().'.event = product.event AND 
                        product_property_'.$property->getType().'.field = :'.$property->getType().'_const AND 
                        product_property_'.$property->getType().'.value = :'.$property->getType().'_value',
                    );

                    $dbal->setParameter($property->getType().'_const', $property->getConst());
                    $dbal->setParameter($property->getType().'_value', $property->getValue());
                }
            }
        }


        // Поиск
        if(($this->search instanceof SearchDTO) && $this->search->getQuery())
        {

            /** Поиск по индексам */
            $search = str_replace('-', ' ', $this->search->getQuery());

            /** Очистить поисковую строку от всех НЕ буквенных/числовых символов */
            $search = preg_replace('/[^ a-zа-яё\d]/ui', ' ', $search);
            $search = preg_replace('/\br(\d+)\b/i', '$1', $search);  // Заменяем R или r в начале строки, за которым следует цифра

            /** Задать префикс и суффикс для реализации варианта "содержит" */
            $search = '*'.trim($search).'*';

            /** Получим ids из индекса */
            $resultProducts = $this->SearchIndexHandler instanceof SearchIndexInterface
                ? $this->SearchIndexHandler->handleSearchQuery($search, ProductSearchTag::TAG)
                : false;

            if($this->SearchIndexHandler instanceof SearchIndexInterface && false === empty($resultProducts))
            {
                /** Фильтруем по полученным из индекса ids: */

                $ids = array_column($resultProducts, 'id');

                /** Товары */
                $dbal
                    ->andWhere('(
                        product.id IN (:uuids) 
                        OR product_offer.id IN (:uuids)
                        OR product_variation.id IN (:uuids) 
                        OR product_modification.id IN (:uuids)
                    )')
                    ->setParameter(
                        key: 'uuids',
                        value: $ids,
                        type: ArrayParameterType::STRING,
                    );

                $dbal->addOrderBy('CASE WHEN product.id IN (:uuids) THEN 0 ELSE 1 END');
                $dbal->addOrderBy('CASE WHEN product_offer.id IN (:uuids) THEN 0 ELSE 1 END');
                $dbal->addOrderBy('CASE WHEN product_variation.id IN (:uuids)  THEN 0 ELSE 1 END');
                $dbal->addOrderBy('CASE WHEN product_modification.id IN (:uuids)  THEN 0 ELSE 1 END');
            }

            if(empty($resultProducts))
            {
                $dbal
                    ->createSearchQueryBuilder($this->search)
                    ->addSearchLike('invariable.number')
                    ->addSearchLike('product_modification.article')
                    ->addSearchLike('product_variation.article')
                    ->addSearchLike('product_offer.article')
                    ->addSearchLike('product_info.article');
            }
        }


        /** Сортируем по дате, в первую очередь закрываем все старые заявки */
        $dbal->orderBy('modify.mod_date');

        if($this->filter?->getDate())
        {
            $date = $this->filter->getDate();

            $dbal
                ->andWhere('DATE(modify.mod_date) BETWEEN :start AND :end')
                ->setParameter('start', $date, Types::DATE_IMMUTABLE)
                ->setParameter('end', $date, Types::DATE_IMMUTABLE);
        }

        $dbal->allGroupByExclude();

        return $dbal;
    }


    /**
     * Метод возвращает все заявки, требующие перемещения между складами в виде ассоциативного массива
     */
    public function findPaginator(UserProfileUid|UserProfile|false|null $profile): PaginatorInterface
    {
        $dbal = $this->builder($profile);

        return $this->paginator->fetchAllAssociative($dbal);
    }

    /**
     * Метод возвращает все заявки, требующие перемещения между складами в виде резалтов
     */
    public function findResult(UserProfileUid $profile): Generator
    {
        $dbal = $this->builder($profile);

        return $dbal->fetchAllHydrate(AllProductStocksMoveResult::class);
    }
}
