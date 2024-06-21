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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\ProductStockTotalEditHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Storage\ProductStockStorageEditDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Storage\ProductStockStorageEditForm;
use BaksDev\Products\Stocks\UseCase\Admin\Storage\ProductStockStorageEditHandler;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
//#[RoleSecurity('ROLE_PRODUCT_STOCK_STORAGE')]
final class StorageController extends AbstractController
{
    #[Route('/admin/product/stocks/storage/{id}', name: 'admin.total.storage', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity] ProductStockTotal $ProductStocksTotal,
        ProductStockTotalEditHandler $ProductStocksHandler,
        ProductDetailByConstInterface $productDetailByConst
    ): Response {

        $ProductStocksDTO = new ProductStockStorageEditDTO();
        $ProductStocksTotal->getDto($ProductStocksDTO);

        if(!$ProductStocksDTO->getProfile()->equals($this->getProfileUid()))
        {
            throw new InvalidArgumentException('Invalid profile');
        }


        // Форма
        $form = $this->createForm(ProductStockStorageEditForm::class, $ProductStocksDTO, [
            'action' => $this->generateUrl('products-stocks:admin.total.storage', ['id' => (string) $ProductStocksTotal]),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_stock_storage_edit'))
        {
            $this->refreshTokenForm($form);

            $handle = $ProductStocksHandler->handle($ProductStocksDTO);

            $this->addFlash(
                'page.total',
                $handle instanceof ProductStockTotal ? 'success.total' : 'danger.total',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToReferer();
        }

        $ProductDetail = $productDetailByConst->fetchProductDetailByConstAssociative(
            $ProductStocksDTO->getProduct(),
            $ProductStocksDTO->getOffer(),
            $ProductStocksDTO->getVariation(),
            $ProductStocksDTO->getModification()
        );

        return $this->render([
            'form' => $form->createView(),
            'card' => $ProductDetail
        ]);
    }
}
