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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackage;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackageTransport;
use BaksDev\DeliveryTransport\Entity\Package\Stocks\DeliveryPackageStocks;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Print\OrderPrint;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Stocks\Entity\Stock\Event\Part\ProductStockPart;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Forms\PackageFilter\ProductStockPackageFilterInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;

final class AllProductStocksPackageRepository implements AllProductStocksPackageInterface
{
    private ?SearchDTO $search = null;

    private ?ProductStockPackageFilterInterface $filter = null;

    private ?UserProfileUid $profile = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductStockPackageFilterInterface $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function profile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->paginator->setLimit($limit);
        return $this;
    }


    /**
     * Метод возвращает все заявки на упаковку заказов в виде массива.
     */
    public function findPaginator(): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('stock.id')
            ->addSelect('stock.event')
            ->from(ProductStock::class, 'stock');

        $dbal
            ->addSelect('invariable.number')
            ->join(
                'stock',
                ProductStocksInvariable::class,
                'invariable',
                '
                invariable.main = stock.id AND 
                invariable.profile = :profile
            ')
            ->setParameter(
                key: 'profile',
                value: ($this->profile instanceof UserProfileUid) ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE,
            );


        // ProductStockEvent
        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->join(
                'stock',
                ProductStockEvent::class,
                'event',
                '
                event.id = stock.event AND 
                event.status = :package
                ');

        $dbal->setParameter(
            'package',
            new ProductStockStatus(new ProductStockStatusPackage()),
            ProductStockStatus::TYPE,
        );


        /** Погрузка на доставку */
        if(class_exists(DeliveryPackage::class))
        {
            /** Подгружаем разделенные заказы */
            $existDeliveryPackage = $this->DBALQueryBuilder->createQueryBuilder(self::class);
            $existDeliveryPackage
                ->select('1')
                ->from(DeliveryPackage::class, 'bGIuGLiNkf')
                ->where('bGIuGLiNkf.event = delivery_stocks.event');

            $dbal->leftJoin(
                'stock',
                DeliveryPackageStocks::class,
                'delivery_stocks',
                'delivery_stocks.stock = stock.id AND EXISTS('.$existDeliveryPackage->getSQL().')',
            );


            $dbal->leftJoin(
                'delivery_stocks',
                DeliveryPackage::class,
                'delivery_package',
                'delivery_package.event = delivery_stocks.event',
            );

            $dbal->addSelect('delivery_transport.date_package');

            $dbal->leftJoin(
                'delivery_package',
                DeliveryPackageTransport::class,
                'delivery_transport',
                'delivery_transport.package = delivery_package.id',
            );
        }
        else
        {
            $dbal->addSelect('NULL AS date_package');
            $dbal->setParameter('divide', 'ntUIGnScMq');
        }

        $dbal
            ->addSelect('stock_part.value AS product_stock_part')
            ->leftJoin(
                'stock',
                ProductStockPart::class,
                'stock_part',
                'stock_part.event = stock.event',
            );


        $dbal->join(
            'stock',
            ProductStockOrder::class,
            'ord',
            'ord.event = stock.event',
        );


        $dbal
            ->addSelect('orders.id AS order_id')
            ->leftJoin(
                'ord',
                Order::class,
                'orders',
                'orders.id = ord.ord',
            );


        $dbal
            ->addSelect('order_event.danger AS order_danger')
            ->addSelect('order_event.comment AS order_comment')
            ->leftJoin(
                'ord',
                OrderEvent::class,
                'order_event',
                'order_event.id = orders.event',
            );

        $dbal
            ->addSelect('order_print.printed as printed')
            ->leftJoin(
                'order_event',
                OrderPrint::class,
                'order_print',
                'order_print.event = order_event.id',
            );

        $dbal->leftJoin(
            'orders',
            OrderUser::class,
            'order_user',
            'order_user.event = orders.event',
        );


        $delivery_condition = 'order_delivery.usr = order_user.id';

        if($this->filter instanceof ProductStockPackageFilterInterface)
        {
            if($this->filter->getDate() instanceof DateTimeImmutable)
            {
                $delivery_condition .= ' AND order_delivery.delivery_date >= :delivery_date_start AND order_delivery.delivery_date < :delivery_date_end';
                $dbal->setParameter('delivery_date_start', $this->filter->getDate(), Types::DATE_IMMUTABLE);
                $dbal->setParameter(
                    'delivery_date_end',
                    $this->filter->getDate()?->modify('+1 day'),
                    Types::DATE_IMMUTABLE,
                );
            }

            if($this->filter->getDelivery() instanceof DeliveryUid)
            {
                $delivery_condition .= ' AND order_delivery.delivery = :delivery';
                $dbal->setParameter('delivery', $this->filter->getDelivery(), DeliveryUid::TYPE);
            }

            if(true === $this->filter->getPrint())
            {
                $dbal->andWhere('order_print.printed IS TRUE');
            }

            if(false === $this->filter->getPrint())
            {
                $dbal->andWhere('order_print.printed IS NOT TRUE');
            }

        }

        $dbal
            ->addSelect('order_delivery.delivery_date')
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                $delivery_condition,
            );

        $dbal->leftJoin(
            'order_delivery',
            DeliveryEvent::class,
            'delivery_event',
            'delivery_event.id = order_delivery.event AND delivery_event.main = order_delivery.delivery',
        );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'delivery_event',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = delivery_event.id AND delivery_trans.local = :local',
            );


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $dbal->leftJoin(
            'event',
            UserProfile::class,
            'users_profile',
            'users_profile.id = invariable.profile',
        );

        // Event
        $dbal->leftJoin(
            'users_profile',
            UserProfileEvent::class,
            'users_profile_event',
            'users_profile_event.id = users_profile.event',
        );

        // Personal
        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile_event',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile_event.id',
            );


        // Группа
        /** Проверка перемещения по заказу */
        //$dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        //$dbalExist->select('1');
        //$dbalExist->from(ProductStockMove::class, 'exist_move');
        //$dbalExist->where('exist_move.ord = ord.ord ');

        //        $dbalExist->join(
        //            'exist_move',
        //            ProductStockEvent::class,
        //            'exist_move_event',
        //            'exist_move_event.id = exist_move.event AND  (
        //                exist_move_event.status != :incoming
        //            )'
        //        );


        //        $dbalExist->join(
        //            'exist_move_event',
        //            ProductStock::class,
        //            'exist_move_stock',
        //            'exist_move_stock.event = exist_move_event.id'
        //        );


        //$dbal->addSelect(sprintf('EXISTS(%s) AS products_move', $dbalExist->getSQL()));
        //        $dbal->setParameter(
        //            'incoming',
        //            new ProductStockStatus(new ProductStockStatusIncoming()),
        //            ProductStockStatus::TYPE
        //        );


        /** Пункт назначения при перемещении */
        //        $dbal->leftJoin(
        //            'event',
        //            ProductStockMove::class,
        //            'move_stock',
        //            'move_stock.event = event.id'
        //        );


        // UserProfile
        //        $dbal->leftJoin(
        //            'move_stock',
        //            UserProfile::class,
        //            'users_profile_move',
        //            'users_profile_move.id = move_stock.destination'
        //        );

        //        $dbal
        //            ->addSelect('users_profile_personal_move.username AS users_profile_destination')
        //            ->leftJoin(
        //                'users_profile_move',
        //                UserProfilePersonal::class,
        //                'users_profile_personal_move',
        //                'users_profile_personal_move.event = users_profile_move.event'
        //            );


        //        /** Пункт назначения при перемещении */
        //        $dbal->leftOneJoin(
        //            'ord',
        //            ProductStockMove::class,
        //            'destination_stock',
        //            'destination_stock.event != stock.event AND destination_stock.ord = ord.ord',
        //            'event'
        //        );


        //        $dbal->leftJoin(
        //            'destination_stock',
        //            ProductStockEvent::class,
        //            'destination_event',
        //            'destination_event.id = destination_stock.event'
        //        );

        //        // UserProfile
        //        $dbal->leftJoin(
        //            'destination_stock',
        //            UserProfile::class,
        //            'users_profile_destination',
        //            'users_profile_destination.id = destination_event.profile'
        //        );

        //        $dbal
        //            ->addSelect('users_profile_personal_destination.username AS users_profile_move')
        //            ->leftJoin(
        //                'users_profile_destination',
        //                UserProfilePersonal::class,
        //                'users_profile_personal_destination',
        //                'users_profile_personal_destination.event = users_profile_destination.event'
        //            );


        // Поиск
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('invariable.number');
        }


        //$dbal->addOrderBy('products_move', 'ASC');
        $dbal->addOrderBy('order_delivery.delivery_date', 'ASC');
        //$dbal->addOrderBy('stock.id', 'ASC');

        //$dbal->addGroupBy('ord.ord');
        //$dbal->allGroupByExclude();


        return $this->paginator->fetchAllHydrate($dbal, AllProductStocksPackageResult::class);
    }
}
