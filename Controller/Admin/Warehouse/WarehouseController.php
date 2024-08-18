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

namespace BaksDev\Products\Stocks\Controller\Admin\Warehouse;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\ProductStock;
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
