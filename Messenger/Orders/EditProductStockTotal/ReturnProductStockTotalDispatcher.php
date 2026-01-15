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

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\UseCase\Admin\Return\ReturnOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Return\ReturnOrderHandler;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\BaksDevProductsSignBundle;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignReturn\ProductSignReturnMessage;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalStorage\ProductStocksTotalStorageInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\EditProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Products\ProductStockProductDTO;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При изменении складской заявки со статусом Completed «Выдан по месту назначения» - запускает процесс возврата:
 *  - складских остатков
 *  - Честных знаков
 *
 * @note сравнивает прошлое и текущее состояние складской заявки
 * @note складская заявка изменяется при изменении заказа
 *
 * @note отслеживаемые изменения складской заявки:
 * - удаление единицы товара
 * - удаление продукта из заказа
 */
#[AsMessageHandler(priority: 0)]
final readonly class ReturnProductStockTotalDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $Logger,

        private ProductStocksEventInterface $ProductStocksEventRepository,

        private AddProductStockInterface $AddProductStockRepository,
        private UserByUserProfileInterface $UserByUserProfileRepository,
        private ProductStocksTotalStorageInterface $ProductStocksTotalStorageRepository,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierByEventRepository,
        private ReturnOrderHandler $ReturnOrderHandler,

        private MessageDispatchInterface $MessageDispatch,
        private DeduplicatorInterface $Deduplicator,
        private EntityManagerInterface $entityManager,

        private ?ProductSignByOrderInterface $productSignByOrderRepository = null,
    ) {}

    public function __invoke(EditProductStockTotalMessage $message): void
    {
        if($this->productSignByOrderRepository instanceof ProductSignByOrderInterface)
        {
            return;
        }

        $Deduplicator = $this->Deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getEvent(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /**
         * Получаем активное событие
         */
        $CurrentProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($CurrentProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /** Только статус Completed «Выдан по месту назначения» */
        if(false === ($CurrentProductStockEvent->isStatusEquals(ProductStockStatusCompleted::class)))
        {
            return;
        }

        /**
         * Получаем предыдущее событие
         */
        $lastProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getLast())
            ->find();

        if(false === ($lastProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        $lastEditProductStockDTO = new EditProductStockDTO($lastProductStockEvent->getId());
        $lastProductStockEvent->getDto($lastEditProductStockDTO);
        $lastProductStockInvariableDTO = $lastEditProductStockDTO->getInvariable();


        $CurrentEditProductStockDTO = new EditProductStockDTO($CurrentProductStockEvent->getId());
        $CurrentProductStockEvent->getDto($CurrentEditProductStockDTO);

        $CurrentProductStockInvariableDTO = $CurrentEditProductStockDTO->getInvariable();
        $UserProfileUid = $CurrentProductStockInvariableDTO->getProfile();


        $OrderUid = $CurrentEditProductStockDTO->getOrd()->getOrd();


        /**
         * Итерируемся по текущей коллекции в поисках изменений
         *
         * @var ProductStockProductDTO $currentProductStockProductDTO
         */

        foreach($CurrentEditProductStockDTO->getProduct() as $currentProductStockProductDTO)
        {
            /**
             * Ищем соответствие продукта из текущей и предыдущей СЗ
             *
             * @var ProductStockProductDTO|null $lastProductStockProductDTO
             */
            $lastProductStockProductDTO = $lastEditProductStockDTO
                ->getProduct()
                ->findFirst(
                    function(int $k, ProductStockProductDTO $productStockDTO) use ($currentProductStockProductDTO) {
                        return $productStockDTO->getProduct()->equals($currentProductStockProductDTO->getProduct())
                            && ((is_null($productStockDTO->getOffer()) === true && is_null($currentProductStockProductDTO->getOffer()) === true) || $productStockDTO->getOffer()?->equals($currentProductStockProductDTO->getOffer()))
                            && ((is_null($productStockDTO->getVariation()) === true && is_null($currentProductStockProductDTO->getVariation()) === true) || $productStockDTO->getVariation()?->equals($currentProductStockProductDTO->getVariation()))
                            && ((is_null($productStockDTO->getModification()) === true && is_null($currentProductStockProductDTO->getModification()) === true) || $productStockDTO->getModification()?->equals($currentProductStockProductDTO->getModification()));
                    },
                );

            if(false === $lastProductStockProductDTO instanceof ProductStockProductDTO)
            {
                continue;
            }

            $currentStockTotal = $currentProductStockProductDTO->getTotal();
            $lastStockTotal = $lastProductStockProductDTO->getTotal();

            if($currentStockTotal < $lastStockTotal)
            {
                if(true === class_exists(BaksDevProductsSignBundle::class))
                {
                    /** Делаем возврат Честных знаков */
                    $this->subProductSignReservation($lastEditProductStockDTO);
                }

                /** Получаем место для хранения указанной продукции данного профиля */
                $ProductStockTotal = $this->ProductStocksTotalStorageRepository
                    ->profile($CurrentProductStockInvariableDTO->getProfile())
                    ->product($currentProductStockProductDTO->getProduct())
                    ->offer($currentProductStockProductDTO->getOffer())
                    ->variation($currentProductStockProductDTO->getVariation())
                    ->modification($currentProductStockProductDTO->getModification())
                    ->storage(null)
                    ->find();

                /** Если место хранения продукции на складе не найдено - создаем для возврата */
                if(false === ($ProductStockTotal instanceof ProductStockTotal))
                {
                    /* получаем пользователя профиля, для присвоения новому месту складирования */
                    $User = $this->UserByUserProfileRepository
                        ->forProfile($UserProfileUid)
                        ->find();

                    if(false === ($User instanceof User))
                    {
                        $this->Logger->error(
                            'Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                            [
                                self::class.':'.__LINE__,
                                'profile' => (string) $UserProfileUid,
                            ],
                        );

                        throw new InvalidArgumentException('Ошибка при обновлении складских остатков.');
                    }

                    /* Создаем новое место складирования на указанный профиль пользователя */
                    $ProductStockTotal = new ProductStockTotal(
                        $User->getId(),
                        $UserProfileUid,
                        $currentProductStockProductDTO->getProduct(),
                        $currentProductStockProductDTO->getOffer(),
                        $currentProductStockProductDTO->getVariation(),
                        $currentProductStockProductDTO->getModification(),
                        null,
                    );

                    $this->entityManager->persist($ProductStockTotal);
                    $this->entityManager->flush();

                    $this->Logger->info(
                        'Место складирования не найдено! Создали новое место для указанной продукции',
                        [
                            self::class.':'.__LINE__,
                            'profile' => (string) $UserProfileUid,
                        ],
                    );
                }

                $this->Logger->info(
                    sprintf('Добавляем приход продукции по возврату %s', $CurrentProductStockInvariableDTO->getNumber()),
                    [self::class.':'.__LINE__],
                );

                /** Создаем приход на продукт */
                $diffSub = $lastStockTotal - $currentStockTotal;
                $this->handle($ProductStockTotal, $diffSub);

                /** Создаем заказ со статусом Return «Возврат» на соответствующее количество */

                if($OrderUid instanceof OrderUid)
                {
                    $this->orderHandler(
                        $OrderUid,
                        $currentProductStockProductDTO,
                        $diffSub,
                    );
                }
            }

            /** Уменьшаем коллекцию продуктов из предыдущего события - для списания резерва на складе в случае удаленного продукта */
            $lastEditProductStockDTO->getProduct()->removeElement($lastProductStockProductDTO);

        }


        /**
         * Итерируемся по предыдущей коллекции в поиске удаленных продуктов (коллекция меняется в стр. 161)
         *
         * @var ProductStockProductDTO $lastProductStockProductDTO
         */

        foreach($lastEditProductStockDTO->getProduct() as $lastProductStockProductDTO)
        {
            /** Снимаем резерв с Честных знаков */
            $this->subProductSignReservation($lastEditProductStockDTO);

            /** Получаем место для хранения указанной продукции данного профиля */
            $ProductStockTotal = $this->ProductStocksTotalStorageRepository
                ->profile($lastProductStockInvariableDTO->getProfile())
                ->product($lastProductStockProductDTO->getProduct())
                ->offer($lastProductStockProductDTO->getOffer())
                ->variation($lastProductStockProductDTO->getVariation())
                ->modification($lastProductStockProductDTO->getModification())
                ->storage(null)
                ->find();

            /** Если место хранения продукции на складе не найдено - создаем для возврата */
            if(false === ($ProductStockTotal instanceof ProductStockTotal))
            {
                /* получаем пользователя профиля, для присвоения новому месту складирования */
                $User = $this->UserByUserProfileRepository
                    ->forProfile($UserProfileUid)
                    ->find();

                if(false === ($User instanceof User))
                {
                    $this->Logger->error(
                        'Ошибка при обновлении складских остатков. Не удалось получить пользователя по профилю.',
                        [
                            self::class.':'.__LINE__,
                            'profile' => (string) $UserProfileUid,
                        ],
                    );

                    throw new InvalidArgumentException('Ошибка при обновлении складских остатков.');
                }

                /* Создаем новое место складирования на указанный профиль пользователя */
                $ProductStockTotal = new ProductStockTotal(
                    $User->getId(),
                    $UserProfileUid,
                    $currentProductStockProductDTO->getProduct(),
                    $currentProductStockProductDTO->getOffer(),
                    $currentProductStockProductDTO->getVariation(),
                    $currentProductStockProductDTO->getModification(),
                    null,
                );

                $this->entityManager->persist($ProductStockTotal);
                $this->entityManager->flush();

                $this->Logger->info(
                    'Место складирования не найдено! Создали новое место для указанной продукции',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $UserProfileUid,
                    ],
                );
            }

            $totalDown = $lastProductStockProductDTO->getTotal();

            $this->Logger->info(
                message: sprintf(
                    '%s: Удалили продукт. Добавляем возврат продукции на склад +%s',
                    $lastEditProductStockDTO->getInvariable()->getNumber(),
                    $totalDown,
                ),
                context: [
                    self::class.':'.__LINE__,
                    '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                    var_export($message, true),
                ],
            );

            /** Создаем приход на продукт */
            $lastStockTotal = $lastProductStockProductDTO->getTotal();
            $this->handle($ProductStockTotal, $lastStockTotal);

            /** Создаем заказ со статусом Return «Возврат» на соответствующее количество */

            if($OrderUid instanceof OrderUid)
            {
                $this->orderHandler(
                    $OrderUid,
                    $lastProductStockProductDTO,
                    $totalDown,
                );
            }
        }


        $Deduplicator->save();
    }

    /**
     * Отправляет сообщение на снятие резерва с Честного знака
     */
    private function subProductSignReservation(EditProductStockDTO $lastProductStocks): void
    {

        /** Честные знаки, у которых нужно снять резерв
         * - проверяем, что у ЧЗ есть связь с item
         * - так как этот item будет удален из заказа и складской заявки - снимаем резерв у ЧЗ - переводим в статус New
         */

        $OrderUid = $lastProductStocks->getOrd()->getOrd();

        if(false === ($OrderUid instanceof OrderUid))
        {
            $this->Logger->warning(
                message: 'products-stocks: Не снимаем резервы с честных знаков. Идентификатор заказа не присвоен складской заявке',
                context: [
                    self::class.':'.__LINE__,
                    (string) $lastProductStocks->getOrd()->getOrd(),
                ],
            );

            return;
        }

        $result = $this->productSignByOrderRepository
            ->forOrder($OrderUid)
            ->withoutItem()
            ->findAll();

        if(false === $result || false === $result->valid())
        {
            $this->Logger->warning(
                message: 'products-stocks: Не найдены Честные знаки по заказу для снятия резерва и возврата в реализацию',
                context: [
                    self::class.':'.__LINE__,
                    (string) $lastProductStocks->getOrd()->getOrd(),
                ],
            );

            return;
        }

        $this->Logger->info(
            message: sprintf('%s: снимаем резерв Честных знаков', $OrderUid),
            context: [self::class.':'.__LINE__],
        );

        foreach($result as $ProductSignByOrderResult)
        {
            $this->MessageDispatch->dispatch(
                message: new ProductSignReturnMessage(
                    $lastProductStocks->getInvariable()->getProfile(),
                    $ProductSignByOrderResult->getSignEvent(),
                ),
                transport: 'products-sign',
            );
        }

    }


    public function handle(ProductStockTotal $ProductStockTotal, int $total): void
    {
        /** Добавляем приход на указанный профиль (склад) */
        $rows = $this->AddProductStockRepository
            ->total($total)
            ->reserve(false) // не обновляем резерв
            ->updateById($ProductStockTotal);

        $this->MessageDispatch->addClearCacheOther('products-stocks');

        if(empty($rows))
        {
            $this->Logger->critical(
                'Ошибка при обновлении складских остатков при возврате',
                [
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId(),
                    self::class.':'.__LINE__,
                ],
            );

            return;
        }

        $this->Logger->info(
            'Добавили возврат продукции на склад',
            [
                'ProductStockTotalUid' => (string) $ProductStockTotal->getId(),
                self::class.':'.__LINE__,
            ],
        );

    }


    public function orderHandler(
        OrderUid $OrderUid,
        ProductStockProductDTO $ProductStockProductDTO,
        int $total
    ): void
    {
        $OrderEvent = $this->CurrentOrderEventRepository->forOrder($OrderUid)->find();

        if($OrderEvent instanceof OrderEvent)
        {
            $ReturnOrderDTO = new ReturnOrderDTO();
            $OrderEvent->getDto($ReturnOrderDTO);
            $ReturnOrderDTO->setComment(sprintf('Частичный возврат заказа %s', $OrderEvent->getOrderNumber()));

            /** Итерируемся по продукции и удаляем все, кроме измененного продукта  */
            foreach($ReturnOrderDTO->getProduct() as $OrderProductDTO)
            {
                $CurrentProductIdentifierResult = $this->CurrentProductIdentifierByEventRepository
                    ->forEvent($OrderProductDTO->getProduct())
                    ->forOffer($OrderProductDTO->getOffer())
                    ->forVariation($OrderProductDTO->getVariation())
                    ->forModification($OrderProductDTO->getModification())
                    ->find();

                if(false === ($CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult))
                {
                    $ReturnOrderDTO->removeProduct($OrderProductDTO);
                    continue;
                }

                if(false === $CurrentProductIdentifierResult->getProduct()->equals($ProductStockProductDTO->getProduct()))
                {
                    $ReturnOrderDTO->removeProduct($OrderProductDTO);
                    continue;
                }

                if(
                    (
                        false === ($CurrentProductIdentifierResult->getOfferConst() instanceof ProductOfferConst)
                        && true === ($ProductStockProductDTO->getOffer() instanceof ProductOfferConst)
                    )
                    || false === $CurrentProductIdentifierResult->getOfferConst()->equals($ProductStockProductDTO->getOffer())
                )
                {
                    $ReturnOrderDTO->removeProduct($OrderProductDTO);
                    continue;
                }

                if(
                    (
                        false === ($CurrentProductIdentifierResult->getVariationConst() instanceof ProductVariationConst)
                        && true === ($ProductStockProductDTO->getVariation() instanceof ProductVariationConst)
                    )
                    || false === $CurrentProductIdentifierResult->getVariationConst()->equals($ProductStockProductDTO->getVariation())
                )
                {
                    $ReturnOrderDTO->removeProduct($OrderProductDTO);
                    continue;
                }

                if(
                    (
                        false === ($CurrentProductIdentifierResult->getModificationConst() instanceof ProductModificationConst)
                        && true === ($ProductStockProductDTO->getModification() instanceof ProductModificationConst)
                    )
                    || false === $CurrentProductIdentifierResult->getModificationConst()->equals($ProductStockProductDTO->getModification())
                )
                {
                    $ReturnOrderDTO->removeProduct($OrderProductDTO);
                    continue;
                }

                /** Если найден текущий продукт - указываем количество возврата */

                $OrderProductDTO->getPrice()->setTotal($total);
            }


            if(false === $ReturnOrderDTO->getProduct()->isEmpty())
            {
                $Order = $this->ReturnOrderHandler->handle($ReturnOrderDTO);

                if(false === ($Order instanceof Order))
                {
                    $this->Logger->critical(
                        'products-stocks: Ошибка при создании заказа со статусом возврата на единицу продукции',
                        [self::class.':'.__LINE__, $Order],
                    );
                }
            }

        }
    }
}
