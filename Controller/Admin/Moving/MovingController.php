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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\CollectionMovingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\CollectionMovingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\MovingProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Moving\Products\ProductStockProductDTO;
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
        MovingProductStockHandler $MovingProductStockHandler,
        ProductWarehouseTotalInterface $productWarehouseTotal,
        ProductDetailByConstInterface $productDetailByConst
    ): Response
    {
        $movingDTO = new CollectionMovingProductStockDTO($this->getUsr())->setDestinationWarehouse($this->getProfileUid());

        // Форма заявки
        $form = $this
            ->createForm(
                type: CollectionMovingProductStockForm::class,
                data: $movingDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.new')],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('moving'))
        {
            $this->refreshTokenForm($form);

            $success = true;

            /**
             * Создаем каждое отдельно перемещение
             */

            /** @var MovingProductStockDTO $move */
            foreach($movingDTO->getMove() as $move)
            {
                /**
                 * Проверяем, что на складе не изменилось доступное количество
                 *
                 * @var ProductStockProductDTO $product
                 */
                foreach($move->getProduct() as $product)
                {
                    $ProductStockTotal = $productWarehouseTotal->getProductProfileTotal(
                        $move->getMove()->getWarehouse(),
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification(),
                    );

                    if($product->getTotal() > $ProductStockTotal)
                    {
                        $productDetail = $productDetailByConst
                            ->product($product->getProduct())
                            ->offerConst($product->getOffer())
                            ->variationConst($product->getVariation())
                            ->modificationConst($product->getModification())
                            ->find();

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

                        $this->addFlash(
                            type: 'Недостаточное количество продукции',
                            message: $msg,
                            domain: 'products-stocks.admin',
                        );

                        continue 2;
                    }
                }

                $move
                    ->getInvariable()
                    ->setUsr($this->getUsr()->getId())
                    ->setProfile($move->getMove()->getWarehouse());

                $move->setComment($movingDTO->getComment());

                $ProductStock = $MovingProductStockHandler->handle($move);

                if(false === ($ProductStock instanceof ProductStock))
                {
                    $success = false;

                    $this->addFlash(
                        type: 'page.moving',
                        message: 'danger.move',
                        domain: 'products-stocks.admin',
                        arguments: $ProductStock,
                    );
                }
            }

            if($success)
            {
                $this->addFlash(
                    type: 'page.moving',
                    message: 'success.move',
                    domain: 'products-stocks.admin',
                );
            }

            return $this->redirectToRoute('products-stocks:admin.moving.index', status: 200);
        }

        return $this->render(['form' => $form->createView()]);
    }
}
