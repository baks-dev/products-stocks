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

namespace BaksDev\Products\Stocks\Messenger\BarcodeScanner;

use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Barcode\Messenger\ScannerMessage;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart\AllWorkingByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\CurrentManufacturePartEvent\CurrentManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Products\Stocks\Forms\PartScanner\PartScannerDTO;
use BaksDev\Products\Stocks\Forms\PartScanner\PartScannerForm;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\AllProductStocksOrdersPartInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\ProductStocksOrdersPartResult;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class PartScannerDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private AllProductStocksOrdersPartInterface $AllProductStocksOrdersPartRepository,
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private Environment $twig,
    ) {}


    /**
     * Получаем состояние партии отправляем соответствующие действия
     */
    public function __invoke(ScannerMessage $message): void
    {
        if(false === empty($message->getContent()))
        {
            return;
        }

        /**
         * Получаем упаковку товара по заказам
         */

        $ProductStockPartUid = new ProductStockPartUid($message->getIdentifier());


        /** Получаем все заказы в сборочном листе */
        $products = $this->AllProductStocksOrdersPartRepository
            ->forProductStockPart($ProductStockPartUid)
            ->onlyPackageStatus()
            ->findAll();

        /** Упаковка с идентификатором в статусе «Package» не найдена */
        if(false === $products || false === $products->valid())
        {
            $this->logger->warning(sprintf('%s: Упаковка с идентификатором в статусе «Package» не найдена', $ProductStockPartUid), [
                self::class.':'.__LINE__,
            ]);

            return;
        }


        /** Создаем форму для подтверждения выполненных действий */

        $form = $this->formFactory
            ->create(
                type: PartScannerForm::class,
                data: new PartScannerDTO($ProductStockPartUid),
                options: [
                    'action' => $this->router->generate(
                        'products-stocks:admin.scan.package',
                        ['id' => $ProductStockPartUid],
                    ),
                ],
            );


        /** @var ProductStocksOrdersPartResult $current */
        $current = $products->current();

        $content = $this->twig->render(
            name: '@products-stocks/admin/scan/package.html.twig',
            context: [
                'form' => $form->createView(),
                'number' => $current->getPartNumber(),
                'products' => $products,
            ],
        );

        $message->setContent($content);

    }
}