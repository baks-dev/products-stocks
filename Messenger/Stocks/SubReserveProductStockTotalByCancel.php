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

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksReserve\SubProductStocksTotalReserveMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubReserveProductStockTotalByCancel
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageDispatchInterface $messageDispatch;
    private DeduplicatorInterface $deduplicator;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        LoggerInterface $productsStocksLogger,
        MessageDispatchInterface $messageDispatch,
        DeduplicatorInterface $deduplicator
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->messageDispatch = $messageDispatch;
        $this->deduplicator = $deduplicator;
    }

    /**
     * Создаем события на снятие резерва при отмене складской заявки
     */
    public function __invoke(ProductStockMessage $message): void
    {

        $Deduplicator = $this->deduplicator
            ->namespace(md5(self::class))
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusCancel::STATUS
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }


        if($message->getLast() === null)
        {
            return;
        }

        /** Активный статус складской заявки */
        $ProductStockEvent = $this->entityManager->getRepository(ProductStockEvent::class)->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        // Если статус события заявки не является Cancel «Отменен».
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusCancel::class))
        {
            return;
        }

        // Получаем всю продукцию в заявке со статусом Cancel «Отменен»
        $products = $this->productStocks->getProductsCancelStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка на отмену не имеет продукции в коллекции', [__FILE__.':'.__LINE__]);
            return;
        }

        /** Идентификатор профиля склада отгрузки, где производится отмена заявки */
        $UserProfileUid = $ProductStockEvent->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                'Отменяем резерв на складе при отмене складской заявки',
                [
                    __FILE__.':'.__LINE__,
                    'number' => $ProductStockEvent->getNumber(),
                    'total' => $product->getTotal(),
                    'ProductStockEventUid' => (string) $message->getEvent(),
                    'UserProfileUid' => (string) $UserProfileUid,
                    'ProductUid' => (string) $product->getProduct(),
                    'ProductOfferConst' => (string) $product->getOffer(),
                    'ProductVariationConst' => (string) $product->getVariation(),
                    'ProductModificationConst' => (string) $product->getModification(),
                ]
            );

            /** Снимаем ТОЛЬКО резерв продукции на складе */
            for($i = 1; $i <= $product->getTotal(); $i++)
            {
                $SubProductStocksTotalCancelMessage = new SubProductStocksTotalReserveMessage(
                    $UserProfileUid,
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                );

                $this->messageDispatch->dispatch($SubProductStocksTotalCancelMessage, transport: 'products-stocks');

                if($i === $product->getTotal())
                {
                    break;
                }
            }
        }

        $Deduplicator->save();
    }
}
