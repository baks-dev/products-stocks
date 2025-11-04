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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\BaksDevProductsStocksBundle;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\Products\Part\ProductStockProductPart;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\ArrayParameterType;
use Generator;


final class AllProductStocksOrdersProductRepository implements AllProductStocksOrdersProductInterface
{
    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function forProfile(UserProfile|UserProfileUid|null|false $profile): self
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


    /**
     * Метод возвращает продукцию без партии согласно
     *
     * @return Generator<ProductStocksOrdersProductResult>
     */
    public function findAll(array $ids): Generator|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('SUM(product_stock_product.total) AS total')
            ->addSelect('product_stock_product.product')
            ->addSelect('product_stock_product.offer')
            ->addSelect('product_stock_product.variation')
            ->addSelect('product_stock_product.modification')
            ->from(ProductStockProduct::class, 'product_stock_product')
            ->where('product_stock_product.event IN (:uuids)')
            ->setParameter(
                key: 'uuids',
                value: $ids,
                type: ArrayParameterType::STRING,
            );


        $dbal
            ->addSelect("JSON_AGG (DISTINCT orders_invariable.main) AS mains")
            ->leftJoin(
                'product_stock_product',
                ProductStockEvent::class,
                'product_stock_event',
                'product_stock_event.id = product_stock_product.event',
            );

        $dbal
            ->leftJoin(
                'product_stock_product',
                ProductStockProductPart::class,
                'product_stock_product_part',
                'product_stock_product_part.product = product_stock_product.id',
            );


        $dbal->andWhere('product_stock_product_part.value IS NULL');

        /** Продукт */
        $dbal
            ->join(
                'product_stock_product',
                Product::class,
                'product',
                'product.id = product_stock_product.product',
            );


        /** Название продукта */
        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                '
                        product_trans.event = product.event 
                        AND product_trans.local = :local
                    ');


        /** OFFER */
        $dbal
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_stock_product',
                ProductOffer::class,
                'product_offer',
                '
                        product_offer.event = product.event 
                        AND product_offer.const = product_stock_product.offer
                    ');

        /** ТИП торгового предложения */
        $dbal
            ->addSelect('product_category_offers.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'product_category_offers',
                'product_category_offers.id = product_offer.category_offer',
            );

        /** VARIATION */
        $dbal
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_stock_product',
                ProductVariation::class,
                'product_variation',
                '
                        product_variation.offer = product_offer.id 
                        AND product_variation.const = product_stock_product.variation
                    ');

        /** ТИП варианта торгового предложения */
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_variation.category_variation',
            );


        /** MODIFICATION */
        $dbal
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_stock_product',
                ProductModification::class,
                'product_modification',
                '
                        product_modification.variation = product_variation.id 
                        AND product_modification.const = product_stock_product.modification
                    ');

        /** ТИП модификации множественного варианта */
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
            );

        $dbal
            ->addSelect("JSON_AGG ( 
                DISTINCT JSONB_BUILD_OBJECT (
                    'id', product_stock_product.id
                )) AS products",
            );


        /**
         * Если имеется складской учет и передан идентификатор профиля магазина
         */
        if(class_exists(BaksDevProductsStocksBundle::class))
        {
            $dbal
                ->addSelect("JSON_AGG ( 
                        DISTINCT JSONB_BUILD_OBJECT (
                            'total', stock.total, 
                            'reserve', stock.reserve,
                            'storage', stock.storage
                            
                        )) AS stocks_quantity",
                )
                ->leftJoin(
                    'product_stock_product',
                    ProductStockTotal::class,
                    'stock',
                    '
                    
                        stock.profile = :stock_profile AND
                        stock.product = product.id 
                        
                        AND
                            
                            CASE 
                                WHEN product_stock_product.offer IS NOT NULL 
                                THEN stock.offer = product_stock_product.offer
                                ELSE stock.offer IS NULL
                            END
                                
                        AND 
                        
                            CASE
                                WHEN product_stock_product.variation IS NOT NULL 
                                THEN stock.variation = product_stock_product.variation
                                ELSE stock.variation IS NULL
                            END
                            
                        AND
                        
                            CASE
                                WHEN product_stock_product.modification IS NOT NULL 
                                THEN stock.modification = product_stock_product.modification
                                ELSE stock.modification IS NULL
                            END
                ')
                ->setParameter(
                    key: 'stock_profile',
                    value: $this->profile instanceof UserProfileUid ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
                    type: UserProfileUid::TYPE,
                );


        }

        $dbal
            ->leftJoin(
                'product_stock_product',
                ProductStockOrder::class,
                'product_stock_order',
                'product_stock_order.event = product_stock_product.event',
            );

        $dbal->leftJoin(
            'product_stock_order',
            OrderInvariable::class,
            'orders_invariable',
            'orders_invariable.main = product_stock_order.ord',
        );

        $dbal
            ->addSelect("JSON_AGG ( 
                DISTINCT JSONB_BUILD_OBJECT (
                    'id', orders_invariable.main,
                    'number', orders_invariable.number
                )) AS orders",
            );


        /** Получаем честные знаки на продукцию */


        /** Получаем стикеры маркировки заказов */


        $dbal->allGroupByExclude();

        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllHydrate(ProductStocksOrdersProductResult::class);
    }
}