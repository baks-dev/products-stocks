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

namespace BaksDev\Products\Stocks\Messenger\Stocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalStorage\ProductStocksTotalStorageInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final readonly class AddQuantityProductStocksTotalByIncomingStock
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private ProductStocksByIdInterface $productStocks,
        private EntityManagerInterface $entityManager,
        private UserByUserProfileInterface $userByUserProfile,
        private ProductStocksTotalStorageInterface $productStocksTotalStorage,
        private AddProductStockInterface $addProductStock,
        private DeduplicatorInterface $deduplicator,
    ) {}

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
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());


        if(empty($products))
        {
            $this->logger->warning('Заявка не имеет продукции в коллекции', [self::class.':'.__LINE__]);
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusIncoming::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }


        /** Идентификатор профиля склада при поступлении */
        $UserProfileUid = $ProductStockEvent->getStocksProfile();

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
                $User = $this->userByUserProfile
                    ->forProfile($UserProfileUid)
                    ->find();

                if(!$User)
                {
                    $this->logger->error(
                        'Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                        [
                            self::class.':'.__LINE__,
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

                $this->logger->info(
                    'Место складирования не найдено! Создали новое место для указанной продукции',
                    [
                        self::class.':'.__LINE__,
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
                [self::class.':'.__LINE__]
            );

            $this->handle($ProductStockTotal, $product->getTotal());


        }

        $Deduplicator->save();

    }

    public function handle(ProductStockTotal $ProductStockTotal, int $total): void
    {

        /** Добавляем приход на указанный профиль (склад) */
        $rows = $this->addProductStock
            ->total($total)
            ->reserve(null)
            ->updateById($ProductStockTotal);

        if(empty($rows))
        {
            $this->logger->critical(
                'Ошибка при обновлении складских остатков',
                [
                    self::class.':'.__LINE__,
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
                ]
            );

            return;
        }

        $this->logger->info(
            'Добавили приход продукции на склад',
            [
                self::class.':'.__LINE__,
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId()
            ]
        );
    }
}
