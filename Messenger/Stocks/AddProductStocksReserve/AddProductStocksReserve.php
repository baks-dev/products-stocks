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

namespace BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddProductStocksReserve
{
    private ProductStockQuantityInterface $productStockMinQuantity;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private AddProductStockInterface $addProductStock;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductStockQuantityInterface $productStockMinQuantity,
        LoggerInterface $productsStocksLogger,
        AddProductStockInterface $addProductStock
    )
    {
        $this->productStockMinQuantity = $productStockMinQuantity;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->addProductStock = $addProductStock;
    }

    /**
     * Создает резерв на единицу продукции на указанный склад начиная с минимального наличия
     */
    public function __invoke(AddProductStocksReserveMessage $message): void
    {
        $this->entityManager->clear();

        $ProductStockTotal = $this->productStockMinQuantity
            ->profile($message->getProfile())
            ->product($message->getProduct())
            ->offerConst($message->getOffer())
            ->variationConst($message->getVariation())
            ->modificationConst($message->getModification())
            ->findOneBySubReserve();

        if(!$ProductStockTotal)
        {
            $this->logger->critical('Не найдено продукции на складе для резервирования',
                [
                    __FILE__.':'.__LINE__,
                    'profile' => (string) $message->getProfile(),
                    'product' => (string) $message->getProduct(),
                    'offer' => (string) $message->getOffer(),
                    'variation' => (string) $message->getVariation(),
                    'modification' => (string) $message->getModification()
                ]);

            throw new DomainException('Невозможно добавить резерв на продукцию');

        }


        $this->handle($ProductStockTotal);
    }

    public function handle(ProductStockTotal $ProductStockTotal): void
    {
        /** Добавляем в резерв единицу продукции */
        $rows = $this->addProductStock
            ->total(null)
            ->reserve(1)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical('Не найдено продукции на складе для резервирования. Возможно остатки были изменены в указанном месте',
                [
                    __FILE__.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]);

            return;
        }

        $this->logger->info(sprintf('%s : Добавили резерв на склад единицы продукции', $ProductStockTotal->getStorage()),
            [
                __FILE__.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]);
    }
}
