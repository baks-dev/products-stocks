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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Repository\UpdateProductQuantity\SubProductQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileLogisticWarehouse\UserProfileLogisticWarehouseInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимает резерв в карточке продукции при отмене заявки Cancel «Отменен»
 */
#[AsMessageHandler(priority: 1)]
final readonly class SubReserveProductByProductStockCancel
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentProductIdentifierByConstInterface $currentProductIdentifierByConst,
        private CurrentProductStocksInterface $CurrentProductStocks,
        private SubProductQuantityInterface $subProductQuantity,
        private DeduplicatorInterface $deduplicator,
        private UserProfileLogisticWarehouseInterface $UserProfileLogisticWarehouse
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        if(false === ($message->getLast() instanceof ProductStockEventUid))
        {
            return;
        }

        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }


        /** Получаем статус заявки */
        $ProductStockEvent = $this->CurrentProductStocks->getCurrentEvent($message->getId());

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /** Если Статус НЕ является Cancel «Отменен» - завершаем работу */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class))
        {
            return;
        }

        /**
         * Если заявка по заказу - не снимаем резерв в карточке (будет снят при отмене заказа)
         *
         */
        if($ProductStockEvent->getOrder() instanceof OrderUid)
        {
            return;
        }

        /**
         * Проверяем, является ли данный профиль логистическим складом
         */

        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();

        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            return;
        }

        $isLogisticWarehouse = $this->UserProfileLogisticWarehouse
            ->forProfile($UserProfileUid)
            ->isLogisticWarehouse();

        if(false === $isLogisticWarehouse)
        {
            return;
        }

        // Получаем всю продукцию в заявке со статусом Cancel «Отменен»
        $products = $ProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning(
                'Заявка не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->changeReserve($product);
        }

        $DeduplicatorExecuted->save();
    }

    public function changeReserve(ProductStockProduct $product): void
    {
        $CurrentProductDTO = $this->currentProductIdentifierByConst
            ->forProduct($product->getProduct())
            ->forOfferConst($product->getOffer())
            ->forVariationConst($product->getVariation())
            ->forModificationConst($product->getModification())
            ->find();

        if(false === ($CurrentProductDTO instanceof CurrentProductIdentifierResult))
        {
            $this->logger->critical(
                'products-stocks: Невозможно отменить общий резерв (карточка не найдена)',
                [$product, self::class.':'.__LINE__],
            );

            return;
        }

        $rows = $this->subProductQuantity
            ->forEvent($CurrentProductDTO->getEvent())
            ->forOffer($CurrentProductDTO->getOffer())
            ->forVariation($CurrentProductDTO->getVariation())
            ->forModification($CurrentProductDTO->getModification())
            ->subQuantity(false)
            ->subReserve($product->getTotal())
            ->update();

        if($rows)
        {
            $this->logger->info(
                'Перемещение: Отменили общий резерв в карточке при отмене складской заявки на перемещение',
                [$product, self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->critical(
            'products-stocks: Невозможно отменить общий резерв продукции (карточка не найдена либо недостаточное количество резерва)',
            [$product, self::class.':'.__LINE__],
        );
    }
}
