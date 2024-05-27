<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksTotal;


use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockMinQuantityInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddProductStocksReserve
{
    private ProductStockMinQuantityInterface $productStockMinQuantity;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductStockMinQuantityInterface $productStockMinQuantity,
        LoggerInterface $productsStocksLogger
    )
    {
        $this->productStockMinQuantity = $productStockMinQuantity;
        $this->entityManager = $entityManager;
        $this->logger = $productsStocksLogger;
    }

    /**
     * Создает резерв на единицу продукции на указанный склад начиная с минимального наличия
     */
    public function __invoke(AddProductStocksReserveMessage $message): void
    {
        $this->entityManager->clear();

        $ProductStockTotal = $this->productStockMinQuantity
            ->profile($message->getProfile())
            ->product($message->getProduct())
            ->offerConst($message->getOffer())
            ->variationConst($message->getVariation())
            ->modificationConst($message->getModification())
            ->findOneBySubReserve();

        if(!$ProductStockTotal)
        {
            $this->logger->critical('Не найдено продукции на складе для резервирования',
                [
                    __FILE__.':'.__LINE__,
                    'profile' => $message->getProfile()->getValue(),
                    'product' => $message->getProduct()->getValue(),
                    'offer' => $message->getOffer()?->getValue(),
                    'variation' => $message->getVariation()?->getValue(),
                    'modification' => $message->getModification()?->getValue()
                ]);

            throw new DomainException('Невозможно добавить резерв на продукцию');

        }

        $ProductStockTotal->addReserve(1);
        $this->entityManager->flush();

        $this->logger->info(sprintf('%s : Добавили резерв на единицу продукции', $ProductStockTotal->getStorage()),
            [
                __FILE__.':'.__LINE__,
                'profile' => $message->getProfile()->getValue(),
                'product' => $message->getProduct()->getValue(),
                'offer' => $message->getOffer()?->getValue(),
                'variation' => $message->getVariation()?->getValue(),
                'modification' => $message->getModification()?->getValue()
            ]);
    }
}
