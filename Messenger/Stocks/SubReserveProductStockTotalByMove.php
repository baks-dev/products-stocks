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
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class SubReserveProductStockTotalByMove
{
    public function __construct(
        #[Target('productsStocksLogger')] private readonly LoggerInterface $logger,
        private ProductStocksByIdInterface $productStocks,
        private EntityManagerInterface $entityManager,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     * Снимаем резерв и наличие со склада отгрузки при статусе Moving «Перемещение»
     */
    public function __invoke(ProductStockMessage $message): void
    {
        if($message->getLast() === null)
        {
            return;
        }

        /** Получаем статус прошлого события заявки */
        $ProductStockEventLast = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getLast());

        /** Если статус предыдущего события заявки не является Moving «Перемещение» - завершаем обработчик*/
        if(!$ProductStockEventLast || false === $ProductStockEventLast->getStatus()->equals(ProductStockStatusMoving::class))
        {
            return;
        }

        /** Получаем статус активного события заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());


        /** Если статус активного события не является Warehouse «Отправили на склад» */
        if(!$ProductStockEvent || false === $ProductStockEvent->getStatus()->equals(ProductStockStatusWarehouse::class))
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

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusMoving::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Идентификатор профиля склада отгрузки (из прошлого события!) */
        $UserProfileUid = $ProductStockEventLast->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {

            $this->logger->info(
                'Снимаем резерв и наличие на складе грузоотправителя при перемещении продукции',
                [
                    self::class.':'.__LINE__,
                    'number' => $ProductStockEvent->getNumber(),
                    'event' => (string) $message->getEvent(),
                    'profile' => (string) $ProductStockEvent->getProfile(),
                    'product' => (string) $product->getProduct(),
                    'offer' => (string) $product->getOffer(),
                    'variation' => (string) $product->getVariation(),
                    'modification' => (string) $product->getModification(),
                    'total' => $product->getTotal(),
                ]
            );

            /** Снимаем резерв и остаток на единицу продукции на складе грузоотправителя */
            for($i = 1; $i <= $product->getTotal(); $i++)
            {
                $SubProductStocksTotalMessage = new SubProductStocksTotalAndReserveMessage(
                    $UserProfileUid,
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                );

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalMessage,
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
