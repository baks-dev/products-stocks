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

namespace BaksDev\Products\Stocks\Controller\Admin\Package;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionSelectedProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionSelectedProductStockForm;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PACKAGE')]
final class ExtraditionSelectedController extends AbstractController
{
    /**
     * Укомплектовать выбранные складские заявки
     */
    #[Route('/admin/product/stock/package/extradition-selected', name: 'admin.package.extradition-selected', methods: ['GET', 'POST'])]
    public function extradition(
        Request $request,
        ExtraditionProductStockHandler $ExtraditionProductStockHandler,
        ProductsByProductStocksInterface $productDetail,
        CentrifugoPublishInterface $publish,
        ProductStocksEventInterface $productStocksEvent,
    ): Response
    {

        $ExtraditionSelectedProductStockDTO = new ExtraditionSelectedProductStockDTO();

        $form = $this
            ->createForm(
                type: ExtraditionSelectedProductStockForm::class,
                data: $ExtraditionSelectedProductStockDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.package.extradition-selected')],
            )
            ->handleRequest($request);

        $products = [];
        $stock_numbers = [];

        /** @var ExtraditionProductStockDTO $ExtraditionProductStockDTO */
        foreach($ExtraditionSelectedProductStockDTO->getCollection() as $ExtraditionProductStockDTO)
        {

            $productStockEventEntity = $productStocksEvent->forEvent($ExtraditionProductStockDTO->getEvent())->find();

            /** Скрываем идентификатор у остальных пользователей */
            $publish
                ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                ->addData(['identifier' => (string) $productStockEventEntity->getMain()])
                ->send('remove');


            $stock_numbers[] = $productStockEventEntity->getInvariable()?->getNumber();
            $products[] = $productDetail->fetchAllProductsByProductStocksAssociative($productStockEventEntity->getMain());

        }


        if($form->isSubmitted() && $form->isValid() && $form->has('extradition_selected_package'))
        {
            $this->refreshTokenForm($form);

            foreach($ExtraditionSelectedProductStockDTO->getCollection() as $ExtraditionProductStockDTO)
            {

                $handle = $ExtraditionProductStockHandler->handle($ExtraditionProductStockDTO);

                /** Скрываем идентификатор у всех пользователей */
                $remove = $publish
                    ->addData(['profile' => false]) // Скрывает у всех
                    ->addData(['identifier' => (string) $handle->getId()])
                    ->send('remove');

                $flash = $this->addFlash
                (
                    'page.package',
                    $handle instanceof ProductStock ? 'success.extradition' : 'danger.extradition',
                    'products-stocks.admin',
                    $handle,
                    $remove ? 200 : 302
                );
            }

            return $flash ?: $this->redirectToRoute('products-stocks:admin.package.index');
        }


        /** Выводим несколько заявок */
        if($ExtraditionSelectedProductStockDTO->getCollection()->count() > 1)
        {
            return $this->render([
                'form' => $form->createView(),
                'stock_numbers' => $stock_numbers,
            ]);
        }

        /** Выводим одну заявку */
        return $this->render(
            parameters: [
                'form' => $form->createView(),
                'products' => $products,
                'stock_number' => current($stock_numbers),
            ],
            module: 'products-stocks',
            dir: '/admin/package/extradition',
            file: 'content.html.twig',
        );

    }
}
