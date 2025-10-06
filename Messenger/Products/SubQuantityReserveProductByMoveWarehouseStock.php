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

namespace BaksDev\Products\Stocks\Messenger\Products;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileLogisticWarehouse\UserProfileLogisticWarehouseInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимает резерв и отнимает количество продукции при перемещении между складами
 */
#[AsMessageHandler(priority: 1)]
final readonly class SubQuantityReserveProductByMoveWarehouseStock
{
    public function __construct(
        #[Target('productsProductLogger')] private LoggerInterface $Logger,
        private ProductModificationQuantityInterface $ModificationQuantity,
        private ProductVariationQuantityInterface $VariationQuantity,
        private ProductOfferQuantityInterface $OfferQuantity,
        private ProductQuantityInterface $ProductQuantity,
        private EntityManagerInterface $EntityManager,
        private DeduplicatorInterface $Deduplicator,
        private UserProfileLogisticWarehouseInterface $UserProfileLogisticWarehouse
    ) {}

    /**
     * Снимает резерв и отнимает количество продукции при перемещении между складами
     * Пополнение произойдет когда на склад будет приход
     */
    public function __invoke(ProductStockMessage $message): void
    {
        if(false === ($message->getLast() instanceof ProductStockEventUid))
        {
            return;
        }

        /** Получаем предыдущий статус заявки */
        $lastProductStockEvent = $this->EntityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getLast());

        /** Получаем текущий статус заявки */
        $productStockEvent = $this->EntityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(
            false === ($lastProductStockEvent instanceof ProductStockEvent)
            || false === ($productStockEvent instanceof ProductStockEvent)
        )
        {
            return;
        }

        // Если предыдущий Статус не является Moving «Перемещение»
        if(false === $lastProductStockEvent->equalsProductStockStatus(ProductStockStatusMoving::class))
        {
            return;
        }

        /**
         * Проверяем, является ли данный профиль логистическим складом
         */
        $isLogisticWarehouse = $this->UserProfileLogisticWarehouse
            ->forProfile($productStockEvent->getMove()?->getDestination())
            ->isLogisticWarehouse()
        ;

        if(false === $isLogisticWarehouse)
        {
            return;
        }

        // Получаем всю продукцию в ордере которая перемещается со склада
        // Если поступила отмена заявки - массив продукции будет NULL
        /** @see SubReserveMaterialStockTotalByCancel */
        $products = $lastProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->Logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        $Deduplicator = $this->Deduplicator
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

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->changeProduct($product);
        }

        $Deduplicator->save();
    }


    public function changeProduct(ProductStockProduct $product): void
    {
        $productUpdateQuantityReserve = null;

        // Количественный учет модификации множественного варианта торгового предложения
        if($product->getModification())
        {
            $this->EntityManager->clear();

            $productUpdateQuantityReserve = $this->ModificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );
        }

        // Количественный учет множественного варианта торгового предложения
        if(null === $productUpdateQuantityReserve && $product->getVariation())
        {
            $this->EntityManager->clear();

            $productUpdateQuantityReserve = $this->VariationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation()
            );
        }

        // Количественный учет торгового предложения
        if(null === $productUpdateQuantityReserve && $product->getOffer())
        {
            $this->EntityManager->clear();

            $productUpdateQuantityReserve = $this->OfferQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer()
            );
        }

        // Количественный учет продукта
        if(null === $productUpdateQuantityReserve)
        {
            $this->EntityManager->clear();

            $productUpdateQuantityReserve = $this->ProductQuantity->getProductQuantity(
                $product->getProduct()
            );
        }

        $context = [
            self::class.':'.__LINE__,
            'total' => $product->getTotal(),
            'ProductUid' => (string) $product->getProduct(),
            'ProductStockEventUid' => (string) $product->getEvent()->getId(),
            'ProductOfferConst' => (string) $product->getOffer(),
            'ProductVariationConst' => (string) $product->getVariation(),
            'ProductModificationConst' => (string) $product->getModification(),
        ];

        if(
            $productUpdateQuantityReserve &&
            $productUpdateQuantityReserve->subQuantity($product->getTotal()) &&
            $productUpdateQuantityReserve->subReserve($product->getTotal())
        )
        {
            $this->EntityManager->flush();
            $this->Logger->info('Перемещение: Сняли общий резерв и количество продукции в карточке при перемещении между складами', $context);
            return;
        }

        $this->Logger->critical('Перемещение: Невозможно общий резерв и количество продукции: карточка не найдена либо недостаточное количество резерва или остатка)', $context);
    }
}
