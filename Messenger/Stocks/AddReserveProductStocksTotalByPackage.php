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
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
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
    private CurrentProductStocksInterface $currentProductStocks;
    private MessageDispatchInterface $messageDispatch;
    private DeduplicatorInterface $deduplicator;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        LoggerInterface $productsStocksLogger,
        CurrentProductStocksInterface $currentProductStocks,
        MessageDispatchInterface $messageDispatch,
        DeduplicatorInterface $deduplicator
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->currentProductStocks = $currentProductStocks;
        $this->messageDispatch = $messageDispatch;
        $this->deduplicator = $deduplicator;
    }

    /**
     * Резервирование на складе продукции при статусе "ОТПАРВЛЕН НА СБОРКУ"
     */
    public function __invoke(ProductStockMessage $message): void
    {

        $Deduplicator = $this->deduplicator
            ->deduplication([
                $message->getId(),
                ProductStockStatusPackage::STATUS
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->entityManager->clear();

        $ProductStockEvent = $this->currentProductStocks->getCurrentEvent($message->getId());

        if(!$ProductStockEvent)
        {
            return;
        }

        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusPackage::class))
        {
            return;
        }


        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->productStocks->getProductsPackageStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка на упаковку не имеет продукции в коллекции', [__FILE__.':'.__LINE__]);
            return;
        }


        /** Идентификатор профиля, куда была отправлена заявка на упаковку */
        $UserProfileUid = $ProductStockEvent->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $key => $product)
        {
            $this->logger->info(
                'Добавляем резерв продукции на складе при создании заявки на упаковку',
                [
                    __FILE__.':'.__LINE__,
                    'event' => (string) $message->getEvent(),
                    'profile' => (string) $UserProfileUid,
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                    'variation' => (string) $product->getVariation(),
                    'modification' => (string) $product->getModification(),
                    'total' => $product->getTotal(),
                ]
            );


            /**
             * Создаем резерв на единицу продукции при упаковке
             */
            for($i = 1; $i <= $product->getTotal(); $i++)
            {
                $AddProductStocksReserve = new AddProductStocksReserveMessage(
                    $UserProfileUid,
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                );

                $this->messageDispatch->dispatch(
                    $AddProductStocksReserve,
                    transport: 'products-stocks'
                );

                if($i === $product->getTotal())
                {
                    break;
                }
            }
        }

        $Deduplicator->save();
    }
}
