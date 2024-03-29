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
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\ProductStockDTO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING_NEW')]
final class MovingController extends AbstractController
{
    /** Заявка на перемещение товара на другой склад */
    #[Route('/admin/product/stock/moving/new', name: 'admin.moving.new', methods: ['GET', 'POST'])]
    public function moving(
        Request $request,
        MovingProductStockHandler $handler,
        // #[MapEntity] ProductStockEvent $Event,
    ): Response
    {
        $movingDTO = new MovingProductStockDTO($this->getUsr());

        //$incomingDTO->setProfile($this->getProfileUid());

        // Форма заявки
        $form = $this->createForm(MovingProductStockForm::class, $movingDTO, [
            'action' => $this->generateUrl('products-stocks:admin.moving.new'),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('moving'))
        {
            /** @var ProductStockDTO $move */
            $success = true;

            /** Создаем каждое отдельно перемещение */
            foreach($movingDTO->getMove() as $move)
            {
                $move->setProfile($move->getMove()->getWarehouse());
                $move->setComment($movingDTO->getComment());

                $ProductStock = $handler->handle($move);

                if(!$ProductStock instanceof ProductStock)
                {
                    $success = false;
                    $this->addFlash('danger', 'admin.danger.move', 'admin.product.stock', $ProductStock);
                }
            }

            if($success)
            {
                $this->addFlash('success', 'admin.success.move', 'admin.product.stock');
            }

            return $this->redirectToRoute('products-stocks:admin.moving.index');

        }

        return $this->render(['form' => $form->createView()]);
    }
}
