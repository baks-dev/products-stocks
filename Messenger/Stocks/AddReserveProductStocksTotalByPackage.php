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

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddReserveProductStocksTotalByPackage
{
    private ProductStocksByIdInterface $productStocks;

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $collection,
        LoggerInterface $messageDispatchLogger,
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;

        // Инициируем статусы складских остатков
        $collection->cases();
        $this->logger = $messageDispatchLogger;
    }

    /**
     * Резервирование на складе продукции при статусе "ОТПАРВЛЕН НА СБОРКУ"
     */
    public function __invoke(ProductStockMessage $message): void
    {
        return;


        $this->logger->info('MessageHandler', ['handler' => self::class]);

        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        // Если Статус не является "Упаковка"
        if (!$ProductStockEvent || !$ProductStockEvent->getStatus()->equals(new ProductStockStatusPackage()))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->productStocks->getProductsPackageStocks($message->getId());

        if ($products)
        {
            /** @var ProductStockProduct $product */
            foreach ($products as $product)
            {
                $ProductStockTotal = $this->entityManager
                    ->getRepository(ProductStockTotal::class)
                    ->findOneBy(
                        [
                            'warehouse' => $ProductStockEvent->getWarehouse(),
                            'product' => $product->getProduct(),
                            'offer' => $product->getOffer(),
                            'variation' => $product->getVariation(),
                            'modification' => $product->getModification(),
                        ]
                    );

                $ProductStockTotal->addReserve($product->getTotal());
            }

            $this->entityManager->flush();
        }

        $this->logger->info('MessageHandlerSuccess', ['handler' => self::class]);
    }
}
