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

namespace BaksDev\Products\Stocks\Messenger\Orders\MultiplyProductStocksPackage;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\DeliveryTransport\BaksDevDeliveryTransportBundle;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\MultiplyOrdersPackage\MultiplyOrdersPackageMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\ExistOrderEventByStatus\ExistOrderEventByStatusInterface;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Orders\ProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Products\ProductStockDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class MultiplyProductStocksPackageDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private PackageProductStockHandler $PackageProductStockHandler,
        private CentrifugoPublishInterface $publish,
    ) {}

    public function __invoke(MultiplyProductStocksPackageMessage $message): void
    {

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getOrderId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }


        $OrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($message->getOrderId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                sprintf('products-stocks: Ошибка при создании складской заявки на продукцию заказа %s', $message->getOrderId()),
                [self::class],
            );

            return;
        }

        /** Скрываем заказ у всех пользователей */
        $this->publish
            ->addData(['order' => (string) $message->getOrderId()])
            ->send('orders');

        /**
         * Создаем заявку на сборку заказа на "Целевой склад для упаковки заказа"
         */

        $PackageProductStockDTO = new PackageProductStockDTO();
        $OrderEvent->getDto($PackageProductStockDTO);


        /**
         * Трансформируем идентификаторы продукта в константы
         */

        $PackageProductStockDTO->setProduct(new ArrayCollection());

        foreach($OrderEvent->getProduct() as $OrderProduct)
        {
            /** Получаем идентификаторы констант продукции  */
            $currentProductIdentifierResult = $this->CurrentProductIdentifier
                ->forEvent($OrderProduct->getProduct())
                ->forOffer($OrderProduct->getOffer())
                ->forVariation($OrderProduct->getVariation())
                ->forModification($OrderProduct->getModification())
                ->find();

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($currentProductIdentifierResult->getProduct())
                ->setOffer($currentProductIdentifierResult->getOfferConst())
                ->setVariation($currentProductIdentifierResult->getVariationConst())
                ->setModification($currentProductIdentifierResult->getModificationConst())
                ->setTotal($OrderProduct->getTotal());

            $PackageProductStockDTO->addProduct($ProductStockDTO);
        }

        // Присваиваем заявке склад для сборки
        $PackageProductStockDTO
            ->getInvariable()
            ->setNumber($OrderEvent->getOrderNumber())
            ->setUsr($OrderEvent->getOrderUser())
            ->setProfile($message->getUserProfile());

        // Присваиваем заявке идентификатор заказа
        $productStockOrderDTO = new ProductStockOrderDTO();
        $productStockOrderDTO->setOrd($OrderEvent->getMain());

        $PackageProductStockDTO->setOrd($productStockOrderDTO);


        $ProductStock = $this->PackageProductStockHandler->handle($PackageProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('products-stocks: Ошибка %s при создании заявки на упаковку заказа %s', $ProductStock, $OrderEvent->getOrderNumber()),
                [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        $Deduplicator->save();
    }
}
