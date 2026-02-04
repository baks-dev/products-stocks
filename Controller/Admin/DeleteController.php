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

namespace BaksDev\Products\Stocks\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstRepository;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksForm;
use BaksDev\Products\Stocks\UseCase\Admin\Delete\DeleteProductStocksHandler;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_DELETE')]
final class DeleteController extends AbstractController
{
    #[Route('/admin/product/stock/delete/{id}', name: 'admin.delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        #[MapEntity] ProductStockEvent $ProductStocksEvent,
        DeleteProductStocksHandler $ProductStocksDeleteHandler,
        ProductDetailByConstRepository $oneProductDetailByConst
    ): Response
    {

        $ProductStocksDeleteDTO = $ProductStocksEvent->getDto(DeleteProductStocksDTO::class);

        $form = $this->createForm(DeleteProductStocksForm::class, $ProductStocksDeleteDTO, [
            'action' => $this->generateUrl(
                'products-stocks:admin.delete',
                ['id' => $ProductStocksDeleteDTO->getEvent()],
            ),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('delete_product_stock'))
        {
            $this->refreshTokenForm($form);

            //$handle = $ProductStocksDeleteHandler->handle($ProductStocksDeleteDTO);

            $handle = null;

            $JsonResponse = $this->addFlash(
                'page.purchase',
                $handle instanceof ProductStock ? 'success.delete' : 'danger.delete',
                'products-stocks.admin',
                $handle,
            );

            return $JsonResponse ?: $this->redirectToReferer();
        }

        /**
         * Получаем информацию о продукте для отображения в форме.
         * Предполагается что в коллекции закупки должен быть один продукт.
         *
         * @var ProductStockProduct $ProductStockProduct
         */

        if($ProductStocksEvent->getProduct()->isEmpty())
        {
            throw new InvalidArgumentException('Product not found');
        }

        $ProductStockProduct = $ProductStocksEvent->getProduct()->current();

        $product = $oneProductDetailByConst
            ->product($ProductStockProduct->getProduct())
            ->offerConst($ProductStockProduct->getOffer())
            ->variationConst($ProductStockProduct->getVariation())
            ->modificationConst($ProductStockProduct->getModification())
            ->find();

        if($product === false)
        {
            throw new InvalidArgumentException('Product not found');
        }

        return $this->render([
            'form' => $form->createView(),
            'number' => $ProductStocksEvent->getNumber(),
            'product' => $product,
        ]);
    }
}
