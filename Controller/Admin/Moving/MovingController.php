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
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\Move\ProductStockMoveDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockHandler;

use BaksDev\Products\Stocks\UseCase\Admin\Moving\Products\ProductStockDTO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING_NEW')]
final class MovingController extends AbstractController
{
    /** Заявка на перемещение товара на другой склад */
    #[Route('/admin/product/stock/moving/new', name: 'admin.moving.new', methods: ['GET', 'POST'])]
    public function moving(
        Request $request,
        MovingProductStockHandler $handler,
        ProductWarehouseTotalInterface $productWarehouseTotal,
        ProductDetailByConstInterface $productDetailByConst
    ): Response
    {
        $movingDTO = new MovingProductStockDTO($this->getUsr());

        // Форма заявки
        $form = $this->createForm(MovingProductStockForm::class, $movingDTO, [
            'action' => $this->generateUrl('products-stocks:admin.moving.new'),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('moving'))
        {
            $this->refreshTokenForm($form);

            $success = true;

            /** Создаем каждое отдельно перемещение */

            /** @var \BaksDev\Products\Stocks\UseCase\Admin\Moving\ProductStockDTO $move */
            foreach($movingDTO->getMove() as $move)
            {
                /** Проверяем, что на складе не изменилось доступное количество */

                /** @var ProductStockDTO $product */
                foreach($move->getProduct() as $product)
                {
                    $ProductStockTotal = $productWarehouseTotal->getProductProfileTotal(
                        $move->getMove()->getWarehouse(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification()
                    );

                    if($product->getTotal() > $ProductStockTotal)
                    {
                        $productDetail = $productDetailByConst->fetchProductDetailByConstAssociative(
                            $product->getProduct(),
                            $product->getOffer(),
                            $product->getVariation(),
                            $product->getModification()
                        );


                        $msg = '<b>'.$productDetail['product_name'].'</b>';
                        $msg .= ' ('.$productDetail['product_article'].')';

                        if($productDetail['product_offer_value'])
                        {
                            $msg .= '<br>'.$productDetail['product_offer_name'].': ';
                            $msg .= '<b>'.$productDetail['product_offer_value'].'</b>';
                        }

                        if($productDetail['product_variation_name'])
                        {
                            $msg .= ' '.$productDetail['product_variation_name'].': ';
                            $msg .= '<b>'.$productDetail['product_variation_value'].'</b>';
                        }

                        if($productDetail['product_modification_value'])
                        {
                            $msg .= ' '.$productDetail['product_modification_name'].': ';
                            $msg .= '<b>'.$productDetail['product_modification_value'].'</b>';
                        }


                        $msg .= '<br>Доступно: <b>'.$ProductStockTotal.'</b>';


                        $this->addFlash('Недостаточное количество продукции', $msg, 'products-stocks.admin');
                        continue 2;
                    }
                }

                $move->setProfile($move->getMove()->getWarehouse());
                $move->setComment($movingDTO->getComment());

                $ProductStock = $handler->handle($move);

                if(!$ProductStock instanceof ProductStock)
                {
                    $success = false;
                    $this->addFlash('danger', 'danger.move', 'products-stocks.admin', $ProductStock);
                }
            }

            if($success)
            {
                $this->addFlash('success', 'success.move', 'products-stocks.admin');
            }

            return $this->redirectToRoute('products-stocks:admin.moving.index');

        }

        return $this->render(['form' => $form->createView()]);
    }
}
