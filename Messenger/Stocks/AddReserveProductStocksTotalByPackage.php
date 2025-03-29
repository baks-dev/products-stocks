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
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Резервирование на складе продукции при статусе "ОТПАРВЛЕН НА СБОРКУ"
 */
#[AsMessageHandler(priority: 1)]
final readonly class AddReserveProductStocksTotalByPackage
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksByIdInterface $productStocks,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        $ProductStockEvent = $this
            ->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->productStocks->getProductsPackageStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        /** Идентификатор профиля, куда была отправлена заявка на упаковку */
        $UserProfileUid = $ProductStockEvent->getStocksProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->logger->info(
                'Добавляем резерв продукции на складе при создании заявки на упаковку',
                ['total' => $product->getTotal()]
            );

            /**
             * Создаем резерв на единицу продукции при упаковке
             */

            $AddProductStocksReserve = new AddProductStocksReserveMessage(
                profile: $UserProfileUid,
                product: $product->getProduct(),
                offer: $product->getOffer(),
                variation: $product->getVariation(),
                modification: $product->getModification(),
                stock: $message->getId()
            );

            $productTotal = $product->getTotal();

            for($i = 1; $i <= $productTotal; $i++)
            {
                $AddProductStocksReserve
                    ->setIterate($i);

                $this->messageDispatch->dispatch(
                    $AddProductStocksReserve,
                    stamps: [new MessageDelay('1 seconds')],
                    transport: 'products-stocks'
                );

                if($i === $productTotal)
                {
                    break;
                }
            }
        }

        $DeduplicatorExecuted->save();
    }
}
