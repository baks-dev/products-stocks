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

namespace BaksDev\Products\Stocks\Repository\ProductVariationChoice;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Products\Category\Entity as CategoryEntity;
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductVariationChoiceWarehouse implements ProductVariationChoiceWarehouseInterface
{

    private TranslatorInterface $translator;
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(
        ORMQueryBuilder $ORMQueryBuilder,
        TranslatorInterface $translator
    )
    {

        $this->translator = $translator;
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /** Метод возвращает все идентификаторы множественных вариантов, имеющиеся в наличии на склад */
    public function getProductsVariationExistWarehouse(
        UserUid $usr,
        ProductUid $product,
        ProductOfferConst $offer
    ): ?array
    {

        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $select = sprintf('new %s(stock.variation, variation.value, trans.name, (SUM(stock.total) - SUM(stock.reserve)))', ProductVariationConst::class);

        $qb->select($select);

        $qb->from(ProductStockTotal::class, 'stock');

        $qb
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $usr, UserUid::TYPE);

        // $qb->where('stock.warehouse = :warehouse');
        $qb->andWhere('(stock.total - stock.reserve) > 0');
        $qb->andWhere('stock.product = :product');
        $qb->andWhere('stock.offer = :offer');

        $qb->addGroupBy('stock.variation');
        $qb->addGroupBy('trans.name');
        $qb->addGroupBy('variation.value');

        $qb->join(
            ProductEntity\Product::class,
            'product',
            'WITH',
            'product.id = stock.product'
        );

        $qb->join(
            ProductEntity\Offers\ProductOffer::class,
            'offer',
            'WITH',
            'offer.const = stock.offer AND offer.event = product.event'
        );

        $qb->join(
            ProductEntity\Offers\Variation\ProductVariation::class,
            'variation',
            'WITH',
            'variation.const = stock.variation AND variation.offer = offer.id'
        );

        // Тип торгового предложения

        $qb->join(
            CategoryEntity\Offers\Variation\ProductCategoryVariation::class,
            'category_variation',
            'WITH',
            'category_variation.id = variation.categoryVariation'
        );

        $qb->leftJoin(
            CategoryEntity\Offers\Variation\Trans\ProductCategoryVariationTrans::class,
            'trans',
            'WITH',
            'trans.variation = category_variation.id AND trans.local = :local'
        );

        $qb->setParameter('product', $product, ProductUid::TYPE);
        $qb->setParameter('offer', $offer, ProductOfferConst::TYPE);
        $qb->setParameter('local', new Locale($this->translator->getLocale()), Locale::TYPE);


        /* Кешируем результат ORM */
        return $qb
            //->enableCache('products-stocks', 86400)
            ->getResult();
    }
}
