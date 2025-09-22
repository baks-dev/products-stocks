<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\UseCase\Admin\Stock\MovingProductToStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Stock\MovingProductToStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Stock\MovingProductToStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_STORAGE_MOVE')]
final class MoveToStockStorageController extends AbstractController
{
    /** Переместить на другое место складирования в рамках склада */
    #[Route('/admin/product/stock/storage/move/{id}', name: 'admin.total.move', methods: ['GET', 'POST'])]
    public function move(
        Request $request,
        #[MapEntity] ProductStockTotal $stockTotal,
        MovingProductToStockHandler $MovingProductToStockHandler
    ): Response
    {
        $movingProductToStockDTO = new MovingProductToStockDTO();
        $stockTotal->getDto($movingProductToStockDTO);

        $movingProductToStockDTO->setFromId($stockTotal->getId());

        $form = $this->createForm(
            MovingProductToStockForm::class,
            $movingProductToStockDTO,
            options: ['action' => $this->generateUrl(
                'products-stocks:admin.total.move',
                ['id' => $stockTotal->getId()]
            )]
        )
        ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_stock_storage_move'))
        {
            $handle = $MovingProductToStockHandler->handle($movingProductToStockDTO);

            $this->addFlash(
                'page.storageMove',
                $handle instanceof ProductStockTotal ? 'success.storageMove' : 'danger.storageMove',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}