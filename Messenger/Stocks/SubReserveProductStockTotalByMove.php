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
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubReserveProductStockTotalByMove
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
     * Снимаем резерв со склада при статусе "ПЕРЕМЕЩЕНИЕ"
     */
    public function __invoke(ProductStockMessage $message): void
    {
        if ($message->getLast() === null)
        {
            return;
        }

        /** Получаем статус прошлого события заявки */
        $ProductStockEventLast = $this->entityManager->getRepository(ProductStockEvent::class)->find($message->getLast());

        // Если статус предыдущего события заявки не является Moving «Перемещение»
        if (!$ProductStockEventLast || !$ProductStockEventLast->getStatus()->equals(ProductStockStatusMoving::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус предыдущего события не Moving «Перемещение»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

            return;
        }

        /** Получаем статус активного события заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }


        // Если статус текущей заявки не является Warehouse «Отправили на склад»
        if (!$ProductStockEvent->getStatus()->equals(ProductStockStatusWarehouse::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус заявки не является Warehouse «Отправили на склад»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

            return;
        }

        // Получаем всю продукцию в ордере которая перемещается со склада
        $products = $this->productStocks->getProductsWarehouseStocks($message->getId());

        if ($products)
        {
            /** @var ProductStockProduct $product */
            foreach ($products as $product)
            {
                $ProductStockTotal = $this->entityManager
                    ->getRepository(ProductStockTotal::class)
                    ->findOneBy(
                        [
                            'profile' => $ProductStockEventLast->getProfile(), // Склад, с которого переместилась продукция
                            'product' => $product->getProduct(),
                            'offer' => $product->getOffer(),
                            'variation' => $product->getVariation(),
                            'modification' => $product->getModification(),
                        ]
                    );

                if (!$ProductStockTotal)
                {
                    $throw = sprintf(
                        'Невозможно снять резерв с продукции, которой нет на складе (profile: %s, product: %s, offer: %s, variation: %s, modification: %s)',
                        $ProductStockEvent->getProfile(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification(),
                    );

                    throw new DomainException($throw);
                }

                $ProductStockTotal->subReserve($product->getTotal());
                $ProductStockTotal->subTotal($product->getTotal());


                $this->logger->info('Сняли резерв и уменьшили количество на складе при перемещении продукции',
                    [
                        __FILE__.':'.__LINE__,
                        'event' => $message->getEvent()->getValue(),
                        'profile' => $ProductStockEvent->getProfile()->getValue(),
                        'product' => $product->getProduct()->getValue(),
                        'offer' => $product->getOffer()?->getValue(),
                        'variation' => $product->getVariation()?->getValue(),
                        'modification' => $product->getModification()?->getValue(),
                        'total' => $product->getTotal(),
                    ]);

            }

            $this->entityManager->flush();
        }
    }
}
