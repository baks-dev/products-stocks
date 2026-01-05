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

namespace BaksDev\Products\Stocks\Messenger\Orders;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusDecommission;
use BaksDev\Orders\Order\UseCase\Admin\Canceled\CanceledOrderDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\DecommissionProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\DecommissionProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Invariable\DecommissionProductStockInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Orders\DecommissionProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Products\DecommissionProductStockProductDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает складскую заявку со статусом Decommission «Списание» на продукцию
 */
#[AsMessageHandler(priority: 0)]
final readonly class DecommissionProductStocksByDecommissionOrderDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifier,
        private DecommissionProductStockHandler $DecommissionProductStockHandler,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем активное состояние заказа */
        $OrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($message->getId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                'products-stocks: Не найдено событие OrderEvent',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * Складскую заявку на списание можно создать только при условии, если заказ со статусом Decommission «Списание»
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusDecommission::class))
        {
            return;
        }


        /**
         * Создаем заявку на сборку заказа на "Целевой склад для упаковки заказа"
         */

        $DecommissionProductStockDTO = new DecommissionProductStockDTO();

        // Присваиваем заявке склад для сборки
        $DecommissionProductStockInvariableDTO = new DecommissionProductStockInvariableDTO()
            ->setNumber($OrderEvent->getOrderNumber())
            ->setUsr($OrderEvent->getOrderUser())
            ->setProfile($OrderEvent->getOrderProfile());

        // Присваиваем заявке идентификатор заказа
        $DecommissionProductStockOrderDTO = new DecommissionProductStockOrderDTO()
            ->setOrd($OrderEvent->getMain());

        $DecommissionProductStockDTO
            ->setInvariable($DecommissionProductStockInvariableDTO)
            ->setOrd($DecommissionProductStockOrderDTO);

        foreach($OrderEvent->getProduct() as $OrderProduct)
        {
            /** Получаем идентификаторы констант продукции  */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifier
                ->forEvent($OrderProduct->getProduct())
                ->forOffer($OrderProduct->getOffer())
                ->forVariation($OrderProduct->getVariation())
                ->forModification($OrderProduct->getModification())
                ->find();

            $DecommissionProductStockProductDTO = new DecommissionProductStockProductDTO()
                ->setProduct($CurrentProductIdentifierResult->getProduct())
                ->setOffer($CurrentProductIdentifierResult->getOfferConst())
                ->setVariation($CurrentProductIdentifierResult->getVariationConst())
                ->setModification($CurrentProductIdentifierResult->getModificationConst())
                ->setTotal($OrderProduct->getTotal());

            $DecommissionProductStockDTO->addProduct($DecommissionProductStockProductDTO);
        }

        $ProductStock = $this->DecommissionProductStockHandler
            ->handle($DecommissionProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('products-stocks: Ошибка %s при создании складской заявки на списание заказа %s', $ProductStock, $OrderEvent->getOrderNumber()),
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
