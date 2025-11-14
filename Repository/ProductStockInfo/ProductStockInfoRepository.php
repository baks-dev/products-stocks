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

namespace BaksDev\Products\Stocks\Repository\ProductStockInfo;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class ProductStockInfoRepository implements ProductStockInfoInterface
{

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
    ) {}


    public function forProfile(UserProfileUid|UserProfile $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }


    public function find(): ProductStockInfoResult|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        /* Получить максимальное кол-во */
        $dbal
            ->select('stock_total.id AS stock_id')
            ->addSelect('(stock_total.total - stock_total.reserve) AS max_stock_total')
            ->addSelect('stock_total.profile AS max_stock_profile')
            ->from(ProductStockTotal::class, 'stock_total')
            ->where('stock_total.profile != :profile');


        /* Получить минимальное кол-во */
        $dbal
            ->addSelect("
                COALESCE(
                    NULLIF(current_stock_total.total, 0),
                    0
                ) AS min_stock_total
            ")
            ->leftJoin(
                'stock_total',
                ProductStockTotal::class,
                'current_stock_total',
                '
                current_stock_total.profile = :profile
                AND current_stock_total.product = stock_total.product
                AND current_stock_total.offer = stock_total.offer
                AND current_stock_total.variation = stock_total.variation
                AND current_stock_total.modification = stock_total.modification
            ',
            )
            ->orderBy('stock_total.total', 'DESC')
            ->having('(SUM(stock_total.total) - SUM(stock_total.reserve)) > 100 ');


        /* Товар не находится в перемещениях */

        $dbal->leftJoin(
            'stock_total',
            ProductStockProduct::class,
            'stock_product',
            'stock_total.product = stock_product.product'
        );

        $dbal
            ->leftJoin(
                'stock_product',
                ProductStockEvent::class,
                'stock_event',
                'stock_product.event = stock_event.id AND stock_event.status <> :status'
            )
            ->setParameter(
                'status',
                ProductStockStatusMoving::class,
                ProductStockStatus::TYPE,
            );

        /* Destination */
        $dbal->leftJoin(
            'stock_event',
            ProductStockMove::class,
            'move',
            'move.event = stock_event.id AND move.ord IS NULL',
        );

        $dbal
            ->leftJoin(
                'move',
                UserProfile::class,
                'users_profile_destination',
                'users_profile_destination.id = move.destination AND move.destination = :profile',
            );

        $dbal->setParameter(
            key: 'profile',
            value: $this->profile instanceof UserProfileUid ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
            type: UserProfileUid::TYPE,
        );


        /* Product */
        $dbal
            ->addSelect('product.id as product_id')
            ->join(
                'stock_total',
                Product::class,
                'product',
                'product.id = stock_total.product',
            );

        /* Product Event */
        $dbal->join(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event',
        );

        $dbal
            ->leftJoin(
                'product_event',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id',
            );

        /* Product Trans */
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local',
            );


        /* Торговое предложение */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->addSelect('product_offer.const as product_offer_const')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product_event.id AND product_offer.const = stock_total.offer',
            );


        /* Получаем тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );

        /* Множественные варианты торгового предложения */
        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->addSelect('product_variation.const as product_variation_const')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_total.variation',
            );


        /* Получаем тип множественного варианта */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation',
            );


        /* Модификация множественного варианта торгового предложения */

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->addSelect('product_modification.const as product_modification_const')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id  AND product_modification.const = stock_total.modification',
            );


        /* Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
            );

        /* Артикул продукта */

        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article 
		');


        /** Ответственное лицо (Склад) */

        $dbal
            ->join(
                'stock_total',
                UserProfile::class,
                'users_profile',
                'users_profile.id = stock_total.profile',
            );

        $dbal
            ->addSelect('users_profile_personal.username AS max_stock_username')
            ->join(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event',
            );

        $dbal->setMaxResults(1);
        $dbal->allGroupByExclude();


        return $dbal->fetchHydrate(ProductStockInfoResult::class);

    }

}