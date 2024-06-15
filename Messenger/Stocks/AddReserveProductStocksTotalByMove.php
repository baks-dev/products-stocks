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
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddReserveProductStocksTotalByMove
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        LoggerInterface $productsStocksLogger,
        MessageDispatchInterface $messageDispatch,
    )
    {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
        $this->messageDispatch = $messageDispatch;
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
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusMoving::class))
        {
            return;
        }


        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $this->productStocks->getProductsMovingStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка на перемещение не имеет продукции в коллекции', [__FILE__.':'.__LINE__]);
            return;
        }

        /** Идентификатор профиля склада отгрузки */
        $UserProfileUid = $ProductStockEvent->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                'Добавляем резерв продукции на складе при создании заявки на перемещение',
                [
                    __FILE__.':'.__LINE__,
                    'total' => $product->getTotal(),
                    'number' => $ProductStockEvent->getNumber(),
                ]
            );

            /**
             * Добавляем резерв на единицу продукции (добавляем по одной для резерва от меньшего к большему)
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
                    return;
                }

                usleep(300);
            }

        }

    }
}
