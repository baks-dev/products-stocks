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

namespace BaksDev\Products\Stocks\Messenger\Stocks\ProductStockApprove;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\ProductStockSettingsByProfileInterface;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\ProductStockSettingsByProfileResult;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalByProduct\ProductStocksTotalByProductInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalDTO;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Задаем значение approve ProductStockTotal = false, при условии,
 * что заявка Completed «Выдан по месту назначения»
 */
#[AsMessageHandler(priority: 0)]
final readonly class UpdateStockTotalApproveDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private DeduplicatorInterface $deduplicator,
        private ProductStockSettingsByProfileInterface $ProductStocksSettingsByProfileRepository,
        private ProductStocksTotalByProductInterface $ProductStocksTotalByProductRepository,
        private ApproveProductStockTotalHandler $approveHandler,

    ) {}

    public function __invoke(ProductStockMessage $message): void
    {

        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        /** @var ProductStockEvent $CurrentProductStockEvent */
        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();


        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /**
         * Approve обновится только при условии, что заявка Completed «Выдан по месту назначения»
         */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCompleted::class))
        {
            return;
        }


        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                'Складская заявка не может определить ProductStocksInvariable',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }


        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();

        /* Настройки складского учета профиля */
        $ProductStockSettingsByProfileResult = $this->ProductStocksSettingsByProfileRepository
            ->profile($UserProfileUid)
            ->find();


        if(false === ($ProductStockSettingsByProfileResult instanceof ProductStockSettingsByProfileResult))
        {
            return;
        }

        /** Не проверяем если порог равен 0  */
        if(empty($ProductStockSettingsByProfileResult->getThreshold()))
        {
            return;
        }


        $products = $ProductStockEvent->getProduct();

        foreach($products as $product)
        {

            /**
             * Получаем все места складирования данного продукта на складе профиля
             *
             * @var ProductStockProduct $product
             */
            $ProductStockTotals = $this->ProductStocksTotalByProductRepository
                ->profile($UserProfileUid)
                ->product($product->getProduct())
                ->offer($product->getOffer())
                ->variation($product->getVariation())
                ->modification($product->getModification())
                ->findAll();

            foreach($ProductStockTotals as $ProductStockTotal)
            {
                /** @var ProductStockTotal $ProductStockTotal */
                /* Обновить при условии, что разница total - reserve меньше заданного порога */
                if($ProductStockSettingsByProfileResult->getThreshold() > ($ProductStockTotal->getTotal() - $ProductStockTotal->getReserve()))
                {
                    $ApproveProductStockTotalDTO = new ApproveProductStockTotalDTO();
                    $ProductStockTotal->getDto($ApproveProductStockTotalDTO);

                    /** Требуем подтверждение остатка флагом FALSE в данном месте складирования */
                    $ApproveProductStockTotalDTO->getApprove()->setValue(false);

                    $handle = $this->approveHandler->handle($ApproveProductStockTotalDTO);

                    if(false === ($handle instanceof ProductStockTotal))
                    {
                        $this->logger->critical(
                            sprintf("Ошибка обновления approve для места %s", $ProductStockTotal->getId()),
                        );
                    }

                    $this->logger->info(
                        sprintf("Настройка approve для места %s установлена в false", $ProductStockTotal->getId()),
                    );

                }
            }

        }

        $DeduplicatorExecuted->save();

    }
}