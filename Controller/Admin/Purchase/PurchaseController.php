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

namespace BaksDev\Products\Stocks\Controller\Admin\Purchase;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PURCHASE_NEW')]
final class PurchaseController extends AbstractController
{
    /**
     * Добавить закупку
     */
    #[Route('/admin/product/stock/purchase', name: 'admin.purchase.new', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        PurchaseProductStockHandler $PurchaseProductStockHandler
    ): Response {

        if(!$this->getProfileUid())
        {
            throw new UserNotFoundException('User Profile not found');
        }

        $purchaseDTO = new PurchaseProductStockDTO();

        // Форма добавления
        $form = $this->createForm(PurchaseProductStockForm::class, $purchaseDTO, [
            'action' => $this->generateUrl('products-stocks:admin.purchase.new'),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('purchase'))
        {
            $this->refreshTokenForm($form);

            $purchase = clone $purchaseDTO;

            /** @var ProductStockDTO $product */
            foreach($purchase->getProduct() as $product)
            {
                $purchaseDTO->setProduct(new ArrayCollection());
                $purchaseDTO->addProduct($product);
                $handle = $PurchaseProductStockHandler->handle($purchaseDTO);

                if(!$handle instanceof ProductStock)
                {
                    $this->addFlash(
                        'page.purchase',
                        'danger.purchase',
                        'products-stocks.admin',
                        $handle
                    );
                }
            }

            $this->addFlash(
                'page.purchase',
                'success.purchase',
                'products-stocks.admin'
            );

            return $this->redirectToRoute('products-stocks:admin.purchase.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
