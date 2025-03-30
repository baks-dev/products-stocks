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

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимаем резерв и наличие со склада отгрузки при статусе Moving «Перемещение»
 */
#[AsMessageHandler(priority: 1)]
final readonly class SubReserveProductStockTotalByMove
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksByIdInterface $productStocks,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        if(false === ($message->getLast() instanceof ProductStockEventUid))
        {
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                $message->getId(),
                ProductStockStatusMoving::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /**
         * Получаем статус прошлого события заявки
         */

        $ProductStockEventLast = $this->ProductStocksEventRepository
            ->forEvent($message->getLast())
            ->find();

        /** Если статус предыдущего события заявки не является Moving «Перемещение» - завершаем обработчик*/
        if(
            false === ($ProductStockEventLast instanceof ProductStockEvent) ||
            false === $ProductStockEventLast->equalsProductStockStatus(ProductStockStatusMoving::class)
        )
        {
            return;
        }

        /**
         * Получаем статус активного события заявки
         */

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        /** Если статус активного события не является Warehouse «Отправили на склад» */
        if(
            false === ($ProductStockEvent instanceof ProductStockEvent) ||
            false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusWarehouse::class)
        )
        {
            return;
        }

        // Получаем всю продукцию в заявке которая перемещается со склада
        $products = $this->productStocks->getProductsWarehouseStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        /** Идентификатор профиля склада отгрузки (из прошлого события!) */
        $UserProfileUid = $ProductStockEventLast->getStocksProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                sprintf('%s: Снимаем резерв и наличие на складе грузоотправителя при перемещении продукции', $ProductStockEvent->getNumber()),
                [
                    self::class.':'.__LINE__,
                    var_export($product, true)
                ]
            );

            /** Снимаем резерв и остаток на складе грузоотправителя */

            $productTotal = $product->getTotal();

            $SubProductStocksTotalMessage = new SubProductStocksTotalAndReserveMessage(
                order: $message->getId(),
                profile: $UserProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification(),
            );

            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorage
                ->forProfile($UserProfileUid)
                ->forProduct($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->count();

            /**
             * Если на складе количество мест одно - обновляем сразу весь резерв
             */

            if($storage === 1)
            {
                $SubProductStocksTotalMessage
                    ->setIterate(1)
                    ->setTotal($product->getTotal());

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalMessage,
                    transport: 'products-stocks-low',
                );

                continue;
            }

            /**
             * Если на складе количество мест несколько - снимаем резерв на единицу продукции
             * для резерва по местам от меньшего к большему
             */

            for($i = 1; $i <= $productTotal; $i++)
            {
                $SubProductStocksTotalMessage
                    ->setIterate($i)
                    ->setTotal(1);

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalMessage,
                    transport: 'products-stocks-low'
                );

                if($i === $productTotal)
                {
                    break;
                }
            }
        }

        $Deduplicator->save();
    }
}
