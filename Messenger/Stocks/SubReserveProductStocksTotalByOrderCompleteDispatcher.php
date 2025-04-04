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
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимаем резерв и остаток со склада продукции при статусе заказа Completed «Выполнен»
 */
#[AsMessageHandler(priority: 60)]
final readonly class SubReserveProductStocksTotalByOrderCompleteDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private OrderEventInterface $OrderEventRepository,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private CountProductStocksStorageInterface $CountProductStocksStorage,
    ) {}

    public function __invoke(OrderMessage $message): void
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

        $OrderEvent = $this->OrderEventRepository
            ->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            return;
        }

        /** Если статус заказа не Completed «Выполнен» */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCompleted::class))
        {
            return;
        }

        /** Получаем текущее состояние заказа, в случае если событие изменилось  */
        if(false === ($OrderEvent->getOrderProfile() instanceof UserProfileUid))
        {
            $OrderEvent = $this->CurrentOrderEvent
                ->forOrder($message->getId())
                ->find();

            if(false === ($OrderEvent instanceof OrderEvent))
            {
                $this->logger->critical(
                    'products-stocks: Не найдено событие OrderEvent',
                    [self::class.':'.__LINE__, var_export($message, true)]
                );

                return;
            }
        }


        if($OrderEvent->getProduct()->isEmpty())
        {
            $this->logger->critical(
                'products-stocks: Не найдено продукции в заказе',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        $UserProfileUid = $OrderEvent->getOrderProfile();

        /** @var OrderProduct $product */
        foreach($OrderEvent->getProduct() as $product)
        {
            /**
             * Получаем идентификаторы карточки
             * @note: в заказе идентификаторы события, для склада необходимы константы
             */
            $CurrentProductIdentifier = $this->CurrentProductIdentifier
                ->forEvent($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->find();

            if(false === ($CurrentProductIdentifier instanceof CurrentProductIdentifierResult))
            {
                $this->logger->critical(
                    'products-stocks: Невозможно снять резерв и остаток на складе (карточка не найдена)',
                    [$product, self::class.':'.__LINE__]
                );

                return;
            }

            $this->logger->info('Снимаем резерв и остаток на складе при выполненном заказа:');

            $SubProductStocksTotalMessage = new SubProductStocksTotalAndReserveMessage(
                order: $message->getId(),
                profile: $UserProfileUid,
                product: $CurrentProductIdentifier->getProduct(),
                offer: $CurrentProductIdentifier->getOfferConst(),
                variation: $CurrentProductIdentifier->getVariationConst(),
                modification: $CurrentProductIdentifier->getModificationConst(),
            );


            /** Поверяем количество мест складирования продукции на складе */

            $storage = $this->CountProductStocksStorage
                ->forProfile($UserProfileUid)
                ->forProduct($CurrentProductIdentifier->getProduct())
                ->forOffer($CurrentProductIdentifier->getOfferConst())
                ->forVariation($CurrentProductIdentifier->getVariationConst())
                ->forModification($CurrentProductIdentifier->getModificationConst())
                ->count();

            if(false === $storage)
            {
                $this->logger->critical(
                    'Не найдено место складирования для полного списания продукции при выполненном заказе',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $UserProfileUid,
                        var_export($SubProductStocksTotalMessage, true),
                    ]
                );
            }

            /**
             * Если на складе количество мест одно - обновляем сразу весь резерв
             */

            if($storage === 1)
            {
                $SubProductStocksTotalMessage
                    ->setIterate(1)
                    ->setTotal($product->getTotal());

                $this->messageDispatch->dispatch(
                    $SubProductStocksTotalMessage,
                    transport: 'products-stocks-low',
                );

                continue;
            }


            /**
             * Снимаем резерв и остаток продукции на складе по одной единице продукции
             */

            $productTotal = $product->getTotal();

            for($i = 1; $i <= $productTotal; $i++)
            {
                $SubProductStocksTotalMessage
                    ->setIterate($i)
                    ->setTotal(1);

                $this->messageDispatch->dispatch(
                    message: $SubProductStocksTotalMessage,
                    transport: 'products-stocks-low',
                );

                if($i === $product->getTotal())
                {
                    break;
                }
            }
        }

        $DeduplicatorExecuted->save();
    }

}
