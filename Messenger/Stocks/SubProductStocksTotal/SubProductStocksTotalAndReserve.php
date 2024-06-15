<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Lock\AppLockInterface;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\SubProductStockInterface;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubProductStocksTotalAndReserve
{
    private ProductStockQuantityInterface $productStockMinQuantity;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private SubProductStockInterface $updateProductStock;
    private AppLockInterface $appLock;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductStockQuantityInterface $productStockMinQuantity,
        LoggerInterface $productsStocksLogger,
        SubProductStockInterface $updateProductStock,
        AppLockInterface $appLock
    )
    {
        $this->productStockMinQuantity = $productStockMinQuantity;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->updateProductStock = $updateProductStock;
        $this->appLock = $appLock;
    }

    /**
     * Снимает наличие продукции и резерв с указанного склада с мест, начиная с минимального наличия
     */
    public function __invoke(SubProductStocksTotalAndReserveMessage $message): void
    {

        $key = $message->getProfile().
            $message->getProduct().
            $message->getOffer().
            $message->getVariation().
            $message->getModification();

        $lock = $this->appLock
            ->createLock($key)
            ->lifetime(30)
            ->wait();


        $this->entityManager->clear();

        // Получаем одно место складирования продукции с минимальным количеством в наличии без учета резерва, но чтобы был резерв
        // списываем единицу продукции с минимальным числом остатка, затем с другого места где больше
        $ProductStockTotal = $this->productStockMinQuantity
            ->profile($message->getProfile())
            ->product($message->getProduct())
            ->offerConst($message->getOffer())
            ->variationConst($message->getVariation())
            ->modificationConst($message->getModification())
            ->findOneByTotalMin();

        if(!$ProductStockTotal)
        {
            $this->logger->critical('Не найдено продукции на складе для списания',
                [
                    __FILE__.':'.__LINE__,
                    'profile' => (string) $message->getProfile(),
                    'product' => (string) $message->getProduct(),
                    'offer' => (string) $message->getOffer(),
                    'variation' => (string) $message->getVariation(),
                    'modification' => (string) $message->getModification()
                ]);

            return;
        }

        $this->handle($ProductStockTotal);

        $lock->release();
    }

    public function handle(ProductStockTotal $ProductStockTotal): void
    {
        $rows = $this->updateProductStock
            ->total(1)
            ->reserve(1)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical(
                'Невозможно снять резерв и остаток продукции, которой нет в наличии или заранее не зарезервирована',
                [
                    __FILE__.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]);

            return;
        }

        $this->logger->info(
            sprintf('место: %s : Сняли резерв и уменьшили количество на единицу продукции', $ProductStockTotal->getStorage()),
            [
                __FILE__.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]);
    }
}
