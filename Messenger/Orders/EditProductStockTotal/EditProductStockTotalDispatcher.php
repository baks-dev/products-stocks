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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Repository\Items\AllOrderProductItemConst\AllOrderProductItemConstInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksReserve\SubProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\EditProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Products\ProductStockProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При изменении продуктов в складской заявке запускает процесс изменения складских остатков и резервирования Честных знаков
 *
 * @note сравнивает прошлое и текущее состояние складской заявки
 * @note складская заявка изменяется при изменении заказа
 * @note отслеживаемые изменения складской заявки:
 * - изменение количества продукта в большую или меньшую сторону
 * - добавление нового продукта
 * - удаление продукта
 */
#[AsMessageHandler(priority: 0)]
final readonly class EditProductStockTotalDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $Logger,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CountProductStocksStorageInterface $CountProductStocksStorageRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierRepository,
        private AllOrderProductItemConstInterface $allOrderProductItemConstRepository,
        private ProductSignByOrderInterface $productSignByOrderRepository,
        private MessageDispatchInterface $MessageDispatch,
        private DeduplicatorInterface $Deduplicator,
    ) {}

    public function __invoke(EditProductStockTotalMessage $message): void
    {
        $Deduplicator = $this->Deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                self::class
            ]);

        if($Deduplicator->isExecuted())
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

        $lastProductStocks = new EditProductStockDTO($lastProductStockEvent->getId());
        $lastProductStockEvent->getDto($lastProductStocks);

        /**
         * Получаем активное событие
         */
        $currentProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($currentProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        $currentProductStocks = new EditProductStockDTO($currentProductStockEvent->getId());
        $currentProductStockEvent->getDto($currentProductStocks);

        /** Номер текущей СЗ */
        $currentProductStockNumber = $currentProductStocks->getInvariable()->getNumber();

        /**
         * @var ProductStockProductDTO $currentProductStockProductDTO
         */
        foreach($currentProductStocks->getProduct() as $currentProductStockProductDTO)
        {
            /**
             * Ищем соответствие продукта из текущей и предыдущей СЗ
             * @var ProductStockProductDTO|null $lastProductStockProductDTO
             */
            $lastProductStockProductDTO = $lastProductStocks->getProduct()->findFirst(function(
                int $k,
                ProductStockProductDTO $productStockDTO
            ) use (
                $currentProductStockProductDTO
            ) {

                return $productStockDTO->getProduct()->equals($currentProductStockProductDTO->getProduct())
                    && ((is_null($productStockDTO->getOffer()) === true && is_null($currentProductStockProductDTO->getOffer()) === true) || $productStockDTO->getOffer()?->equals($currentProductStockProductDTO->getOffer()))
                    && ((is_null($productStockDTO->getVariation()) === true && is_null($currentProductStockProductDTO->getVariation()) === true) || $productStockDTO->getVariation()?->equals($currentProductStockProductDTO->getVariation()))
                    && ((is_null($productStockDTO->getModification()) === true && is_null($currentProductStockProductDTO->getModification()) === true) || $productStockDTO->getModification()?->equals($currentProductStockProductDTO->getModification()));
            });

            /** Уменьшаем коллекцию продуктов из предыдущего события - для списания резерва на складе */
            $lastProductStocks->getProduct()->removeElement($lastProductStockProductDTO);

            /**
             * Поверяем количество мест складирования продукции на складе
             */
            $storage = $this->CountProductStocksStorageRepository
                ->forProfile($currentProductStocks->getInvariable()->getProfile())
                ->forProduct($currentProductStockProductDTO->getProduct())
                ->forOffer($currentProductStockProductDTO->getOffer())
                ->forVariation($currentProductStockProductDTO->getVariation())
                ->forModification($currentProductStockProductDTO->getModification())
                ->count();

            if(false === $storage)
            {
                $this->Logger->critical(
                    message: sprintf(
                        '%s: Не найдено место на складе для изменения резерва при упаковке',
                        $currentProductStockNumber
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        'profile' => (string) $currentProductStocks->getInvariable()->getProfile(),
                        var_export($message, true),
                    ],
                );

                continue;
            }

            /**
             * Если в предыдущем состоянии СЗ продукт СОВПАДАЕТ с продуктом из текущего состояния СЗ
             * ОБНОВИЛИ количество продукта в СЗ - сверяем количество из состояний
             */
            if(true === $lastProductStockProductDTO instanceof ProductStockProductDTO)
            {
                $currentStockTotal = $currentProductStockProductDTO->getTotal();
                $lastStockTotal = $lastProductStockProductDTO->getTotal();

                /**
                 * Если в предыдущем состоянии СЗ количество продукта РАВНО количеству продукта из текущего состояния СЗ
                 * НЕ ИЗМЕНИЛИ количество продуктов в СЗ
                 */
                if($currentStockTotal === $lastStockTotal)
                {
                    continue;
                }

                /**
                 * Количество продукции в СЗ было УВЕЛИЧЕНО
                 */
                if($currentStockTotal > $lastStockTotal)
                {
                    $diffAdd = $currentStockTotal - $lastStockTotal;

                    /**
                     * Резервируем честные знаки
                     */

                    $addProductSignReservation = $this->addProductSignReservation($currentProductStocks);

                    /**
                     * Если при резервировании ЧЗ произошла ошибка - прерываем резервирование остатков на складе
                     */
                    if(false === $addProductSignReservation)
                    {
                        break;
                    }

                    $this->Logger->info(
                        message: sprintf(
                            '%s: Увеличиваем резерв на складе на +%s',
                            $currentProductStockNumber,
                            $diffAdd,
                        ),
                        context: [
                            self::class.':'.__LINE__,
                            '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                            '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                            var_export($message, true),
                        ]
                    );

                    $AddProductStocksReserve = new AddProductStocksReserveMessage(
                        order: $currentProductStockEvent->getMain(),
                        profile: $currentProductStocks->getInvariable()->getProfile(),
                        product: $currentProductStockProductDTO->getProduct(),
                        offer: $currentProductStockProductDTO->getOffer(),
                        variation: $currentProductStockProductDTO->getVariation(),
                        modification: $currentProductStockProductDTO->getModification()
                    );

                    /**
                     * Если на складе количество мест одно - обновляем сразу весь резерв
                     */
                    if($storage === 1)
                    {
                        $this->Logger->info(
                            message: sprintf(
                                '%s: обновляем сразу весь резерв',
                                $currentProductStockNumber,
                            ),
                            context: [self::class.':'.__LINE__]
                        );

                        $AddProductStocksReserve
                            ->setIterate(1)
                            ->setTotal($diffAdd);

                        $this->MessageDispatch->dispatch(
                            $AddProductStocksReserve,
                            transport: 'products-stocks',
                        );
                    }

                    /**
                     * Если на складе количество мест несколько
                     * создаем резерв на единицу продукции для резерва по местам от меньшего к большему
                     */
                    if($storage > 1)
                    {
                        $this->Logger->info(
                            message: sprintf(
                                '%s: создаем резерв на единицу продукции',
                                $currentProductStockNumber,
                            ),
                            context: [self::class.':'.__LINE__]
                        );

                        for($i = 1; $i <= $diffAdd; $i++)
                        {
                            $AddProductStocksReserve
                                ->setIterate($i)
                                ->setTotal(1);

                            $this->MessageDispatch->dispatch(
                                $AddProductStocksReserve,
                                transport: 'products-stocks',
                            );
                        }
                    }
                }

                /**
                 * Количество продукции в СЗ было УМЕНЬШЕНО
                 */
                if($currentStockTotal < $lastStockTotal)
                {
                    $diffSub = $lastStockTotal - $currentStockTotal;

                    /** Снимаем резерв Честных знаков */
                    $subProductSignReservation = $this->subProductSignReservation($lastProductStocks);

                    /**
                     * Если при снятии резервов ЧЗ произошла ошибка - прерываем снятие резервов на складе
                     */
                    if(false === $subProductSignReservation)
                    {
                        break;
                    }

                    $this->Logger->info(
                        message: sprintf(
                            '%s: Уменьшаем резерв на складе на -%s',
                            $currentProductStockNumber,
                            $diffSub,
                        ),
                        context: [
                            self::class.':'.__LINE__,
                            '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                            '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                            var_export($message, true),
                        ]
                    );

                    $SubProductStocksTotalCancelMessage = new SubProductStocksReserveMessage(
                        stock: $currentProductStockEvent->getMain(),
                        profile: $currentProductStocks->getInvariable()->getProfile(),
                        product: $currentProductStockProductDTO->getProduct(),
                        offer: $currentProductStockProductDTO->getOffer(),
                        variation: $currentProductStockProductDTO->getVariation(),
                        modification: $currentProductStockProductDTO->getModification(),
                    );

                    /**
                     * Если на складе количество мест одно - снимаем сразу весь резерв
                     */
                    if($storage === 1)
                    {
                        $this->Logger->info(
                            message: sprintf(
                                '%s: снимаем сразу весь резерв',
                                $currentProductStockNumber,
                            ),
                            context: [self::class.':'.__LINE__]
                        );

                        $SubProductStocksTotalCancelMessage
                            ->setIterate(1)
                            ->setTotal($diffSub);

                        $this->MessageDispatch->dispatch(
                            $SubProductStocksTotalCancelMessage,
                            transport: 'products-stocks-low', // списание в низкий приоритет
                        );
                    }

                    /**
                     * Если на складе количество мест несколько
                     * снимаем резерв на единицу продукции для резерва по местам от меньшего к большему
                     */
                    if($storage > 1)
                    {
                        $this->Logger->info(
                            message: sprintf(
                                '%s: снимаем резерв на единицу продукции',
                                $currentProductStockNumber,
                            ),
                            context: [self::class.':'.__LINE__]
                        );

                        for($i = 1; $i <= $diffSub; $i++)
                        {
                            $SubProductStocksTotalCancelMessage
                                ->setIterate($i)
                                ->setTotal(1);

                            $this->MessageDispatch->dispatch(
                                $SubProductStocksTotalCancelMessage,
                                transport: 'products-stocks-low', // списание в низкий приоритет
                            );
                        }
                    }
                }
            }

            /**
             * Если в предыдущем состоянии СЗ продукт НЕ СОВПАДАЕТ с продуктом из текущего состояния СЗ
             * ДОБАВИЛИ продукт в текущую СЗ - добавляем резерв на количество нового продукта из СЗ
             */
            if(false === $lastProductStockProductDTO instanceof ProductStockProductDTO)
            {
                $totalUp = $currentProductStockProductDTO->getTotal();

                /**
                 * Резервируем честные знаки
                 */

                $addProductSignReservation = $this->addProductSignReservation($currentProductStocks);

                /**
                 * Если при резервировании ЧЗ произошла ошибка - прерываем резервирование остатков на складе
                 */
                if(false === $addProductSignReservation)
                {
                    break;
                }

                $this->Logger->info(
                    message: sprintf('%s: Добавили продукт. Увеличиваем резерв на складе +%s',
                        $currentProductStockNumber,
                        $totalUp,
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                        '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                        var_export($message, true),
                    ]
                );

                $AddProductStocksReserve = new AddProductStocksReserveMessage(
                    order: $currentProductStockEvent->getMain(),
                    profile: $currentProductStocks->getInvariable()->getProfile(),
                    product: $currentProductStockProductDTO->getProduct(),
                    offer: $currentProductStockProductDTO->getOffer(),
                    variation: $currentProductStockProductDTO->getVariation(),
                    modification: $currentProductStockProductDTO->getModification()
                );

                /**
                 * Если на складе количество мест одно - обновляем сразу весь резерв
                 */

                if($storage === 1)
                {
                    $AddProductStocksReserve
                        ->setIterate(1)
                        ->setTotal($totalUp);

                    $this->MessageDispatch->dispatch(
                        $AddProductStocksReserve,
                        transport: 'products-stocks',
                    );
                }

                /**
                 * Если на складе количество мест несколько
                 * создаем резерв на единицу продукции для резерва по местам от меньшего к большему
                 */
                if($storage > 1)
                {
                    for($i = 1; $i <= $totalUp; $i++)
                    {
                        $AddProductStocksReserve
                            ->setIterate($i)
                            ->setTotal(1);

                        $this->MessageDispatch->dispatch(
                            $AddProductStocksReserve,
                            transport: 'products-stocks',
                        );
                    }
                }
            }
        }

        /**
         * Удаленные продукты в СЗ - списываем резерв
         * @var ProductStockProductDTO $lastProductStockProductDTO
         */
        foreach($lastProductStocks->getProduct() as $lastProductStockProductDTO)
        {
            /** Снимаем резерв Честных знаков */
            $subProductSignReservation = $this->subProductSignReservation($lastProductStocks);

            /**
             * Если при снятии резервов ЧЗ произошла ошибка - прерываем снятие резервов на складе
             */
            if(false === $subProductSignReservation)
            {
                break;
            }

            /**
             * Поверяем количество мест складирования продукции на складе
             */
            $storage = $this->CountProductStocksStorageRepository
                ->forProfile($lastProductStocks->getInvariable()->getProfile())
                ->forProduct($lastProductStockProductDTO->getProduct())
                ->forOffer($lastProductStockProductDTO->getOffer())
                ->forVariation($lastProductStockProductDTO->getVariation())
                ->forModification($lastProductStockProductDTO->getModification())
                ->count();

            if(false === $storage)
            {
                $this->Logger->critical(
                    message: sprintf(
                        '%s: Не найдено место на складе для списания резерва',
                        $currentProductStockNumber
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        'profile' => (string) $currentProductStocks->getInvariable()->getProfile(),
                    ],
                );

                continue;
            }

            $totalDown = $lastProductStockProductDTO->getTotal();

            $this->Logger->info(
                message: sprintf(
                    '%s: Удалили продукт. Уменьшаем резерв на складе на -%s',
                    $currentProductStockNumber,
                    $totalDown,
                ),
                context: [
                    self::class.':'.__LINE__,
                    '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                    var_export($message, true),
                ]
            );

            $SubProductStocksTotalCancelMessage = new SubProductStocksReserveMessage(
                stock: $lastProductStockEvent->getMain(),
                profile: $lastProductStocks->getInvariable()->getProfile(),
                product: $lastProductStockProductDTO->getProduct(),
                offer: $lastProductStockProductDTO->getOffer(),
                variation: $lastProductStockProductDTO->getVariation(),
                modification: $lastProductStockProductDTO->getModification(),
            );

            /**
             * Если на складе количество мест одно - снимаем сразу весь резерв
             */
            if($storage === 1)
            {
                $this->Logger->info(
                    message: sprintf(
                        '%s: снимаем сразу весь резерв',
                        $currentProductStockNumber,
                    ),
                    context: [self::class.':'.__LINE__]
                );

                $SubProductStocksTotalCancelMessage
                    ->setIterate(1)
                    ->setTotal($totalDown);

                $this->MessageDispatch->dispatch(
                    $SubProductStocksTotalCancelMessage,
                    transport: 'products-stocks-low', // списание в низкий приоритет
                );
            }

            /**
             * Если на складе количество мест несколько
             * снимаем резерв на единицу продукции для резерва по местам от меньшего к большему
             */
            if($storage > 1)
            {
                $this->Logger->info(
                    message: sprintf(
                        'складская заявка %s: снимаем резерв на единицу продукции',
                        $currentProductStockNumber,
                    ),
                    context: [self::class.':'.__LINE__]
                );

                for($i = 1; $i <= $totalDown; $i++)
                {
                    $SubProductStocksTotalCancelMessage
                        ->setIterate($i)
                        ->setTotal(1);

                    $this->MessageDispatch->dispatch(
                        $SubProductStocksTotalCancelMessage,
                        transport: 'products-stocks-low', // списание в низкий приоритет
                    );
                }
            }
        }

        $Deduplicator->save();
    }

    /**
     * Отправляет сообщение на резерв Честного знака
     * - получаем все item по заказу без Честных знаков
     * - получаем константы по идентификаторам OrderProduct
     * - на каждую единицу продукции резервируем Честный знак
     */
    private function addProductSignReservation(EditProductStockDTO $currentProductStocks): bool
    {
        /** Все единицы продукта из заказа без Честного знака */
        $productItemsConsts = $this->allOrderProductItemConstRepository
            ->withoutSign()
            ->findAll($currentProductStocks->getOrd()->getOrd());

        if(false === $productItemsConsts)
        {
            $this->Logger->critical(
                message: 'Не найдены единицы продукции для резервирования Честных знаков',
                context: [
                    self::class.':'.__LINE__,
                    var_export($currentProductStocks, true),
                ],
            );

            return false;
        }

        $this->Logger->info(
            message: 'резервируем Честные знаки по КОЛИЧЕСТВУ продукции',
            context: [self::class.':'.__LINE__],
        );

        $ProductSignPart = new ProductSignUid();

        foreach($productItemsConsts as $key => $const)
        {
            $orderProductIds = $const->getParams();

            if(null === $orderProductIds)
            {
                $this->Logger->critical(
                    message: 'Невозможно получить идентификаторы OrderProduct',
                    context: [
                        self::class.':'.__LINE__,
                        var_export($productItemsConsts, true),
                    ]
                );

                return false;
            }

            /** Получаем константы продукта */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifierRepository
                ->forEvent($orderProductIds['product'])
                ->forOffer($orderProductIds['offer'])
                ->forVariation($orderProductIds['variation'])
                ->forModification($orderProductIds['modification'])
                ->find();

            if(false === $CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult)
            {
                $this->Logger->critical(
                    message: 'Невозможно получить CurrentProductIdentifierResult',
                    context: [
                        self::class.':'.__LINE__,
                        var_export($const, true),
                    ]
                );

                return false;
            }

            /** Разбиваем партии по 100 шт */
            if((($key + 1) % 100) === 0)
            {
                /** Переопределяем группу */
                $ProductSignPart = new ProductSignUid();
            }

            $this->MessageDispatch
                ->dispatch(
                    message: new ProductSignProcessMessage(
                        order: $currentProductStocks->getOrd()->getOrd(),
                        part: $ProductSignPart,
                        user: $currentProductStocks->getInvariable()->getUsr(),
                        profile: $currentProductStocks->getInvariable()->getProfile(),
                        product: $CurrentProductIdentifierResult->getProduct(),
                        offer: $CurrentProductIdentifierResult->getOfferConst(),
                        variation: $CurrentProductIdentifierResult->getVariationConst(),
                        modification: $CurrentProductIdentifierResult->getModificationConst(),

                        itemConst: $const
                    ),
                    transport: 'products-sign',
                );
        }

        return true;
    }

    /**
     * Отправляет сообщение на снятие резерва с Честного знака
     */
    private function subProductSignReservation(EditProductStockDTO $lastProductStocks): bool
    {
        /** Честные знаки, у которых нужно снять резерв
         * - проверяем, что у ЧЗ есть связь с item
         * - так как этот item будет удален из заказа и складской заявки - снимаем резерв у ЧЗ - переводим в статус New
         */
        $productsSign = $this->productSignByOrderRepository
            ->forOrder($lastProductStocks->getOrd()->getOrd())
            ->withoutItem()
            ->findAll();

        if(false === $productsSign)
        {
            $this->Logger->critical(
                message: 'Не найдены Честные знаки для снятия резерва',
                context: [
                    self::class.':'.__LINE__,
                    var_export($lastProductStocks, true),
                ],
            );

            return false;
        }

        $this->Logger->info(
            message: 'снимаем резерв Честных знаков',
            context: [self::class.':'.__LINE__],
        );

        foreach($productsSign as $sign)
        {
            $this->MessageDispatch->dispatch(
                message: new ProductSignCancelMessage(
                    $lastProductStocks->getInvariable()->getProfile(),
                    $sign->getSignEvent(),
                ),
                transport: 'products-sign',
            );
        }

        return true;
    }
}
