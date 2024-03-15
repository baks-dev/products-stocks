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

namespace BaksDev\Products\Stocks\Repository\ProductStocksById;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use Doctrine\ORM\EntityManagerInterface;

final class ProductStocksById implements ProductStocksByIdInterface
{
    //    private EntityManagerInterface $entityManager;
    //
    //    public function __construct(EntityManagerInterface $entityManager)
    //    {
    //        $this->entityManager = $entityManager;
    //    }


    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {

        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }


    private function builder()
    {
        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->from(ProductStock::class, 'stock')
            ->where('stock.id = :id');

        $orm
            ->join(
                ProductStockEvent::class,
                'event',
                'WITH',
                'event.id = stock.event AND event.status = :status'
            );

        $orm
            ->select('product')
            ->leftJoin(
                ProductStockProduct::class,
                'product',
                'WITH',
                'product.event = event.id'
            );

        return $orm;
    }


    /**
     * Метод возвращает всю продукцию заявке с определенным статусом
     */
    public function getProductsByProductStocksStatus(
        ProductStockUid $id,
        ProductStockStatus|ProductStockStatusInterface|string $status
    ): ?array
    {
        //        if(is_string($status))
        //        {
        //            $status = new $status;
        //        }

        //        $status = $status instanceof ProductStockStatus ? $status : new ProductStockStatus($status);
        //
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');
        //        $qb->setParameter('id', $id, ProductStockUid::TYPE);
        //        $qb->setParameter('status', $status, ProductStockStatus::TYPE);

        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus($status), ProductStockStatus::TYPE);


        return $orm->getResult();
    }


    /** Метод возвращает всю продукция в приходном ордере */
    public function getProductsIncomingStocks(ProductStockUid $id): ?array
    {
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');
        //        $qb->setParameter('id', $id, ProductStockUid::TYPE);

        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus(ProductStockStatusIncoming::class), ProductStockStatus::TYPE);

        return $orm->getResult();
    }


    /**
     * Метод возвращает всю продукцию для сборки (Package)
     */
    public function getProductsPackageStocks(ProductStockUid $id): ?array
    {
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');


        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus(ProductStockStatusPackage::class), ProductStockStatus::TYPE);

        return $orm->getResult();
    }

    /**
     * Метод возвращает всю продукцию для перемещения
     */
    public function getProductsMovingStocks(ProductStockUid $id): ?array
    {
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');

        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus(ProductStockStatusMoving::class), ProductStockStatus::TYPE);

        return $orm->getResult();
    }


    /**
     * Метод возвращает всю продукцию которая переместилась со склада
     */
    public function getProductsWarehouseStocks(ProductStockUid $id): ?array
    {
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');

        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus(ProductStockStatusWarehouse::class), ProductStockStatus::TYPE);

        return $orm->getResult();
    }


    /**
     * Метод возвращает всю продукцию в отмененной заявке
     */
    public function getProductsCancelStocks(ProductStockUid $id): ?array
    {
        //        $qb = $this->entityManager->createQueryBuilder();
        //
        //        $qb->select('product');
        //        $qb->from(ProductStockEntity\ProductStock::class, 'stock');
        //
        //        $qb->join(
        //            ProductStockEntity\Event\ProductStockEvent::class,
        //            'event',
        //            'WITH',
        //            'event.id = stock.event AND event.status = :status'
        //        );
        //
        //        $qb->leftJoin(
        //            ProductStockEntity\Products\ProductStockProduct::class,
        //            'product',
        //            'WITH',
        //            'product.event = event.id'
        //        );
        //
        //        $qb->where('stock.id = :id');

        $orm = $this->builder();

        $orm->setParameter('id', $id, ProductStockUid::TYPE);
        $orm->setParameter('status', new ProductStockStatus(ProductStockStatusCancel::class), ProductStockStatus::TYPE);

        return $orm->getResult();
    }


}
