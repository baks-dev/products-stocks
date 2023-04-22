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

namespace BaksDev\Products\Stocks\Controller\Admin\Incoming;

// use App\Module\Materials\Stock\Entity\MaterialStock;
// use App\Module\Materials\Stock\UseCase\Admin\Incoming\IncomingMaterialStockDTO;
// use App\Module\Materials\Stock\UseCase\Admin\Incoming\IncomingMaterialStockForm;
// use App\Module\Materials\Stock\UseCase\MaterialStockAggregate;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Services\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[RoleSecurity('ROLE_PRODUCT_STOCK_INCOMING')]
final class IncomingController extends AbstractController
{
    /** Добавить приход на склад */
    #[Route('/admin/product/stock/incoming', name: 'admin.incoming.new', methods: ['GET', 'POST'])]
    public function incoming(
        Request                     $request,
        IncomingProductStockHandler $handler
    ): Response
    {

        $incomingDTO = new IncomingProductStockDTO($this->getProfileUid());

        // Форма добавления
        $form = $this->createForm(IncomingProductStockForm::class, $incomingDTO, [
            'action' => $this->generateUrl('ProductStocks:admin.incoming.new'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $ProductStock = $handler->handle($incomingDTO);

            if ($ProductStock instanceof ProductStock) {
                $this->addFlash('success', 'admin.success.new', 'admin.product.stock');
            } else {
                $this->addFlash('danger', 'admin.danger.new', 'admin.product.stock', $ProductStock);
            }

            //return $this->redirectToReferer();
            return $this->redirectToRoute('ProductStocks:admin.incoming.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}