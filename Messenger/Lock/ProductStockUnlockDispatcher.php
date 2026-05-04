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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Lock;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Lock\ProductStockLock;
use BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\EditProductStockTotalMessage;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Запускает процесс разблокировки складской заявки
 *
 * @note используется в конце обработки асинхронных сообщений
 * @note бросаем сообщение с САМЫМ низким приоритетом, чтобы добавить сообщение о разблокировке в конец очереди,
 * после всех сообщений, которые могут быть брошены другими обработчиками этого сообщения
 *
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: -1000)]
final readonly class ProductStockUnlockDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private CurrentProductStocksInterface $currentProductStocksRepository,
    ) {}

    public function __invoke(ProductStockMessage|EditProductStockTotalMessage $message): void
    {
        /** Активное событие складской заявки */
        $ProductStockEvent = $this->currentProductStocksRepository
            ->getCurrentEvent($message->getId());

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->critical(
                sprintf('products-stocks: %s: Не найдено активное событие ProductStockEvent',
                    $ProductStockEvent->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                sprintf('%s: не найдено ProductStocksInvariable',
                    $ProductStockEvent->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /** Если нет связи с блокировкой - прерываем обработчик */
        if(false === ($ProductStockEvent->getLock() instanceof ProductStockLock))
        {
            return;
        }

        /** Если складская заявка уже РАЗБЛОКИРОВАНА - прерываем обработчик */
        if(false === $ProductStockEvent->getLock()->getValue())
        {
            $this->logger->warning(
                message: sprintf('%s: складская заявка => уже РАЗБЛОКИРОВАНА в статусе %s',
                    $ProductStockEvent->getNumber(),
                    $ProductStockEvent->getStatus()->getProductStockStatusValue(),
                ),
                context: [self::class.':'.__LINE__, $message::class],
            );

            return;
        }

        $ProductStockUnlockMessage = new ProductStockUnlockMessage(
            id: $ProductStockEvent->getMain(),
            context: self::class.':'.__LINE__,
        );

        /** Отправляем сообщение для разблокировки */
        $this->messageDispatch->dispatch(
            message: $ProductStockUnlockMessage,
            transport: 'products-stocks',
        );
    }
}
