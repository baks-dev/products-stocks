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

namespace BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksReserve;

use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\SubProductStockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubProductStocksTotalReserve
{
    private LoggerInterface $logger;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductStockQuantityInterface $productStockMinQuantity,
        private SubProductStockInterface $updateProductStock,
        LoggerInterface $productsStocksLogger,
    ) {
        $this->logger = $productsStocksLogger;
    }

    /**
     * Снимает резерв на единицу продукции с указанного склада с мест, начиная с максимального резерва
     */
    public function __invoke(SubProductStocksTotalReserveMessage $message): void
    {
        $this->entityManager->clear();

        /* Получаем одно место складирования с максимальным количеством продукции и резервом > 0 */
        $ProductStockTotal = $this->productStockMinQuantity
            ->profile($message->getProfile())
            ->product($message->getProduct())
            ->offerConst($message->getOffer())
            ->variationConst($message->getVariation())
            ->modificationConst($message->getModification())
            ->findOneByReserveMax();

        if(!$ProductStockTotal)
        {
            $this->logger->critical(
                'Не найдено продукции на складе для списания, либо нет резерва на указанную продукцию',
                [
                    self::class.':'.__LINE__,
                    'profile' => (string) $message->getProfile(),
                    'product' => (string) $message->getProduct(),
                    'offer' => (string) $message->getOffer(),
                    'variation' => (string) $message->getVariation(),
                    'modification' => (string) $message->getModification()
                ]
            );

            return;
        }

        $this->handle($ProductStockTotal);

    }

    public function handle(ProductStockTotal $ProductStockTotal): void
    {
        $rows = $this->updateProductStock
            ->total(null)
            ->reserve(1)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical(
                'Невозможно снять резерв единицы продукции, которой заранее не зарезервирована',
                [
                    self::class.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]
            );

            return;
        }

        $this->logger->info(
            sprintf('Место %s: Сняли резерв продукции на складе на одну единицу', $ProductStockTotal->getStorage()),
            [
                self::class.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]
        );
    }
}
