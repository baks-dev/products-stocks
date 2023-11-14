<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class AddQuantityProductStocksTotalByIncomingStock
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private UserByUserProfileInterface $userByUserProfile;


    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $ProductStockStatusCollection,
        LoggerInterface $messageDispatchLogger,
        UserByUserProfileInterface $userByUserProfile,
    )
    {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->userByUserProfile = $userByUserProfile;

        // Инициируем статусы складских остатков
        $ProductStockStatusCollection->cases();
        $this->logger = $messageDispatchLogger;

    }

    /**
     * Пополнение складских остатков при поступлении на склад
     */
    public function __invoke(ProductStockMessage $message): void
    {
        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        // Если Статус не является "Приход на склад"
        if(!$ProductStockEvent || !$ProductStockEvent->getStatus()->equals(new ProductStockStatusIncoming()))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());

        if($products)
        {
            $this->entityManager->clear();

            /** @var ProductStockProduct $product */
            foreach($products as $product)
            {
                /** Получаем владельца профиля пользователя */

                $ProductStockTotal = $this->entityManager
                    ->getRepository(ProductStockTotal::class)
                    ->findOneBy(
                        [
                            'profile' => $ProductStockEvent->getProfile(),
                            'product' => $product->getProduct(),
                            'offer' => $product->getOffer(),
                            'variation' => $product->getVariation(),
                            'modification' => $product->getModification(),
                        ]
                    );

                if(!$ProductStockTotal)
                {
                    $User = $this->userByUserProfile->findUserByProfile($ProductStockEvent->getProfile());

                    if(!$User)
                    {
                        $this->logger->error('Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                            [
                                __FILE__.':'.__LINE__,
                                'profile' => $ProductStockEvent->getProfile(),
                            ]
                        );

                        throw new InvalidArgumentException('Ошибка при обновлении складских остатков.');
                    }


                    $ProductStockTotal = new ProductStockTotal(
                        $User->getId(),
                        $ProductStockEvent->getProfile(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification()
                    );

                    $this->entityManager->persist($ProductStockTotal);
                }

                $ProductStockTotal->addTotal($product->getTotal());


                $this->logger->info('Добавили приход продукции на склад',
                    [
                        __FILE__.':'.__LINE__,
                        'profile' => $ProductStockEvent->getProfile(),
                        'product' => $product->getProduct(),
                        'offer' => $product->getOffer(),
                        'variation' => $product->getVariation(),
                        'modification' => $product->getModification(),
                        'total' => $product->getTotal(),
                    ]
                );

            }

            $this->entityManager->flush();
        }
    }
}
