<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCKS_EXTRADITION')]
final class ExtraditionController extends AbstractController
{
    /** Укомплектовать складскую заявку */
    #[Route('/admin/product/stock/package/extradition/{id}', name: 'admin.package.extradition', methods: ['GET', 'POST'])]
    public function extradition(
        Request $request,
        #[MapEntity] ProductStockEvent $ProductStockEvent,
        ExtraditionProductStockHandler $ExtraditionProductStockHandler,
        ProductsByProductStocksInterface $productDetail,
    ): Response {
        $ExtraditionProductStockDTO = new ExtraditionProductStockDTO($this->getProfileUid());
        $ProductStockEvent->getDto($ExtraditionProductStockDTO);

        // Форма заявки
        $form = $this->createForm(ExtraditionProductStockForm::class, $ExtraditionProductStockDTO, [
            'action' => $this->generateUrl('ProductStocks:admin.package.extradition', ['id' => $ExtraditionProductStockDTO->getEvent()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $form->has('extradition'))
        {
            $ProductStocks = $ExtraditionProductStockHandler->handle($ExtraditionProductStockDTO);

            if ($ProductStocks instanceof ProductStock)
            {
                $this->addFlash('success', 'admin.success.extradition', 'admin.product.stock');

                return $this->redirectToRoute('ProductStocks:admin.package.index');
            }

            $this->addFlash('danger', 'admin.danger.extradition', 'admin.product.stock', $ProductStocks);

            return $this->redirectToReferer();
        }

        return $this->render([
            'form' => $form->createView(),
            'name' => $ProductStockEvent->getNumber(),
            'products' => $productDetail->fetchAllProductsByProductStocksAssociative($ProductStockEvent->getMain())
        ]);
    }
}
