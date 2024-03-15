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

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksCancel\SubProductStocksTotalCancelMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubReserveProductStockTotalByCancel
{
    private ProductStocksByIdInterface $productStocks;

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $collection,
        LoggerInterface $productsStocksLogger,
        MessageDispatchInterface $messageDispatch
    )
    {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->messageDispatch = $messageDispatch;

        // Инициируем статусы складских остатков
        $collection->cases();
    }

    /**
     * Снимаем резерв со склада при статусе "Отмена заявки"
     */
    public function __invoke(ProductStockMessage $message): void
    {
        if($message->getLast() === null)
        {
            return;
        }

        /** Получаем статус прошлого события заявки */
        $ProductStockEvent = $this->entityManager->getRepository(ProductStockEvent::class)->find($message->getEvent());

        // Если статус предыдущего события заявки не является Cancel «Отменен».
        if(!$ProductStockEvent || !$ProductStockEvent->getStatus()->equals(ProductStockStatusCancel::class))
        {
            $this->logger
                ->notice('Не снимаем резерв на складе: Статус заявки не является Cancel «Отменен»',
                    [
                        __FILE__.':'.__LINE__,
                        'ProductStockUid' => (string) $message->getId(),
                        'event' => (string) $message->getEvent(),
                        'last' => (string) $message->getLast()]
                );

            return;
        }

        // Получаем всю продукцию в заявке со статусом Cancel «Отменен»
        $products = $this->productStocks->getProductsCancelStocks($message->getId());

        if($products)
        {
            /** @var ProductStockProduct $product */
            foreach($products as $product)
            {
                $ProductStockTotal = $this->entityManager
                    ->getRepository(ProductStockTotal::class)
                    ->findOneBy(
                        [
                            'profile' => $ProductStockEvent->getProfile(), // Склад, с которого перемещается
                            'product' => $product->getProduct(),
                            'offer' => $product->getOffer(),
                            'variation' => $product->getVariation(),
                            'modification' => $product->getModification(),
                        ]
                    );

                if(!$ProductStockTotal)
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

                /** Снимаем ТОЛЬКО резерв продукции на складе */
                for($i = 1; $i <= $product->getTotal(); $i++)
                {
                    $SubProductStocksTotalCancelMessage = new SubProductStocksTotalCancelMessage(
                        $ProductStockEvent->getProfile(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification()
                    );

                    $this->messageDispatch->dispatch($SubProductStocksTotalCancelMessage, transport: 'products-stocks');
                }

                $this->logger->info('Отменили резерв на складе при при отмене заявки',
                    [
                        __FILE__.':'.__LINE__,
                        'event' => (string) $message->getEvent(),
                        'profile' => (string) $ProductStockEvent->getProfile(),
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                        'modification' => (string) $product->getModification(),
                        'total' => $product->getTotal(),
                    ]);
            }
        }
    }
}
