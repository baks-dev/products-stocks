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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Cancel\CancelProductStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING_CANCEL')]
final class CancelController extends AbstractController
{
    /** Отмена заявки на перемещение */
    #[Route('/admin/product/stock/moving/cancel/{id}', name: 'admin.moving.cancel', methods: ['GET', 'POST'])]
    public function cancel(
        #[MapEntity] ProductStockEvent $Event,
        Request $request,
        CancelProductStockHandler $MovingProductStockCancelHandler,
    ): Response
    {
        $MovingProductStockCancelDTO = new CancelProductStockDTO();
        $Event->getDto($MovingProductStockCancelDTO);

        // Форма заявки
        $form = $this->createForm(CancelProductStockForm::class, $MovingProductStockCancelDTO, [
            'action' => $this->generateUrl('products-stocks:admin.moving.cancel', ['id' => (string) $Event ]),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('moving_product_stock_cancel'))
        {
            $handle = $MovingProductStockCancelHandler->handle($MovingProductStockCancelDTO);

            $this->addFlash
            (
                'admin.page.cancel',
                $handle instanceof ProductStock ? 'admin.success.cancel' : 'admin.danger.cancel',
                'admin.product.stock',
                $handle
            );

            return $handle instanceof ProductStock ? $this->redirectToRoute('products-stocks:admin.moving.index') : $this->redirectToReferer();

        }

        return $this->render([
            'form' => $form->createView(),
            'number' => $Event->getNumber()
        ]);
    }
}
