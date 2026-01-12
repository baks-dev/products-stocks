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
use BaksDev\Orders\Order\Repository\Items\AllOrderProductItemConst\AllOrderProductItemConstInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Sign\BaksDevProductsSignBundle;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve\AddProductStocksReserveMessage;
use BaksDev\Products\Stocks\Messenger\Stocks\SubProductStocksReserve\SubProductStocksReserveMessage;
use BaksDev\Products\Stocks\Repository\CountProductStocksStorage\CountProductStocksStorageInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\EditProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Products\ProductStockProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При изменении продуктов в складской заявке - запускает процесс изменения складских остатков и резервирования Честных
 * знаков
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
        $currentProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($currentProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        /**
         * Если заявка выполнена - пропускаем
         *
         * @see ReturnProductStockTotalDispatcher
         */
        if(true === ($currentProductStockEvent->isStatusEquals(ProductStockStatusCompleted::class)))
        {
            $Deduplicator->save();
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

        $currentProductStocks = new EditProductStockDTO($currentProductStockEvent->getId());
        $currentProductStockEvent->getDto($currentProductStocks);


        /**
         * @var ProductStockProductDTO $currentProductStockProductDTO
         */
        foreach($currentProductStocks->getProduct() as $currentProductStockProductDTO)
        {
            /**
             * Ищем соответствие продукта из текущей и предыдущей СЗ
             *
             * @var ProductStockProductDTO|null $lastProductStockProductDTO
             */
            $lastProductStockProductDTO = $lastProductStocks
                ->getProduct()
                ->findFirst(
                    function(int $k, ProductStockProductDTO $productStockDTO) use ($currentProductStockProductDTO) {
                        return $productStockDTO->getProduct()->equals($currentProductStockProductDTO->getProduct())
                            && ((is_null($productStockDTO->getOffer()) === true && is_null($currentProductStockProductDTO->getOffer()) === true) || $productStockDTO->getOffer()?->equals($currentProductStockProductDTO->getOffer()))
                            && ((is_null($productStockDTO->getVariation()) === true && is_null($currentProductStockProductDTO->getVariation()) === true) || $productStockDTO->getVariation()?->equals($currentProductStockProductDTO->getVariation()))
                            && ((is_null($productStockDTO->getModification()) === true && is_null($currentProductStockProductDTO->getModification()) === true) || $productStockDTO->getModification()?->equals($currentProductStockProductDTO->getModification()));
                    },
                );


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
                        '%s: Не найдено место на складе для изменения резерва',
                        $currentProductStocks->getInvariable()->getNumber(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        'profile' => (string) $currentProductStocks->getInvariable()->getProfile(),
                        var_export($message, true),
                    ],
                );


                /** Удаляем продукт из коллекции предыдущего состояния, не изменяем резервы и остатки */
                $lastProductStocks->getProduct()->removeElement($lastProductStockProductDTO);

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
                    /** Удаляем продукт из коллекции предыдущего состояния, не изменяем резервы и остатки */
                    $lastProductStocks->getProduct()->removeElement($lastProductStockProductDTO);
                    continue;
                }


                /**
                 * Количество продукции в СЗ было УВЕЛИЧЕНО
                 */


                if($currentStockTotal > $lastStockTotal)
                {
                    $diffAdd = $currentStockTotal - $lastStockTotal;

                    /** Резервируем честные знаки */
                    $this->addProductSignReservation($currentProductStocks);


                    $this->Logger->info(
                        message: sprintf(
                            '%s: Увеличиваем резерв на складе на +%s',
                            $currentProductStocks->getInvariable()->getNumber(),
                            $diffAdd,
                        ),
                        context: [
                            self::class.':'.__LINE__,
                            '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                            '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                            var_export($message, true),
                        ],
                    );

                    $AddProductStocksReserve = new AddProductStocksReserveMessage(
                        order: $currentProductStockEvent->getMain(),
                        profile: $currentProductStocks->getInvariable()->getProfile(),
                        product: $currentProductStockProductDTO->getProduct(),
                        offer: $currentProductStockProductDTO->getOffer(),
                        variation: $currentProductStockProductDTO->getVariation(),
                        modification: $currentProductStockProductDTO->getModification(),
                    );

                    /**
                     * Если на складе количество мест одно - обновляем сразу весь резерв
                     */
                    if($storage === 1)
                    {
                        $this->Logger->info(
                            message: sprintf(
                                '%s: обновляем сразу весь резерв в единственном складском месте',
                                $currentProductStocks->getInvariable()->getNumber(),
                            ),
                            context: [self::class.':'.__LINE__],
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
                                $currentProductStocks->getInvariable()->getNumber(),
                            ),
                            context: [self::class.':'.__LINE__],
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
                    /** Снимаем резерв с Честных знаков */
                    $this->subProductSignReservation($lastProductStocks);

                    $diffSub = $lastStockTotal - $currentStockTotal;

                    $this->Logger->info(
                        message: sprintf(
                            '%s: Уменьшаем резерв на складе на -%s',
                            $currentProductStocks->getInvariable()->getNumber(),
                            $diffSub,
                        ),
                        context: [
                            self::class.':'.__LINE__,
                            '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                            '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                            var_export($message, true),
                        ],
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
                                $currentProductStocks->getInvariable()->getNumber(),
                            ),
                            context: [self::class.':'.__LINE__],
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
                                $currentProductStocks->getInvariable()->getNumber(),
                            ),
                            context: [self::class.':'.__LINE__],
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

                /** Удаляем продукт из предыдущего события - продукт найден и произвел вычисления с резервами */
                $lastProductStocks->getProduct()->removeElement($lastProductStockProductDTO);
            }


            /**
             * Если в предыдущем состоянии СЗ продукт НЕ СОВПАДАЕТ с продуктом из текущего состояния СЗ
             * следует ДОБАВИЛИ новый продукт в текущую СЗ - добавляем резерв на количество НОВОГО продукта из СЗ
             */


            if(false === $lastProductStockProductDTO instanceof ProductStockProductDTO)
            {
                /** Резервируем честные знаки */
                $this->addProductSignReservation($currentProductStocks);

                $totalUp = $currentProductStockProductDTO->getTotal();

                $this->Logger->info(
                    message: sprintf('%s: Добавили продукт. Увеличиваем резерв на складе +%s',
                        $currentProductStocks->getInvariable()->getNumber(),
                        $totalUp,
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        '$currentProductStockProductDTO' => var_export($currentProductStockProductDTO, true),
                        '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                        var_export($message, true),
                    ],
                );

                $AddProductStocksReserve = new AddProductStocksReserveMessage(
                    order: $currentProductStockEvent->getMain(),
                    profile: $currentProductStocks->getInvariable()->getProfile(),
                    product: $currentProductStockProductDTO->getProduct(),
                    offer: $currentProductStockProductDTO->getOffer(),
                    variation: $currentProductStockProductDTO->getVariation(),
                    modification: $currentProductStockProductDTO->getModification(),
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
         * Удаленные продукты в СЗ - списываем резерв на все количество
         *
         * @var ProductStockProductDTO $lastProductStockProductDTO
         */

        foreach($lastProductStocks->getProduct() as $lastProductStockProductDTO)
        {
            /** Снимаем резерв с Честных знаков */
            $this->subProductSignReservation($lastProductStocks);

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
                        $currentProductStocks->getInvariable()->getNumber(),
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
                    $currentProductStocks->getInvariable()->getNumber(),
                    $totalDown,
                ),
                context: [
                    self::class.':'.__LINE__,
                    '$lastProductStockProductDTO' => var_export($lastProductStockProductDTO, true),
                    var_export($message, true),
                ],
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
                        $currentProductStocks->getInvariable()->getNumber(),
                    ),
                    context: [self::class.':'.__LINE__],
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
                        $currentProductStocks->getInvariable()->getNumber(),
                    ),
                    context: [self::class.':'.__LINE__],
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
    private function addProductSignReservation(EditProductStockDTO $currentProductStocks): void
    {
        if(false === class_exists(BaksDevProductsSignBundle::class))
        {
            return;
        }

        /** Все единицы продукта из заказа без Честного знака */
        $productItemsConstants = $this->allOrderProductItemConstRepository
            ->withoutSign()
            ->findAll($currentProductStocks->getOrd()->getOrd());

        if(false === $productItemsConstants || false === $productItemsConstants->valid())
        {
            $this->Logger->critical(
                message: 'Не найдены единицы продукции для резервирования Честных знаков',
                context: [
                    self::class.':'.__LINE__,
                    var_export($currentProductStocks, true),
                ],
            );

            return;
        }

        $this->Logger->info(
            message: 'резервируем Честные знаки по КОЛИЧЕСТВУ продукции',
            context: [self::class.':'.__LINE__],
        );

        $ProductSignPart = new ProductSignUid();

        foreach($productItemsConstants as $key => $const)
        {
            $DeduplicatorConst = $this->Deduplicator
                ->namespace('products-stocks')
                ->deduplication([
                    (string) $const,
                    self::class,
                ]);

            if($DeduplicatorConst->isExecuted())
            {
                continue;
            }

            $orderProductIds = $const->getParams();

            if(null === $orderProductIds)
            {
                $this->Logger->critical(
                    message: 'Невозможно получить идентификаторы OrderProduct',
                    context: [
                        self::class.':'.__LINE__,
                        var_export($productItemsConstants, true),
                    ],
                );

                return;
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
                    ],
                );

                return;
            }

            /** Разбиваем партии по 100 шт */
            if((($key + 1) % 100) === 0)
            {
                /** Переопределяем группу */
                $ProductSignPart = new ProductSignUid();
            }

            /** Дедубликатор */

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

                        itemConst: $const,
                    ),

                    transport: 'products-stocks',
                );

            $DeduplicatorConst->save();
        }

    }

    /**
     * Отправляет сообщение на снятие резерва с Честного знака
     */
    private function subProductSignReservation(EditProductStockDTO $lastProductStocks): void
    {
        if(false === class_exists(BaksDevProductsSignBundle::class))
        {
            return;
        }

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
                message: new ProductSignCancelMessage(
                    $lastProductStocks->getInvariable()->getProfile(),
                    $ProductSignByOrderResult->getSignEvent(),
                ),
                transport: 'products-sign',
            );
        }

    }
}
