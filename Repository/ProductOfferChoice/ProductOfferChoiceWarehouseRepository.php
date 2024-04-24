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

namespace BaksDev\Products\Stocks\Repository\ProductOfferChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Type\Locale\Locale;

use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\CategoryProductOffersTrans;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\User\Type\Id\UserUid;
use Generator;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductOfferChoiceWarehouseRepository implements ProductOfferChoiceWarehouseInterface
{

    private DBALQueryBuilder $DBALQueryBuilder;

    private ?UserUid $user = null;
    private ?ProductUid $product = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function user(UserUid|string $user): self
    {
        if(is_string($user))
        {
            $user = new UserUid($user);
        }

        $this->user = $user;

        return $this;
    }


    public function product(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }


    /**
     * Метод возвращает все идентификаторы торговых предложений, имеющиеся в наличии на складе
     */
    public function getProductsOfferExistWarehouse(): Generator
    {
        if(!$this->user || !$this->product)
        {
            throw new InvalidArgumentException('Необходимо передать все параметры');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(ProductStockTotal::class, 'stock');

        $dbal
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $this->user, UserUid::TYPE);

        $dbal
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);

        $dbal->andWhere('(stock.total - stock.reserve) > 0');


        $dbal->join(
            'stock',
            Product::class,
            'product',
            'product.id = stock.product'
        );


        $dbal->join(
            'product',
            ProductOffer::class,
            'offer',
            'offer.const = stock.offer AND offer.event = product.event'
        );

        // Тип торгового предложения

        $dbal->join(
            'offer',
            CategoryProductOffers::class,
            'category_offer',
            'category_offer.id = offer.category_offer'
        );

        $dbal->leftJoin(
            'category_offer',
            CategoryProductOffersTrans::class,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );


        $dbal->addSelect('stock.offer AS value');
        $dbal->addSelect('offer.value AS attr');
        $dbal->addSelect('category_offer_trans.name AS option');

        $dbal->addSelect('(SUM(stock.total) - SUM(stock.reserve)) AS property');
        $dbal->addSelect('offer.postfix AS characteristic');
        $dbal->addSelect('category_offer.reference AS reference');

        $dbal->groupBy('stock.offer');
        $dbal->addGroupBy('category_offer_trans.name');
        $dbal->addGroupBy('offer.value');
        $dbal->addGroupBy('offer.postfix');
        $dbal->addGroupBy('category_offer.reference');

        return $dbal
            ->enableCache('products-stocks', 86400)
            ->fetchAllHydrate(ProductOfferConst::class);




    }
}
