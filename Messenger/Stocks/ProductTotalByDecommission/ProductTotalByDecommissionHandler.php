<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Messenger\Stocks\ProductTotalByDecommission;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksTotal\SubProductStocksTotalAndReserveMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Снимает остаток со склада при списании
 */
#[AsMessageHandler(priority: 900)]
final readonly class ProductTotalByDecommissionHandler
{
    public function __construct(
        #[Target('ordersOrderLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(ProductTotalByDecommissionMessage $message): void
    {
        $this->MessageDispatch->dispatch(new SubProductStocksTotalAndReserveMessage(
            $message->getOrder(),
            $message->getProfile(),
            $message->getProduct(),
            $message->getOffer(),
            $message->getVariation(),
            $message->getModification(),
        )->setTotal($message->getTotal()));

        $this->logger->info(
            sprintf(
                'Product %s: Снимаем резерв и остаток продукта на складе при списании (см. products-stock.log)',
                $message->getProduct()
            ),
        );
    }
}