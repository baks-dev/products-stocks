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

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockProduct;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\ExistOrderEventByStatus\ExistOrderEventByStatusInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Edit\EditOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Edit\Products\OrderProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\EditProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\EditProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Products\ProductStockProductDTO;
use BaksDev\Users\User\Repository\UserTokenStorage\UserTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При изменении заказа пересохраняет складскую заявку с продуктами, которые были в измененном заказе
 *
 * @note не изменяет статус складской заявки
 * @note всегда перезаписывает продукты в складской заявке
 * @note запускает процесс отслеживания изменений продукции в складской заявке
 * @see ReturnProductStockTotalDispatcher
 */
#[AsMessageHandler(priority: 0)]
final readonly class EditProductStockProductDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private CentrifugoPublishInterface $publish,
        private MessageDispatchInterface $messageDispatch,
        private EditProductStockHandler $editProductStockHandler,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierRepository,
        private CurrentOrderEventInterface $currentOrderEventRepository,
        private ProductStocksByOrderInterface $productStocksByOrderRepository,
        private UserTokenStorageInterface $userTokenStorageRepository,
        private ExistOrderEventByStatusInterface $ExistOrderEventByStatusRepository,
    ) {}

    public function __invoke(EditProductStockProductMessage $message): void
    {
        /** Проверяем, имеет ли заказ статус упаковки */

        $isPackage = $this->ExistOrderEventByStatusRepository
            ->forOrder($message->getOrderId())
            ->forStatus(OrderStatusPackage::class)
            ->isExists();

        /** Заказ без упаковки не имеет складской заявки и резервов */
        if(false === $isPackage)
        {
            return;
        }

        /** Текущее событие заказа */
        $OrderEvent = $this->currentOrderEventRepository
            ->forOrder($message->getOrderId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                message: sprintf('%s Не найдено активное событие заказа', $message->getOrderId()),
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /** Номер заказ */
        $OrderNumber = $OrderEvent->getOrderNumber();

        /** Скрываем заказ у всех пользователей */
        $this->publish
            ->addData(['order' => (string) $OrderEvent->getId()])
            ->send('orders');

        /** Получаем складскую заявку по идентификатору заказа */
        $productStocks = $this->productStocksByOrderRepository
            ->onOrder($OrderEvent->getMain())
            ->findAll();

        /** Если складская заявка по заказу не найдена - останавливаем процесс обработки заказа */
        if(true === empty($productStocks))
        {
            $this->logger->error(
                message: sprintf(
                    '%s Не найдена складская заявка по заказу со статусом `%s`',
                    $OrderNumber,
                    $OrderEvent->getStatus()->getOrderStatusValue(),
                ),
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );

            /*$this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('15 seconds')],
                    transport: 'products-stocks',
                );*/

            return;
        }

        /** @var ProductStockEvent $ProductStockEvent */
        $ProductStockEvent = current($productStocks);

        /**
         * Складская заявка для редактирования
         */
        $EditProductStockDTO = new EditProductStockDTO($ProductStockEvent->getId());
        $ProductStockEvent->getDto($EditProductStockDTO);

        /** Сбрасываем количество продуктов в складской заявке */
        $EditProductStockDTO->getProduct()->clear();

        /**
         * Измененный заказ
         */
        $EditOrderDTO = new EditOrderDTO();
        $OrderEvent->getDto($EditOrderDTO);

        /**
         * Находим расхождение в количестве продукции между ЗАКАЗОМ и его СКЛАДСКОЙ ЗАЯВКОЙ
         *
         * @var OrderProductDTO $OrderProductDTO
         */
        foreach($EditOrderDTO->getProduct() as $OrderProductDTO)
        {
            /** Получаем константы продукта */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifierRepository
                ->forEvent($OrderProductDTO->getProduct())
                ->forOffer($OrderProductDTO->getOffer())
                ->forVariation($OrderProductDTO->getVariation())
                ->forModification($OrderProductDTO->getModification())
                ->find();

            if(false === $CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult)
            {
                $this->logger->critical(
                    message: sprintf(
                        '%s Не найдены константы продукта по идентификаторам',
                        $OrderNumber,
                    ),
                    context: [self::class.':'.__LINE__, var_export($message, true)],
                );

                return;
            }

            $ProductStockProductDTO = new ProductStockProductDTO();
            $ProductStockProductDTO
                ->setProduct($CurrentProductIdentifierResult->getProduct())
                ->setOffer($CurrentProductIdentifierResult->getOfferConst())
                ->setVariation($CurrentProductIdentifierResult->getVariationConst())
                ->setModification($CurrentProductIdentifierResult->getModificationConst())
                ->setTotal($OrderProductDTO->getItem()->count());

            /** Добавляем новый продукт в складскую заявку  */
            $EditProductStockDTO->addProduct($ProductStockProductDTO);
        }

        /** Номер текущей заявки */
        $ProductStockNumber = $ProductStockEvent->getNumber();

        /** Invariable */
        $ProductStockInvariableDTO = $EditProductStockDTO->getInvariable();
        $ProductStockInvariableDTO
            ->setNumber($ProductStockNumber) // номер текущей заявки
            ->setUsr($message->getCurrentUser()) // user из сообщения
            ->setProfile($message->getUserProfile()); // profile из сообщения

        /** Авторизуем текущего пользователя для лога изменений если сообщение обрабатывается из очереди */
        if(false === $this->userTokenStorageRepository->isUser())
        {
            $this->userTokenStorageRepository->authorization($message->getCurrentUser());
        }

        $ProductStock = $this->editProductStockHandler->handle($EditProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('%s Ошибка при редактировании складской заявки %s для заказа %s',
                    $ProductStock,
                    $ProductStockNumber,
                    $OrderNumber,
                ),
                [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );
        }
    }
}
