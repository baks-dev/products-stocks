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

use BaksDev\Centrifugo\BaksDevCentrifugoBundle;
use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Lock\ProductStockLock;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Lock\ProductStockLockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Lock\ProductStockLockHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Блокирует складскую заявку, слушая отдельное сообщение о блокировке
 *
 * @note используется там, где нам необходимо синхронно складскую заявку
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 1000)]
final readonly class ProductStockLockDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentProductStocksInterface $currentProductStocksRepository,
        private ProductStockLockHandler $productStockLockHandler,
        private ?CentrifugoPublishInterface $centrifugoPublish = null,
    ) {}

    public function __invoke(ProductStockLockMessage $message): void
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

        /** Если складская заявка уже ЗАБЛОКИРОВАНА - прерываем обработчик */
        if(true === $ProductStockEvent->getLock()->isLock())
        {
            $this->logger->warning(
                message: sprintf('%s: складская заявка => уже РАЗБЛОКИРОВАНА в статусе %s',
                    $ProductStockEvent->getNumber(),
                    $ProductStockEvent->getStatus()->getProductStockStatusValue(),
                ),
                context: [self::class.':'.__LINE__, $message->getContext()],
            );

            return;
        }

        $ProductStockLockDTO = new ProductStockLockDTO($ProductStockEvent->getId());
        $ProductStockEvent->getLock()->getDto($ProductStockLockDTO);

        $ProductStockLockDTO->lock(); // ставим блокировку

        $ProductStockLock = $this->productStockLockHandler->handle($ProductStockLockDTO);

        if(false === ($ProductStockLock instanceof ProductStockLock))
        {
            $this->logger->critical(
                message: sprintf('%s: Ошибка при снятии блокировки с складской заявки',
                    $ProductStockEvent->getNumber(),
                ),
                context: [self::class.':'.__LINE__],
            );
        }

        $this->logger->info(
            message: sprintf('%s: складская заявка => ЗАБЛОКИРОВАЛИ в статусе %s',
                $ProductStockEvent->getNumber(),
                $ProductStockEvent->getStatus()->getProductStockStatusValue(),
            ),
            context: [self::class.':'.__LINE__, $message->getContext()],
        );

        if(true === class_exists(BaksDevCentrifugoBundle::class))
        {
            /**
             * Блокируем складскую заявку
             */

            $socket = $this->centrifugoPublish
                ->addData([
                    'stock' => (string) $ProductStockEvent->getMain(), // для поиска карточки
                    'number' => (string) $ProductStockEvent->getNumber(), // номер складской заявки
                    'lock' => true, // блокировка на UI
                    'context' => self::class.':'.__LINE__,

                ])
                ->send('stocks'); // канал для обработки складской заявки

            if($socket && $socket->isError())
            {
                $this->logger->critical(
                    message: 'products-stocks: Ошибка при отправке информации о блокировке в Centrifugo',
                    context: [
                        $socket->getMessage(),
                        'number' => $ProductStockEvent->getNumber(),
                        'main' => $ProductStockEvent->getMain(),
                        self::class.':'.__LINE__,
                    ],
                );
            }
        }
    }
}
