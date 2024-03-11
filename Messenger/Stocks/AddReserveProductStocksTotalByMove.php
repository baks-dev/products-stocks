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
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddReserveProductStocksTotalByMove
{
    private ProductStocksByIdInterface $productStocks;

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $collection,
        LoggerInterface $productsStocksLogger
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;

        // Инициируем статусы складских остатков
        $collection->cases();
        $this->logger = $productsStocksLogger;
    }

    /**
     * Резервирование на складе продукции при перемещении
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
        if ($ProductStockEvent->getStatus()->equals(ProductStockStatusMoving::class) === false)
        {
            $this->logger
                ->notice('Не добавляем резерв на склад: Статус заявки не является Moving «Перемещение»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

            return;
        }

        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $this->productStocks->getProductsMovingStocks($message->getId());

        if ($products)
        {
            /** @var ProductStockProduct $product */
            foreach ($products as $key => $product)
            {
                $ProductStockTotal = $this->entityManager
                    ->getRepository(ProductStockTotal::class)
                    ->findOneBy(
                        [
                            'profile' => $ProductStockEvent->getProfile(),
                            'product' => $product->getProduct(),
                            'offer' => $product->getOffer(),
                            'variation' => $product->getVariation(),
                            'modification' => $product->getModification(),
                        ]
                    );

                if (!$ProductStockTotal)
                {
                    $throw = sprintf(
                        'Невозможно зарезервировать продукцию, которой нет на складе (profile: %s, product: %s, offer: %s, variation: %s, modification: %s)',
                        $ProductStockEvent->getProfile(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification(),
                    );

                    throw new DomainException($throw);
                }

                $ProductStockTotal->addReserve($product->getTotal());

                $this->logger->info('Добавили резерв продукции '.$key.' на складе при создании заявки на перемещение',
                    [
                        __FILE__.':'.__LINE__,
                        'event' => $message->getEvent()->getValue(),
                        'profile' => $ProductStockEvent->getProfile()?->getValue(),
                        'product' => $product->getProduct()?->getValue(),
                        'offer' => $product->getOffer()?->getValue(),
                        'variation' => $product->getVariation()?->getValue(),
                        'modification' => $product->getModification()?->getValue(),
                        'total' => $product->getTotal(),
                    ]
                );

                /** Добавляем резерв продукции */
            }

            $this->entityManager->flush();
        }


    }
}
