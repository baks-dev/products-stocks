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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger\PurchaseProductStock;

use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает складскую заявку в статусе Purchase «Закупка»
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class PurchaseProductStockDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private PurchaseProductStockHandler $purchaseProductStockHandler,
        private UserByUserProfileInterface $userByUserProfileRepository,
    ) {}

    public function __invoke(PurchaseProductStockMessage $message): void
    {
        /** Получаем идентификатор пользователя по профилю */
        $User = $this->userByUserProfileRepository
            ->forProfile($message->getProfile())
            ->find();

        if(false === ($User instanceof User))
        {
            $this->logger->critical(
                message: sprintf('products-sign: Не найден профиль пользователя для создания закупочного листа при обработке Честного знака'),
                context: [
                    var_export($message, true),
                    self::class.':'.__LINE__
                ],
            );

            return;
        }

        if(true === ($User instanceof User))
        {
            /** Генерируем номер */
            $PurchaseNumber = number_format(
                microtime(true) * 100,
                0,
                '.',
                '.'
            );

            $PurchaseProductStockDTO = new PurchaseProductStockDTO();
            $PurchaseProductStocksInvariableDTO = $PurchaseProductStockDTO->getInvariable();

            $PurchaseProductStocksInvariableDTO
                ->setUsr($User->getId())
                ->setProfile($message->getProfile())
                ->setNumber($PurchaseNumber);

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($message->getProduct())
                ->setOffer($message->getOffer())
                ->setVariation($message->getVariation())
                ->setModification($message->getModification())
                ->setTotal($message->getTotal());

            $PurchaseProductStockDTO->addProduct($ProductStockDTO);

            $ProductStock = $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);

            if(false === ($ProductStock instanceof ProductStock))
            {
                $this->logger->critical(
                    message: sprintf(
                        'products-sign: Ошибка %s при создании складской заявки в статусе Purchase «Закупка» при обработке Честного знака',
                        $ProductStock),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ]
                );
            }

            if(true === ($ProductStock instanceof ProductStock))
            {
                $this->logger->info(
                    message: sprintf(
                        '%s: Создана складская заявка в статусе Purchase «Закупка» при обработке Честного знака',
                        $PurchaseProductStocksInvariableDTO->getNumber(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ]
                );
            }
        }
    }
}
