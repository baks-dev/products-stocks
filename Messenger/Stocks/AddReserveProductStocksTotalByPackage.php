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
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksTotal\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Error\ErrorProductStocksDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Error\ErrorProductStocksHandler;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
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
    private ProductWarehouseTotalInterface $productWarehouseTotal;
    private ErrorProductStocksHandler $errorProductStocksHandler;

    public function __construct(

        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $ProductStockStatusCollection,
        LoggerInterface $productsStocksLogger,
        CurrentProductStocksInterface $currentProductStocks,
        MessageDispatchInterface $messageDispatch,
        ProductWarehouseTotalInterface $productWarehouseTotal,
        ErrorProductStocksHandler $errorProductStocksHandler
    )
    {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;

        $this->logger = $productsStocksLogger;
        $this->currentProductStocks = $currentProductStocks;
        $this->messageDispatch = $messageDispatch;

        // Инициируем статусы складских остатков
        $ProductStockStatusCollection->cases();

        $this->productWarehouseTotal = $productWarehouseTotal;

        $this->errorProductStocksHandler = $errorProductStocksHandler;
    }

    /**
     * Резервирование на складе продукции при статусе "ОТПАРВЛЕН НА СБОРКУ"
     */
    public function __invoke(ProductStockMessage $message): void
    {
        $this->entityManager->clear();

        $ProductStockEvent = $this->currentProductStocks->getCurrentEvent($message->getId());

        if(!$ProductStockEvent)
        {
            return;
        }

        if($ProductStockEvent->getStatus()->equals(ProductStockStatusPackage::class) === false)
        {
            $this->logger->notice('Не создаем резерв на складе: Складская заявка не является Package «Упаковка»',
                [
                    __FILE__.':'.__LINE__,
                    'ProductStockUid' => (string) $message->getId(),
                    'event' => (string) $message->getEvent(),
                    'last' => (string) $message->getLast()
                ]);
            return;
        }


        if($message->getLast())
        {
            $lastProductStockEvent = $this->entityManager->getRepository(ProductStockEvent::class)->find($message->getLast());

            if(!$lastProductStockEvent || $lastProductStockEvent->getStatus()->equals(new ProductStockStatusIncoming()) === true)
            {
                $this->logger->notice('Не создаем резерв на складе: Складская заявка при поступлении на склад по заказу (резерв уже имеется)',
                    [
                        __FILE__.':'.__LINE__,
                        'ProductStockUid' => (string) $message->getId(),
                        'event' => (string) $message->getEvent(),
                        'last' => (string) $message->getLast()
                    ]);

                return;
            }
        }


        $this->logger
            ->info('Добавляем резерв продукции на складе статусе заявки Package «Упаковка» заказа',
                [
                    __FILE__.':'.__LINE__,
                    'ProductStockUid' => (string) $message->getId(),
                    'event' => (string) $message->getEvent(),
                    'last' => (string) $message->getLast()
                ]);

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

            /** Получаем общее количество на указанном профиле (складе) c учетом резерва */

            $ProductStockTotal = $this->productWarehouseTotal
                ->getProductProfileTotal(
                    $UserProfileUid,
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                );


            if(empty($ProductStockTotal) || $product->getTotal() > $ProductStockTotal)
            {
                // Присваиваем ошибку заявке
                $ErrorProductStocksDTO = new ErrorProductStocksDTO();
                $ProductStockEvent->getDto($ErrorProductStocksDTO);
                $this->errorProductStocksHandler->handle($ErrorProductStocksDTO);

                $this->logger->critical('Невозможно зарезервировать продукцию, которой нет на складе',
                    [
                        __FILE__.':'.__LINE__,
                        'number' => $ProductStockEvent->getNumber(),
                        'event' => (string) $message->getEvent(),
                        'profile' => (string) $UserProfileUid,
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                        'modification' => (string) $product->getModification(),
                        'total' => $product->getTotal(),
                    ]
                );

                throw new DomainException('Невозможно зарезервировать продукцию, которой нет на складе');
            }


            /**
             * Создаем резерв на единицу продукции
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

                $this->messageDispatch->dispatch($AddProductStocksReserve, transport: 'products-stocks');
            }


            $this->logger->info('Добавляем резерв продукции на складе при создании заявки на упаковку',
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

        }


    }
}
