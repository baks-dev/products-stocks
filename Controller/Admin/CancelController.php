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

namespace BaksDev\Products\Stocks\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_CANCEL')]
final class CancelController extends AbstractController
{
    /** Отмена складской заявки (БЕЗ ОТМЕНЫ ЗАКАЗА) */
    #[Route('/admin/product/stock/cancel/{id}', name: 'admin.cancel', methods: ['GET', 'POST'])]
    public function cancel(
        #[MapEntity] ProductStockEvent $Event,
        Request $request,
        CancelProductStockHandler $MovingProductStockCancelHandler,
    ): Response
    {

        $CancelProductStockDTO = new CancelProductStockDTO();
        $Event->getDto($CancelProductStockDTO);

        // Форма заявки
        $form = $this->createForm(CancelProductStockForm::class, $CancelProductStockDTO, [
            'action' => $this->generateUrl('products-stocks:admin.cancel', ['id' => (string) $Event]),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('product_stock_cancel'))
        {
            $this->refreshTokenForm($form);

            $handle = $MovingProductStockCancelHandler->handle($CancelProductStockDTO);

            $this->addFlash(
                'page.cancel',
                $handle instanceof ProductStock ? 'success.cancel' : 'danger.cancel',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToReferer();
        }

        return $this->render([
            'form' => $form->createView(),
            'number' => $Event->getInvariable()?->getNumber()
        ]);
    }
}
