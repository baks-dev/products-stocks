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
 *
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
        #[Target('productsStocksLogger')] private LoggerInterface $Logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
        private MessageDispatchInterface $MessageDispatch,
        private DeduplicatorInterface $Deduplicator,
    ) {}


    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->Deduplicator
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
        $productStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($productStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /** Если Статус не является Статус Moving «Перемещение» - завершаем работу */
        if(false === $productStockEvent->equalsProductStockStatus(ProductStockStatusMoving::class))
        {
            return;
        }


        // Получаем всю продукцию в ордере со статусом Moving (перемещение)
        $products = $productStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->Logger->warning(
                'Заявка не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        if(false === $productStockEvent->isInvariable())
        {
            $this->Logger->warning(
                'Складская заявка не может определить ProductStocksInvariable',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        $userProfileUid = $productStockEvent->getInvariable()?->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->Logger->info(
                'Добавляем резерв продукции на складе при создании заявки на перемещение',
                [
                    self::class.':'.__LINE__,
                    'total' => $product->getTotal(),
                    'number' => $productStockEvent->getNumber(),
                ]
            );

            /**
             * Добавляем резерв на единицу продукции (добавляем по одной для резерва от меньшего к большему)
             */

            $addProductStocksReserve = new AddProductStocksReserveMessage(
                order: $message->getId(),
                profile: $userProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification()
            );


            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorage
                ->forProfile($userProfileUid)
                ->forProduct($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->count();

            if(false === $storage)
            {
                $this->Logger->critical(
                    'Не найдено место складирования на складе для создания резерва перемещения',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $userProfileUid,
                        var_export($addProductStocksReserve, true),
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
                $addProductStocksReserve
                    ->setIterate(1)
                    ->setTotal($productTotal);

                $this->MessageDispatch->dispatch(
                    $addProductStocksReserve,
                    transport: 'products-stocks'
                );

                continue;
            }

            for($i = 1; $i <= $productTotal; $i++)
            {
                $addProductStocksReserve
                    ->setIterate($i)
                    ->setTotal(1);

                $this->MessageDispatch->dispatch(
                    $addProductStocksReserve,
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
