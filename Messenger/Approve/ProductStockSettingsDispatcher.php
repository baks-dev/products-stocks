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

namespace BaksDev\Products\Stocks\Messenger\Approve;


use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\AllProductStocksSizeStocks\AllProductStocksSizeInterface;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\CurrentProductStockSettings\CurrentProductStockSettingsInterface;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\CurrentProductStockSettings\CurrentProductStockSettingsResult;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalDTO;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class ProductStockSettingsDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private CurrentProductStockSettingsInterface $CurrentProductStockSettingsRepository,
        private AllProductStocksSizeInterface $AllProductStocksSizeRepository,
        private ApproveProductStockTotalHandler $ApproveProductStockTotalHandler
    ) {}

    public function __invoke(ProductStockSettingsMessage $message): void
    {
        /** Получаем настройку */

        $CurrentProductStockSettingsResult = $this->CurrentProductStockSettingsRepository
            ->forSettingsMain($message->getId())
            ->find();

        if(false === ($CurrentProductStockSettingsResult instanceof CurrentProductStockSettingsResult))
        {
            return;
        }

        /** Получаем всю продукцию профиля склада */

        $result = $this->AllProductStocksSizeRepository
            ->forProfile($CurrentProductStockSettingsResult->getProfile())
            ->findAll();

        if(false === $result || false === $result->valid())
        {
            return;
        }

        foreach($result as $AllProductStocksSizeResult)
        {
            foreach($AllProductStocksSizeResult->getIdentifiers() as $identifier)
            {
                $ApproveProductStockTotalDTO = new ApproveProductStockTotalDTO()
                    ->setStockTotalIdentifier(new ProductStockTotalUid($identifier));

                $ApproveProductStockTotalDTO
                    ->getApprove()
                    ->setValue($AllProductStocksSizeResult->getQuantity() > $CurrentProductStockSettingsResult->getThreshold());

                $ProductStockTotal = $this->ApproveProductStockTotalHandler->handle($ApproveProductStockTotalDTO);

                if(false === $ProductStockTotal instanceof ProductStockTotal)
                {
                    $this->logger->critical(
                        sprintf('products-stocks: Ошибка при обновлении подтверждения наличия остатков'),
                        [self::class.':'.__LINE__, 'ProductStockTotalUid' => $identifier],
                    );
                }
            }
        }
    }
}
