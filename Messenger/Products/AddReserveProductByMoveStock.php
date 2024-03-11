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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityRepositoryInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityRepositoryInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Добавляет резерв продукции при перемещении
 */

#[AsMessageHandler(priority: 1)]
final class AddReserveProductByMoveStock
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private ProductModificationQuantityRepositoryInterface $modificationQuantity;
    private ProductVariationQuantityRepositoryInterface $variationQuantity;
    private ProductOfferQuantityInterface $offerQuantity;
    private ProductQuantityInterface $productQuantity;
    private LoggerInterface $logger;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        ProductModificationQuantityRepositoryInterface $modificationQuantity,
        ProductVariationQuantityRepositoryInterface $variationQuantity,
        ProductOfferQuantityInterface $offerQuantity,
        ProductQuantityInterface $productQuantity,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $collection,
        LoggerInterface $productsStocksLogger
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->modificationQuantity = $modificationQuantity;
        $this->variationQuantity = $variationQuantity;
        $this->offerQuantity = $offerQuantity;
        $this->productQuantity = $productQuantity;

        // Инициируем статусы складских остатков
        $collection->cases();
        $this->logger = $productsStocksLogger;
    }

    /**
     * Добавляет резерв продукции при перемещении
     */
    public function __invoke(ProductStockMessage $message): void
    {

        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        // Если Статус не является Статус Moving «Перемещение»
        if (false === $ProductStockEvent->getStatus()->equals(ProductStockStatusMoving::class))
        {
            $this->logger
                ->notice('Не резервируем карточку товара: Статус заявки не является Moving «Перемещение»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

            return;
        }

        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $this->productStocks->getProductsMovingStocks($message->getId());

        if ($products) {

            $this->entityManager->clear();

            /** @var ProductStockProduct $product */
            foreach ($products as $key => $product) {

                $ProductUpdateReserve = null;

                // Количественный учет модификации множественного варианта торгового предложения
                if (null === $ProductUpdateReserve && $product->getModification()) {
                    $this->entityManager->clear();

                    $ProductUpdateReserve = $this->modificationQuantity->getProductModificationQuantity(
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification()
                    );
                }

                // Количественный учет множественного варианта торгового предложения
                if (null === $ProductUpdateReserve && $product->getVariation()) {
                    $this->entityManager->clear();

                    $ProductUpdateReserve = $this->variationQuantity->getProductVariationQuantity(
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation()
                    );
                }

                // Количественный учет торгового предложения
                if (null === $ProductUpdateReserve && $product->getOffer()) {
                    $this->entityManager->clear();

                    $ProductUpdateReserve = $this->offerQuantity->getProductOfferQuantity(
                        $product->getProduct(),
                        $product->getOffer()
                    );
                }

                // Количественный учет продукта
                if (null === $ProductUpdateReserve) {
                    $this->entityManager->clear();

                    $ProductUpdateReserve = $this->productQuantity->getProductQuantity(
                        $product->getProduct()
                    );
                }

                if ($ProductUpdateReserve)
                {
                    $ProductUpdateReserve->addReserve($product->getTotal());
                    $this->entityManager->flush();

                    $this->logger->info('Добавили общий резерв продукции с key:'.$key.' в карточке',
                    [
                        __FILE__.':'.__LINE__,
                        'event' => $message->getEvent()->getValue(),
                        'product' => $product->getProduct()->getValue(),
                        'offer' => $product->getOffer()?->getValue(),
                        'variation' => $product->getVariation()?->getValue(),
                        'modification' => $product->getModification()?->getValue(),
                        'total' => $product->getTotal(),
                    ]);
                }
            }
        }
    }
}
