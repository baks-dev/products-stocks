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
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Резервирование на складе продукции при перемещении
 */
#[AsMessageHandler(priority: 1)]
final readonly class AddReserveProductStocksTotalByMove
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
    ) {}


    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusMoving::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем статус заявки */
        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        // Если Статус не является Статус Moving «Перемещение»
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusMoving::class))
        {
            return;
        }


        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $ProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning(
                'Заявка не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                'Складская заявка не может определить ProductStocksInvariable',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                'Добавляем резерв продукции на складе при создании заявки на перемещение',
                [
                    self::class.':'.__LINE__,
                    'total' => $product->getTotal(),
                    'number' => $ProductStockEvent->getNumber(),
                ]
            );

            /**
             * Добавляем резерв на единицу продукции (добавляем по одной для резерва от меньшего к большему)
             */

            $AddProductStocksReserve = new AddProductStocksReserveMessage(
                order: $message->getId(),
                profile: $UserProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification()
            );


            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorage
                ->forProfile($UserProfileUid)
                ->forProduct($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->count();

            if(false === $storage)
            {
                $this->logger->critical(
                    'Не найдено место складирования на складе для создания резерва перемещения',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $UserProfileUid,
                        var_export($AddProductStocksReserve, true),
                    ]
                );

                continue;
            }


            $productTotal = $product->getTotal();

            /**
             * Если на складе количество мест одно - обновляем сразу весь резерв
             */

            if($storage === 1)
            {
                $AddProductStocksReserve
                    ->setIterate(1)
                    ->setTotal($productTotal);

                $this->messageDispatch->dispatch(
                    $AddProductStocksReserve,
                    transport: 'products-stocks'
                );

                continue;
            }

            for($i = 1; $i <= $productTotal; $i++)
            {
                $AddProductStocksReserve
                    ->setIterate($i)
                    ->setTotal(1);

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
