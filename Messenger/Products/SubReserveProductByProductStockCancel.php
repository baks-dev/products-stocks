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
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Product\Repository\UpdateProductQuantity\SubProductQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимает ТОЛЬКО! резерв продукции при отмене заявки Cancel «Отменен»
 */
#[AsMessageHandler(priority: 1)]
final readonly class SubReserveProductByProductStockCancel
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentProductIdentifierByConstInterface $currentProductIdentifierByConst,
        private SubProductQuantityInterface $subProductQuantity,
        private ProductStocksByIdInterface $productStocks,
        private EntityManagerInterface $entityManager,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     * Снимает ТОЛЬКО РЕЗЕРВ! продукции в карточке при отмене заявки без заказа
     */
    public function __invoke(ProductStockMessage $message): void
    {

        if(!$message->getLast())
        {
            return;
        }

        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        // Если Статус не является Cancel «Отменен».
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class))
        {
            return;
        }

        /** Если заявка по заказу - не снимаем резерв (будет снят при отмене заказа) */
        if($ProductStockEvent->getOrder())
        {
            return;
        }

        // Получаем всю продукцию в заявке со статусом Cancel «Отменен»
        $products = $this->productStocks->getProductsCancelStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                $message->getId(),
                ProductStockStatusCancel::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->entityManager->clear();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->changeReserve($product);
        }

        $Deduplicator->save();
    }

    public function changeReserve(ProductStockProduct $product): void
    {
        $CurrentProductDTO = $this->currentProductIdentifierByConst
            ->forProduct($product->getProduct())
            ->forOfferConst($product->getOffer())
            ->forVariationConst($product->getVariation())
            ->forModificationConst($product->getModification())
            ->execute();


        $rows = $this->subProductQuantity
            ->forEvent($CurrentProductDTO->getEvent())
            ->forOffer($CurrentProductDTO->getOffer())
            ->forVariation($CurrentProductDTO->getVariation())
            ->forModification($CurrentProductDTO->getModification())
            ->subQuantity(false)
            ->subReserve($product->getTotal())
            ->update();


        $context = [
            self::class.':'.__LINE__,
            'total' => $product->getTotal(),
            'ProductUid' => (string) $product->getProduct(),
            'ProductStockEventUid' => (string) $product->getEvent()->getId(),
            'ProductOfferConst' => (string) $product->getOffer(),
            'ProductVariationConst' => (string) $product->getVariation(),
            'ProductModificationConst' => (string) $product->getModification(),
        ];

        if($rows)
        {
            $this->logger->info('Перемещение: Отменили общий резерв в карточке при отмене складской заявки на перемещение', $context);
        }
        else
        {
            $this->logger->critical('Перемещение: Невозможно отменить общий резерв продукции (карточка не найдена либо недостаточное количество резерва)', $context);
        }

    }
}
