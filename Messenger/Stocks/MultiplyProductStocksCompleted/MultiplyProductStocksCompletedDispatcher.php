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

namespace BaksDev\Products\Stocks\Messenger\Stocks\MultiplyProductStocksCompleted;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedProductStockDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedProductStockHandler;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusExtradition;
use BaksDev\Users\User\Repository\UserTokenStorage\UserTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Обновляет складскую заявку на статус Completed «Выдан по месту назначения» */
#[AsMessageHandler(priority: 0)]
final readonly class MultiplyProductStocksCompletedDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CompletedProductStockHandler $CompletedProductStockHandler,
        private CentrifugoPublishInterface $publish,
        private UserTokenStorageInterface $UserTokenStorage,
        private DeduplicatorInterface $deduplicator,
        private ProductStocksEventInterface $ProductStocksEventRepository,
    ) {}


    public function __invoke(MultiplyProductStocksCompletedMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getProductStockEvent(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getProductStockEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->critical(
                'products-stocks: Складская заявка для статуса Completed «Выдан по месту назначения» не найдена',
                [self::class, var_export($message, true)],
            );

            return;
        }

        /** Выдать заявку можно только со статусом Extradition «Укомплектована, готова к выдаче» */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusExtradition::class))
        {
            $this->logger->critical(
                'products-stocks: Складскую заявку можно укомплектовать только со статусом Extradition «Укомплектована, готова к выдаче»',
                [self::class, var_export($message, true)],
            );

            return;
        }

        /** Скрываем идентификатор у всех пользователей */
        $this->publish
            ->addData(['profile' => false]) // Скрывает у всех
            ->addData(['identifier' => (string) $ProductStockEvent->getMain()])
            ->send('remove');

        /**
         * Обновляем складскую заявку
         */

        $CompletedProductStockDTO = new CompletedProductStockDTO();
        $ProductStockEvent->getDto($CompletedProductStockDTO);


        /** Авторизуем текущего пользователя для лога изменений если сообщение обрабатывается из очереди */
        if(false === $this->UserTokenStorage->isUser())
        {
            $this->UserTokenStorage->authorization($message->getCurrentUser());
        }

        $ProductStock = $this->CompletedProductStockHandler->handle($CompletedProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('products-stocks: Ошибка %s при обновлении складской заявки %s на статус Completed «Выдан по месту назначения»',
                    $ProductStock,
                    $ProductStockEvent->getNumber(),
                ),
                [self::class, var_export($message, true)],
            );

            return;
        }

        $Deduplicator->save();

        $this->logger->info(
            sprintf('%s: Обновили складскую заявку на статус Completed «Выдан по месту назначения»', $ProductStockEvent->getNumber()),
            [self::class, var_export($message, true)],
        );

    }
}
