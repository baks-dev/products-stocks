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

namespace BaksDev\Products\Stocks\Repository\ProductChoice;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Products\Product\Entity as ProductEntity;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductChoiceWarehouse implements ProductChoiceWarehouseInterface
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


    /**
     * Метод возвращает все идентификаторы продуктов с названием, имеющиеся в наличие на данном складе
     */
    public function getProductsExistWarehouse(UserUid $usr): ?array
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $select = sprintf('new %s(stock.product, trans.name, (SUM(stock.total) - SUM(stock.reserve)) )', ProductUid::class);

        $qb->select($select);

        $qb->from(ProductStockTotal::class, 'stock');

        $qb
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $usr, UserUid::TYPE);

        //$qb->where('stock.warehouse = :warehouse');
        $qb->andWhere('(stock.total - stock.reserve)  > 0');

        $qb
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $usr, UserUid::TYPE);

        $qb->groupBy('stock.product');
        $qb->addGroupBy('trans.name');

        $qb->join(
            ProductEntity\Product::class,
            'product',
            'WITH',
            'product.id = stock.product'
        );

        $qb->leftJoin(
            ProductEntity\Trans\ProductTrans::class,
            'trans',
            'WITH',
            'trans.event = product.event AND trans.local = :local'
        );


        $qb->setParameter('local', new Locale($this->translator->getLocale()), Locale::TYPE);

        /* Кешируем результат ORM */
        return $qb->enableCache('products-stocks', 86400)->getResult();

    }






    /** Метод возвращает все идентификаторы продуктов с названием, имеющиеся в наличие на данном складе */
    public function getProductsByWarehouse(ContactsRegionCallUid $warehouse): ?array
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $select = sprintf('new %s(stock.product, trans.name, SUM(stock.total))', ProductUid::class);

        $qb->select($select);

        $qb->from(ProductStockTotal::class, 'stock');
        $qb->where('stock.warehouse = :warehouse');
        $qb->andWhere('stock.total > 0');

        $qb->groupBy('stock.product');
        $qb->addGroupBy('trans.name');

        $qb->join(
            ProductEntity\Product::class,
            'product',
            'WITH',
            'product.id = stock.product'
        );


        $qb->leftJoin(
            ProductEntity\Trans\ProductTrans::class,
            'trans',
            'WITH',
            'trans.event = product.event AND trans.local = :local'
        );


        $qb->setParameter('warehouse', $warehouse, ContactsRegionCallUid::TYPE);
        $qb->setParameter('local', new Locale($this->translator->getLocale()), Locale::TYPE);

        /* Кешируем результат ORM */
        return $qb->enableCache('products-stocks', 86400)->getResult();
    }
}
