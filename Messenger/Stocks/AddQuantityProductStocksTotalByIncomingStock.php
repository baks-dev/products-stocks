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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalStorage\ProductStocksTotalStorageInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
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
    private ProductStocksTotalStorageInterface $productStocksTotalStorage;
    private AddProductStockInterface $addProductStock;
    private MessageDispatchInterface $messageDispatch;


    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        ProductStockStatusCollection $ProductStockStatusCollection,
        LoggerInterface $productsStocksLogger,
        UserByUserProfileInterface $userByUserProfile,
        ProductStocksTotalStorageInterface $productStocksTotalStorage,
        AddProductStockInterface $addProductStock,
        MessageDispatchInterface $messageDispatch
    )
    {
        // Инициируем статусы складских остатков
        $ProductStockStatusCollection->cases();

        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->userByUserProfile = $userByUserProfile;
        $this->logger = $productsStocksLogger;
        $this->productStocksTotalStorage = $productStocksTotalStorage;
        $this->addProductStock = $addProductStock;
        $this->messageDispatch = $messageDispatch;
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

        $this->entityManager->clear();

        if(!$ProductStockEvent)
        {
            return;
        }

        /**
         * Если Статус заявки не является Incoming «Приход на склад»
         */
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusIncoming::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());


        if(empty($products))
        {
            $this->logger->warning('Заявка на приход не имеет продукции в коллекции', [__FILE__.':'.__LINE__]);
            return;
        }


        /** Идентификатор профиля склада при поступлении */
        $UserProfileUid = $ProductStockEvent->getProfile();

        /** @var ProductStockProduct $product */
        foreach($products as $product)
        {
            /** Получаем место для хранения указанной продукции данного профиля */
            $ProductStockTotal = $this->productStocksTotalStorage
                ->profile($UserProfileUid)
                ->product($product->getProduct())
                ->offer($product->getOffer())
                ->variation($product->getVariation())
                ->modification($product->getModification())
                ->storage($product->getStorage())
                ->find();

            if(!$ProductStockTotal)
            {
                /* получаем пользователя профиля, для присвоения новому месту складирования */
                $User = $this->userByUserProfile->findUserByProfile($UserProfileUid);

                if(!$User)
                {
                    $this->logger->error('Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                        [
                            __FILE__.':'.__LINE__,
                            'profile' => (string) $UserProfileUid,
                        ]
                    );

                    throw new InvalidArgumentException('Ошибка при обновлении складских остатков.');
                }

                /* Создаем новое место складирования на указанный профиль и пользовтаеля  */
                $ProductStockTotal = new ProductStockTotal(
                    $User->getId(),
                    $UserProfileUid,
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification(),
                    $product->getStorage()
                );

                $this->entityManager->persist($ProductStockTotal);
                $this->entityManager->flush();

                $this->logger->info('Место складирования не найдено! Создали новое место для указанной продукции',
                    [
                        __FILE__.':'.__LINE__,
                        'storage' => $product->getStorage(),
                        'profile' => (string) $UserProfileUid,
                        'product' => (string) $product->getProduct(),
                        'offer' => (string) $product->getOffer(),
                        'variation' => (string) $product->getVariation(),
                        'modification' => (string) $product->getModification(),
                    ]
                );
            }

            $this->logger->info(
                sprintf('Добавляем приход продукции по заявке %s', $ProductStockEvent->getNumber()),
                [__FILE__.':'.__LINE__]
            );

            $this->handle($ProductStockTotal, $product->getTotal());

        }

    }

    public function handle(ProductStockTotal $ProductStockTotal, int $total): void
    {

        /** Добавляем приход на указанный профиль (склад) */
        $rows = $this->addProductStock
            ->total($total)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical('Ошибка при обновлении складских остатков',
                [
                    __FILE__.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]);

            return;
        }

        $this->logger->info('Добавили приход продукции на склад',
            [
                __FILE__.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]
        );
    }
}
