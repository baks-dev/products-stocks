<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
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
    ): Response {

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
            'number' => $Event->getNumber()
        ]);
    }
}
