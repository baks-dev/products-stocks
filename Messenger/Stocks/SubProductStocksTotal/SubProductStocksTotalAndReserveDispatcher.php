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

namespace BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\SubProductStockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final readonly class SubProductStocksTotalAndReserveDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStockQuantityInterface $productStockMinQuantity,
        private SubProductStockInterface $updateProductStock,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     * Снимает наличие продукции и резерв с указанного склада с мест, начиная с минимального наличия
     */
    public function __invoke(SubProductStocksTotalAndReserveMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([$message, self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

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
            $this->logger->critical(
                'Не найдено продукции на складе для списания',
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

        $rows = $this->updateProductStock
            ->total(1)
            ->reserve(1)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical(
                'Невозможно снять резерв и остаток продукции, которой нет в наличии или заранее не зарезервирована',
                [
                    self::class.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]
            );

            return;
        }

        $DeduplicatorExecuted->save();

        $this->logger->info(
            sprintf('место: %s : Сняли резерв и уменьшили количество на единицу продукции', $ProductStockTotal->getStorage()),
            [
                self::class.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]
        );
    }
}
