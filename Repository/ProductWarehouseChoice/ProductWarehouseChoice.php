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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseChoice;

use BaksDev\Contacts\Region\Entity as ContactsRegionEntity;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductWarehouseChoice implements ProductWarehouseChoiceInterface
{
    //    private EntityManagerInterface $entityManager;
    //
    //    private TranslatorInterface $translator;
    //
    //    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator)
    //    {
    //        $this->entityManager = $entityManager;
    //        $this->translator = $translator;
    //    }


    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }


    /**
     * Возвращает список складов (профилей пользователя) на которых имеется данный вид продукта
     */
    public function fetchWarehouseByProduct(
        UserUid $usr,
        ProductUid $product,
        ?ProductOfferConst $offer,
        ?ProductVariationConst $variation,
        ?ProductModificationConst $modification,
    ): ?array
    {

        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $select = sprintf('new %s(stock.profile, profile_personal.username, stock.profile, (stock.total - stock.reserve) )', UserProfileUid::class);

        $qb->select($select);

        $qb->from(ProductStockTotal::class, 'stock');
        //        $qb->addGroupBy('stock.warehouse');
        //        $qb->addGroupBy('stock.total');
        //        $qb->addGroupBy('stock.reserve');


        $qb
            ->andWhere('stock.usr = :usr')
            ->setParameter('usr', $usr, UserUid::TYPE);

        $qb->andWhere('(stock.total - stock.reserve) > 0');

        $qb->andWhere('stock.product = :product');
        $qb->setParameter('product', $product, ProductUid::TYPE);

        if($offer)
        {
            $qb->andWhere('stock.offer = :offer');
            $qb->setParameter('offer', $offer, ProductOfferConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.offer IS NULL');
        }

        if($variation)
        {
            $qb->andWhere('stock.variation = :variation');
            $qb->setParameter('variation', $variation, ProductVariationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.variation IS NULL');
        }

        if($modification)
        {
            $qb->andWhere('stock.modification = :modification');
            $qb->setParameter('modification', $modification, ProductModificationConst::TYPE);
        }
        else
        {
            $qb->andWhere('stock.modification IS NULL');
        }


        $qb->join(
            UserProfile::class,
            'profile',
            'WITH',
            'profile.id = stock.profile',
        );

        $qb->join(
            UserProfilePersonal::class,
            'profile_personal',
            'WITH',
            'profile_personal.event = profile.event',
        );


        //        // Warehouse
        //        $exist = $this->entityManager->createQueryBuilder();
        //        $exist->select('1');
        //        $exist->from(ContactsRegionEntity\ContactsRegion::class, 'tmp');
        //        $exist->where('tmp.event = warehouse.event');
        //
        //        $qb->join(
        //            ContactsRegionEntity\Call\ContactsRegionCall::class,
        //            'warehouse',
        //            'WITH',
        //            'warehouse.const = stock.warehouse AND warehouse.stock = true AND '.$qb->expr()->exists($exist->getDQL()),
        //        );
        //
        //
        //        $qb->join(
        //            ContactsRegionEntity\Event\ContactsRegionEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = warehouse.event',
        //        );
        //
        ////        $qb->join(
        ////            ContactsRegionEntity\ContactsRegion::class,
        ////            'contacts',
        ////            'WITH',
        ////            'contacts.event = event.id',
        ////        );
        //
        //        $qb->leftJoin(
        //            ContactsRegionEntity\Call\Trans\ContactsRegionCallTrans::class,
        //            'trans',
        //            'WITH',
        //            'trans.call = warehouse.id AND trans.local = :local',
        //        );
        //        //$qb->addGroupBy('trans.name');

        //$qb->setParameter('local', new Locale($this->translator->getLocale()), Locale::TYPE);

        /** Не кешируем результат! Необходима актуальная информация о наличии */
        return $qb->getQuery()->getResult();

    }
}
