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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\UpdateProductQuantity\AddProductQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final readonly class AddQuantityProductByIncomingStock
{
    public function __construct(
        #[Target('productsProductLogger')] private LoggerInterface $logger,
        private CurrentProductIdentifierByConstInterface $currentProductIdentifierByConst,
        private AddProductQuantityInterface $addProductQuantity,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private ProductStocksByIdInterface $productStocks,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     * Пополнение наличием продукции в карточке при поступлении на склад
     */
    public function __invoke(ProductStockMessage $message): void
    {
        $ProductStockEvent = $this
            ->ProductStocksEventRepository
            ->find($message->getEvent());

        if($ProductStockEvent === false)
        {
            return;
        }

        // Если статус не является Incoming «Приход на склад»
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusIncoming::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            /** Пополняем наличие карточки */
            $this->changeTotal($product);
        }


        $Deduplicator->save();
    }

    public function changeTotal(ProductStockProduct $product): void
    {
        $context = [
            self::class.':'.__LINE__,
            'total' => $product->getTotal(),
            'ProductUid' => (string) $product->getProduct(),
            'ProductStockEventUid' => (string) $product->getEvent()->getId(),
            'ProductOfferConst' => (string) $product->getOffer(),
            'ProductVariationConst' => (string) $product->getVariation(),
            'ProductModificationConst' => (string) $product->getModification(),
        ];

        $CurrentProductDTO = $this->currentProductIdentifierByConst
            ->forProduct($product->getProduct())
            ->forOfferConst($product->getOffer())
            ->forVariationConst($product->getVariation())
            ->forModificationConst($product->getModification())
            ->find();

        if($CurrentProductDTO === false)
        {
            $this->logger->critical('Поступление на склад: Невозможно пополнить общий остаток (карточка не найдена)', $context);
            return;
        }

        $rows = $this->addProductQuantity
            ->forEvent($CurrentProductDTO->getEvent())
            ->forOffer($CurrentProductDTO->getOffer())
            ->forVariation($CurrentProductDTO->getVariation())
            ->forModification($CurrentProductDTO->getModification())
            ->addQuantity($product->getTotal())
            ->addReserve(false)
            ->update();

        if($rows)
        {
            $this->logger->info('Поступление на склад: Пополнили общий остаток в карточке', $context);
        }
        else
        {
            $this->logger->critical('Поступление на склад: Невозможно пополнить общий остаток (карточка не найдена)', $context);
        }
    }
}
