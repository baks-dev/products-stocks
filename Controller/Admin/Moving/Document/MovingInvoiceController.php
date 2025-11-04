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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving\Document;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Forms\MovingsInvoice\MovingsInvoiceDTO;
use BaksDev\Products\Stocks\Forms\MovingsInvoice\MovingsInvoiceForm;
use BaksDev\Products\Stocks\Forms\MovingsInvoice\MovingsInvoiceProductStockDTO;
use BaksDev\Products\Stocks\Repository\ProductStocksMoveDetail\ProductStocksMoveDetailInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Print\ProductStockEventPrintDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Print\ProductStockEventPrintHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileById\UserProfileByIdInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;


#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING')]
final class MovingInvoiceController extends AbstractController
{
    #[Route('/admin/product/stocks/moving/document', name: 'admin.moving.document', methods: ['GET', 'POST'])]
    public function index(
        #[Target('productsStocksLogger')] LoggerInterface $logger,
        Request $request,
        CentrifugoPublishInterface $publish,
        UserProfileByIdInterface $UserProfileByIdRepository,
        ProductStocksMoveDetailInterface $stocksMoveDetail,
        ProductStockEventPrintHandler $ProductStockEventPrintHandler
    ): Response
    {

        $MovingsInvoiceDTO = new MovingsInvoiceDTO();

        $form = $this
            ->createForm(
                type: MovingsInvoiceForm::class,
                data: $MovingsInvoiceDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.document')],
            )
            ->handleRequest($request);


        $productStocks = [];

        if($form->isSubmitted() && $form->has('collection'))
        {

            /**  @var MovingsInvoiceProductStockDTO $movingsInvoiceStockDTO */
            foreach($MovingsInvoiceDTO->getCollection() as $movingsInvoiceStockDTO)
            {
                /** Информация о заявке */
                $ProductsStockInfo = $stocksMoveDetail
                    ->forProfile($this->getProfileUid())
                    ->find($movingsInvoiceStockDTO->getStock());


                if(true === empty($ProductsStockInfo))
                {
                    continue;
                }

                $productStocks[] = $ProductsStockInfo;

                if (false === $ProductsStockInfo->isPrinted()) {
                    $productsStockEventPrintDTO = new ProductStockEventPrintDTO($ProductsStockInfo->getEvent());

                    $productsStockEventPrinted = $ProductStockEventPrintHandler->handle($productsStockEventPrintDTO);

                    if (false === $productsStockEventPrinted) {
                        $logger->warning(
                            'products-stocks: Ошибка сохранения данных о печати акта приема-передачи',
                            [self::class.':'.__LINE__,],
                        );
                    }
                }


                // Отправляем сокет для скрытия заявки у других менеджеров
                $socket = $publish
                    ->addData(['stock' => (string) $ProductsStockInfo->getId()])
                    //                    ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                    ->send('products-stocks');


                if($socket && $socket->isError())
                {
                    return $this->redirectToRoute('products-stocks:admin.moving.index');
                }

            }

        }


        return $this->render(
            [
                'productStocks' => $productStocks,
                'profile' => $UserProfileByIdRepository->find(),
            ]
        );
    }
}