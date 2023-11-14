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

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INCOMING_ACCEPT')]
final class IncomingController extends AbstractController
{
    /**
     * Добавить приход на склад
     */
    #[Route('/admin/product/stock/incoming/{id}', name: 'admin.incoming.accept', methods: ['GET', 'POST'])]
    public function incoming(
        #[MapEntity] ProductStockEvent $ProductStockEvent,
        Request $request,
        IncomingProductStockHandler $IncomingProductStockHandler,
        ProductsByProductStocksInterface $productDetail,
    ): Response {

        $IncomingProductStockDTO = new IncomingProductStockDTO(); // $this->getProfileUid()
        $ProductStockEvent->getDto($IncomingProductStockDTO);
        $IncomingProductStockDTO->setComment(null); // обнуляем комментарий

        // Форма добавления
        $form = $this->createForm(IncomingProductStockForm::class, $IncomingProductStockDTO, [
            'action' => $this->generateUrl('products-stocks:admin.incoming.accept', ['id' => $IncomingProductStockDTO->getEvent()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $form->has('incoming'))
        {
            $handle = $IncomingProductStockHandler->handle($IncomingProductStockDTO);

            $this->addFlash
            (
                'admin.page.accept',
                $handle instanceof ProductStock ? 'admin.success.accept' : 'admin.danger.accept',
                'admin.product.stock',
                $handle
            );

            return $this->redirectToRoute('products-stocks:admin.warehouse.index');
        }

        return $this->render([
            'form' => $form->createView(),
            'name' => $ProductStockEvent->getNumber(),
            'products' => $productDetail->fetchAllProductsByProductStocksAssociative($ProductStockEvent->getMain())
        ]);
    }
}
