<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Repository\UpdateProductQuantity\AddProductQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCancel;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileLogisticWarehouse\UserProfileLogisticWarehouseInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Пополнение наличием в карточке при поступлении на склад
 */
#[AsMessageHandler(priority: 20)]
final readonly class AddQuantityProductByIncomingStock
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentProductIdentifierByConstInterface $currentProductIdentifierByConst,
        private AddProductQuantityInterface $addProductQuantity,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private DeduplicatorInterface $deduplicator,
        private UserProfileLogisticWarehouseInterface $UserProfileLogisticWarehouse
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class],
            );

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

        /**
         * Если статус НЕ Incoming «Приход на склад» либо НЕ Cancel «Отменен» - завершаем работу
         */
        if(
            false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class)
            && false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class)
        )
        {
            return;
        }

        /** Получаем предыдущее событие */

        $LastProductStockEvent = $this
            ->ProductStocksEventRepository
            ->forEvent($message->getLast())
            ->find();


        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }


        /** Если статус Cancel «Отменен», и предыдущее событие НЕ является Completed «Выдан по месту назначения»  */
        if(
            true === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCancel::class)
            && false === $LastProductStockEvent->equalsProductStockStatus(ProductStockStatusCompleted::class)
        )
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

        /** Не пополняем остаток в карточке, если профиль не является логистическим складом */
        if(false === $isLogisticWarehouse)
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $ProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning(
                'Заявка не имеет продукции в коллекции',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        $this->logger->info(
            'Пополнение наличием в карточке при поступлении на склад',
            [self::class.':'.__LINE__, var_export($message, true)],
        );

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            /** Пополняем наличие карточки */
            $this->changeTotal($product);
        }

        $DeduplicatorExecuted->save();
    }

    public function changeTotal(ProductStockProduct $product): void
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
                'products-stocks: Невозможно пополнить общий остаток (карточка не найдена)',
                [$product, self::class.':'.__LINE__],
            );

            return;
        }

        $rows = $this->addProductQuantity
            ->forEvent($CurrentProductDTO->getEvent())
            ->forOffer($CurrentProductDTO->getOffer())
            ->forVariation($CurrentProductDTO->getVariation())
            ->forModification($CurrentProductDTO->getModification())
            ->addQuantity($product->getTotal())
            ->addReserve(false)
            ->update();

        if($rows)
        {
            $this->logger->info(
                'Пополнили общий остаток в карточке при поступлении на склад',
                [$product, self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->critical(
            'products-stocks: Невозможно пополнить общий остаток (карточка не найдена)',
            [$product, self::class.':'.__LINE__],
        );
    }
}
