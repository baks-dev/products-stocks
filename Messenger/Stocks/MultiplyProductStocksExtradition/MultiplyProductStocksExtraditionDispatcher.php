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

namespace BaksDev\Products\Stocks\Messenger\Stocks\MultiplyProductStocksExtradition;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Messenger\LockOrder\OrderUnlockMessage;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockHandler;
use BaksDev\Users\User\Repository\UserTokenStorage\UserTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Изменяет статус складской заявки на Extradition «Укомплектована, готова к выдаче»
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class MultiplyProductStocksExtraditionDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private CentrifugoPublishInterface $publish,
        private UserTokenStorageInterface $UserTokenStorageRepository,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private ExtraditionProductStockHandler $ExtraditionProductStockHandler,
    ) {}

    public function __invoke(MultiplyProductStocksExtraditionMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getProductStockEvent(),
                self::class.':'.__LINE__,
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
                'products-stocks: Складская заявка для статуса Extradition «Укомплектован» не найдена',
                [self::class, var_export($message, true)],
            );

            return;
        }

        /** Укомплектовать заявку можно только со статусом «Упаковка» */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            $this->logger->critical(
                'products-stocks: Складскую заявку можно укомплектовать только со статусом Package «Упаковка»',
                [self::class, var_export($message, true)],
            );

            return;
        }

        /** Скрываем элементы */

        $this->publish
            ->addData([
                'identifier' => (string) $ProductStockEvent->getMain(),
                'profile' => false, // Скрывает у всех
                'context' => self::class.':'.__LINE__,
            ])
            ->send('remove');

        $this->publish
            ->addData([
                'identifier' => (string) $ProductStockEvent->getId(),
                'profile' => false, // Скрывает у всех
                'context' => self::class.':'.__LINE__,
            ])
            ->send('remove');

        $this->publish
            ->addData([
                'order' => (string) $ProductStockEvent->getOrder(),
                'profile' => false, // Скрывает у всех
                'context' => self::class.':'.__LINE__,
            ])
            ->send('orders');

        /**
         * Обновляем складскую заявку
         */

        $ExtraditionProductStockDTO = new ExtraditionProductStockDTO();
        $ProductStockEvent->getDto($ExtraditionProductStockDTO);

        if($message->getComment())
        {
            $ExtraditionProductStockDTO->setComment($message->getComment());
        }

        /** Авторизуем текущего пользователя для лога изменений если сообщение обрабатывается из очереди */
        if(false === $this->UserTokenStorageRepository->isUser())
        {
            $this->UserTokenStorageRepository->authorization($message->getCurrentUser());
        }

        $ProductStock = $this->ExtraditionProductStockHandler->handle($ExtraditionProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('products-stocks: Ошибка %s при обновлении складской заявки %s на статус Extradition «Укомплектован»',
                    $ProductStock,
                    $ProductStockEvent->getNumber(),
                ),
                [self::class, var_export($message, true)],
            );

            return;
        }

        $Deduplicator->save();
    }
}
