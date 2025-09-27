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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPickup;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Stock\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Forms\PickupFilter\ProductStockPickupFilterInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Value\UserProfileValue;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;

final class AllProductStocksPickupRepository implements AllProductStocksPickupInterface
{
    private ?SearchDTO $search = null;

    private ?ProductStockPickupFilterInterface $filter = null;

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

    public function filter(ProductStockPickupFilterInterface $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function profile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    private function builder(): DBALQueryBuilder
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class)->bindLocal();

        $dbal
            ->select('invariable.number')
            ->from(ProductStocksInvariable::class, 'invariable')
            ->where('invariable.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: false === empty($this->profile) ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE
            );

        $dbal
            ->addSelect('stock.id AS stock_id')
            ->addSelect('stock.event AS stock_event')
            ->join(
                'invariable',
                ProductStock::class,
                'stock',
                'stock.id = invariable.main'
            );

        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.status')
            ->join(
                'stock',
                ProductStockEvent::class,
                'event',
                'event.id = stock.event AND event.status = :status'
            )
            ->setParameter(
                'status',
                ProductStockStatusExtradition::class,
                ProductStockStatus::TYPE
            );


        // ProductStockModify
        $dbal
            ->addSelect('modify.mod_date')
            ->leftJoin(
                'event',
                ProductStockModify::class,
                'modify',
                'modify.event = stock.event'
            );

        $dbal
            ->join(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            );


        // ОТВЕТСТВЕННЫЙ

        // UserProfile
        $dbal
            ->join(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = invariable.profile'
            );


        $dbal->leftJoin(
            'stock',
            ProductStockOrder::class,
            'product_stock_order',
            'product_stock_order.event = stock.event'
        );


        $dbal
            ->addSelect('ord.id AS order_id')
            ->join(
                'product_stock_order',
                Order::class,
                'ord',
                'ord.id = product_stock_order.ord'
            );

        $dbal
            ->addSelect('order_event.danger AS order_danger')
            ->addSelect('order_event.comment AS order_comment')
            ->leftJoin(
                'ord',
                OrderEvent::class,
                'order_event',
                'order_event.id = ord.event',
            );

        $dbal
            ->addSelect('order_user.profile AS client_profile_event')
            ->leftJoin(
                'ord',
                OrderUser::class,
                'order_user',
                'order_user.event = ord.event'
            );


        $dbal->addSelect('order_delivery.delivery_date');

        $delivery_condition = 'order_delivery.usr = order_user.id';

        if($this->filter !== null)
        {
            $dateFrom = $this->filter->getDate();
            $dateTo = $dateFrom?->modify('+1 day');

            if($dateFrom instanceof DateTimeImmutable && $dateTo instanceof DateTimeImmutable)
            {
                $delivery_condition .= ' AND order_delivery.delivery_date >= :delivery_date_start AND order_delivery.delivery_date < :delivery_date_end';
                $dbal->setParameter('delivery_date_start', $dateFrom, Types::DATE_IMMUTABLE);
                $dbal->setParameter('delivery_date_end', $dateTo, Types::DATE_IMMUTABLE);
            }

            if($this->filter->getDelivery())
            {
                $delivery_condition .= ' AND order_delivery.delivery = :delivery';
                $dbal->setParameter('delivery', $this->filter->getDelivery(), DeliveryUid::TYPE);
            }

            if($this->filter->getPhone())
            {
                $dbal->join(
                    'order_user',
                    UserProfileValue::class,
                    'client_profile_value',
                    " client_profile_value.event = order_user.profile AND client_profile_value.value LIKE '%' || :phone || '%'"
                );

                $phone = explode('(', $this->filter->getPhone());
                $dbal->setParameter('phone', end($phone));
            }

        }

        $dbal
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                $delivery_condition
            );

        $dbal->leftJoin(
            'order_delivery',
            DeliveryEvent::class,
            'delivery_event',
            'delivery_event.id = order_delivery.event'
        );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'delivery_event',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = delivery_event.id AND delivery_trans.local = :local'
            );

        // Поиск
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('invariable.number');
        }

        $dbal->orderBy('modify.mod_date', 'DESC');

        return $dbal;
    }

    /**
     * Метод возвращает пагинатор ProductStocks
     * @deprecated
     */
    public function findPaginator(): PaginatorInterface
    {
        $dbal = $this->builder();

        return $this->paginator->fetchAllAssociative($dbal);
    }

    /**
     * Метод возвращает пагинатор ProductStocks с гидрацией на объект резалта
     * @see AllProductStocksPickupResult
     */
    public function findResultPaginator(): PaginatorInterface
    {
        $dbal = $this->builder();

        return $this->paginator->fetchAllHydrate($dbal, AllProductStocksPickupResult::class);
    }
}
