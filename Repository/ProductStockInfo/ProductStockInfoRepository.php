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
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;

final class ProductStockInfoRepository implements ProductStockInfoInterface
{

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}


    public function find(): ProductStockInfoResult|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('stock_total.id AS stock_id')
            ->addSelect('MAX(stock_total.total) AS max_stock_total')
            ->addSelect('MIN(stock_total.total) AS min_stock_total')
            ->addSelect('stock_total.profile AS users_profile_id')
            ->from(ProductStockTotal::class, 'stock_total')
            ->andWhere('stock_total.total != 0');


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
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->join(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event',
            );


        $dbal->allGroupByExclude();

        $results = $dbal->fetchAllAssociative();

        if(true === empty($results))
        {
            return false;
        }

        $result = $this->preparedStocks($results);

        return $result !== false ? new ProductStockInfoResult(...$result) : false;

    }


    /**
     * Подготовить данные по максимальному и минимальному значениям с учетом разных профилей
     */
    private function preparedStocks(array $results): array|false
    {

        $stocksByArticle = [];

        /* Получить по артикулу остатки товара */
        foreach($results as $result)
        {
            $stocksByArticle[$result['product_article']][] = $result;
        }


        $stocksByArticleFiltered = [];

        /* Пройти по данным для получения данных по максимальным и минимальным значениям */
        foreach($stocksByArticle as $key => $stocksByArticleItem)
        {

            /* Найти минимальные и максимальные в наличии */
            $max_total = max(array_column($stocksByArticleItem, 'max_stock_total'));
            $min_total = min(array_column($stocksByArticleItem, 'min_stock_total'));


            $result = [];

            /* Найти значения с максимальным и минимальным наличием */
            foreach($stocksByArticleItem as $items)
            {

                /* Сформировать данные по маскимальному значению */
                if($items['max_stock_total'] == $max_total)
                {
                    $result['max_stock_profile'] = $items['users_profile_id'];
                    $result['max_stock_total'] = $max_total;
                    $result['max_stock_username'] = $items['users_profile_username'];
                }

                /* Сформировать данные по минимальному значению */
                if($items['min_stock_total'] == $min_total)
                {
                    $result['min_stock_profile'] = $items['users_profile_id'];
                    $result['min_stock_total'] = $min_total;
                }

            }

            /* Проверить чтобы профили магазинов отличались а также максимальное кол-во должно быть больше 100 */
            if($result['min_stock_profile'] !== $result['max_stock_profile'] && $max_total > 100)
            {
                $result = $result + $items;
                $stocksByArticleFiltered[$key] = $result;
            }

        }



        /* Сортировать по наименьшему остатку */
        usort($stocksByArticleFiltered, function($stock1, $stock2) {
            return $stock1['min_stock_total'] > $stock2['min_stock_total'];
        });

        /* Возвратить первый элемент с наименьшим остатком */
        return empty($stocksByArticleFiltered) === false ? reset($stocksByArticleFiltered) : false;
    }

}