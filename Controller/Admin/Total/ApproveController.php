<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalDTO;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalForm;
use BaksDev\Products\Stocks\UseCase\Admin\ApproveTotal\ApproveProductStockTotalHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Обновить approve для остатков
 * Используется на странице Складской учет
 * По клику на кнопку "Подтвердить" вызвает модальное окно "Подтверждение порога остатков"
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_APPROVE')]
final class ApproveController extends AbstractController
{
    #[Route('/admin/product/stocks/total/approve/{id}', name: 'admin.total.approve', methods: ['GET', 'POST'])]
    public function approve(
        Request $request,
        #[MapEntity] ProductStockTotal $ProductStocksTotal,
        ApproveProductStockTotalHandler $approveHandler,
    ): Response
    {
        $ApproveProductStockTotalDTO = new ApproveProductStockTotalDTO();
        $ProductStocksTotal->getDto($ApproveProductStockTotalDTO);

        /** Подтверждаем остаток флагом TRUE */
        $ApproveProductStockTotalDTO->getApprove()->setValue(true);

        /* Форма "Подтверждение порога остатков" */
        $form = $this
            ->createForm(
                type: ApproveProductStockTotalForm::class,
                data: $ApproveProductStockTotalDTO,
                options: ['action' => $this->generateUrl(
                    'products-stocks:admin.total.approve',
                    ['id' => $ApproveProductStockTotalDTO->getId()],
                )],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('approve'))
        {
            $this->refreshTokenForm($form);

            $handle = $approveHandler->handle($ApproveProductStockTotalDTO);

            $this->addFlash(
                'page.approve',
                $handle instanceof ProductStockTotal ? 'success.approve' : 'danger.approve',
                'products-stocks.admin',
                $handle,
            );

            return $this->redirectToRoute('products-stocks:admin.total.index');
        }

        return $this->render(
            [
                'form' => $form->createView(),
                'name' => "Подтвердить порог остатков",
            ],
        );

    }
}