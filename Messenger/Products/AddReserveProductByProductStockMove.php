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
use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileLogisticWarehouse\UserProfileLogisticWarehouseInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Добавляет резерв продукции при перемещении
 */
#[AsMessageHandler(priority: 1)]
final readonly class AddReserveProductByProductStockMove
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $Logger,
        private ProductModificationQuantityInterface $ModificationQuantity,
        private ProductVariationQuantityInterface $VariationQuantity,
        private ProductOfferQuantityInterface $OfferQuantity,
        private ProductQuantityInterface $ProductQuantity,
        private EntityManagerInterface $EntityManager,
        private DeduplicatorInterface $Deduplicator,
        private UserProfileLogisticWarehouseInterface $UserProfileLogisticWarehouse
    ) {}

    /**
     * Добавляет резерв продукции при перемещении
     */
    public function __invoke(ProductStockMessage $message): void
    {
        $this->EntityManager->clear();

        $productStockEvent = $this->EntityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$productStockEvent)
        {
            return;
        }

        /** @var ProductStockEvent $productStockEvent */
        /** Если Статус не является Статус Moving «Перемещение» */
        if(false === $productStockEvent->equalsProductStockStatus(ProductStockStatusMoving::class))
        {
            return;
        }


        /**
         * Проверяем, является ли данный профиль логистическим складом
         */
        $isLogisticWarehouse = $this->UserProfileLogisticWarehouse
            ->forProfile($productStockEvent->getInvariable()?->getProfile())
            ->isLogisticWarehouse()
        ;

        if(false === $isLogisticWarehouse)
        {
            return;
        }


        // Получаем всю продукцию в заявке
        $products = $productStockEvent->getProduct();

        if(empty($products))
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

        $this->EntityManager->clear();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            $this->changeReserve($product);
        }

        $Deduplicator->save();
    }


    public function changeReserve(ProductStockProduct $product): void
    {
        $productUpdateReserve = null;

        // Количественный учет модификации множественного варианта торгового предложения
        if($product->getModification())
        {
            $this->EntityManager->clear();

            $productUpdateReserve = $this->ModificationQuantity->getProductModificationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation(),
                $product->getModification()
            );
        }

        // Количественный учет множественного варианта торгового предложения
        if(null === $productUpdateReserve && $product->getVariation())
        {
            $this->EntityManager->clear();

            $productUpdateReserve = $this->VariationQuantity->getProductVariationQuantity(
                $product->getProduct(),
                $product->getOffer(),
                $product->getVariation()
            );
        }

        // Количественный учет торгового предложения
        if(null === $productUpdateReserve && $product->getOffer())
        {
            $this->EntityManager->clear();

            $productUpdateReserve = $this->OfferQuantity->getProductOfferQuantity(
                $product->getProduct(),
                $product->getOffer()
            );
        }

        // Количественный учет продукта
        if(null === $productUpdateReserve)
        {
            $this->EntityManager->clear();

            $productUpdateReserve = $this->ProductQuantity->getProductQuantity(
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

        if($productUpdateReserve && $productUpdateReserve->addReserve($product->getTotal()))
        {
            $this->EntityManager->flush();
            $this->Logger->info('Перемещение: Добавили общий резерв продукции в карточке', $context);
            return;
        }

        $this->Logger->critical('Перемещение: Невозможно добавить общий резерв продукции (карточка не найдена)', $context);
    }
}
