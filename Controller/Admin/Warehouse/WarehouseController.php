<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Controller\Admin\Warehouse;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_WAREHOUSE_SEND')]
final class WarehouseController extends AbstractController
{
    /**
     * Отправить закупку на указанный склад
     */
    #[Route('/admin/product/stock/warehouse/{id}', name: 'admin.warehouse.send', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        WarehouseProductStockHandler $handler,
        #[MapEntity] ProductStockEvent $Event
    ): Response
    {

        if(!$this->getProfileUid())
        {
            throw new UserNotFoundException('User Profile not found');
        }

        $WarehouseProductStockDTO = new WarehouseProductStockDTO($this->getUsr());

        $Event->getDto($WarehouseProductStockDTO);

        /**
         * Если заявка на перемещение - присваиваем склад назначения
         * и профиль пользователя, отпарившего заявку
         */
        if($Event->getMoveDestination())
        {
            $WarehouseProductStockDTO->setProfile($Event->getMoveDestination());
            $WarehouseProductStockDTO->getMove()?->setDestination($this->getProfileUid());
        }

        // Форма добавления
        $form = $this->createForm(WarehouseProductStockForm::class, $WarehouseProductStockDTO, [
            'action' => $this->generateUrl(
                'products-stocks:admin.warehouse.send',
                ['id' => $WarehouseProductStockDTO->getEvent()]
            ),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('send'))
        {
            $this->refreshTokenForm($form);

            $handle = $handler->handle($WarehouseProductStockDTO);

            $this->addFlash
            (
                'page.warehouse',
                $handle instanceof ProductStock ? 'success.warehouse' : 'danger.warehouse',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToReferer();

            //return $this->redirectToRoute('products-stocks:admin.purchase.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
