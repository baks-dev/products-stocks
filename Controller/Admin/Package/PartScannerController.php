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

namespace BaksDev\Products\Stocks\Controller\Admin\Package;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryDTO;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryForm;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryHandler;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Products\Stocks\Forms\PartScanner\PartScannerDTO;
use BaksDev\Products\Stocks\Forms\PartScanner\PartScannerForm;
use BaksDev\Products\Stocks\Messenger\Stocks\MultiplyProductStocksExtradition\MultiplyProductStocksExtraditionMessage;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\AllProductStocksOrdersPartInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\ProductStocksOrdersPartResult;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PACKAGE')]
final class PartScannerController extends AbstractController
{
    #[Route('/admin/product/stock/package/scan/{id}', name: 'admin.scan.package', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[ParamConverter(ProductStockPartUid::class)] $id,
        AllProductStocksOrdersPartInterface $AllProductStocksOrdersPartRepository,
        MessageDispatchInterface $MessageDispatch,
    ): Response
    {
        /** Получаем все заказы в сборочном листе */
        $result = $AllProductStocksOrdersPartRepository
            ->forProductStockPart($id)
            ->findAll();

        if(false === $result || false === $result->valid())
        {
            throw new InvalidArgumentException('Invalid Argument ProductStockPartUid');
        }

        $form = $this
            ->createForm(
                type: PartScannerForm::class,
                data: new PartScannerDTO($id),
                options: [
                    'action' => $this->generateUrl(
                        'products-stocks:admin.scan.package',
                        ['id' => $id],
                    ),
                ],
            )
            ->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('part_scanner'))
        {
            $this->refreshTokenForm($form);

            /** @var ProductStocksOrdersPartResult $current */
            $current = $result->current();

            foreach($result as $ProductStocksOrdersPartResult)
            {

                /** Все идентификаторы событий складской заявки */
                $events = $ProductStocksOrdersPartResult->getProductStocksEvents();

                if(empty($events))
                {
                    continue;
                }

                foreach($events as $ProductStocksEvent)
                {
                    $ProductStockEventUid = new ProductStockEventUid($ProductStocksEvent);

                    $MultiplyProductStocksExtraditionMessage = new MultiplyProductStocksExtraditionMessage(
                        $ProductStockEventUid,
                        $this->getCurrentProfileUid(),
                        $this->getCurrentUsr(),
                    );

                    $MessageDispatch->dispatch(
                        message: $MultiplyProductStocksExtraditionMessage,
                        transport: 'products-stocks',
                    );
                }
            }

            $this->addFlash(
                'page.package',
                'success.scan',
                'products-stocks.admin',
                [$current->getPartNumber()],
            );


            return $this->redirectToReferer();
        }

        $this->addFlash(
            'page.package',
            'Необходимо выполнить сканирование QR-кода',
            'products-stocks.admin',
        );

        return $this->redirectToReferer();

    }
}
