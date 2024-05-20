<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Controller\Admin\Pickup;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\DeliveryTransport\Entity\Package\DeliveryPackage;
use BaksDev\DeliveryTransport\Repository\Package\ExistPackageProductStocks\ExistPackageProductStocksInterface;
use BaksDev\DeliveryTransport\Repository\Package\PackageByProductStocks\PackageByProductStocksInterface;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\CompletedPackageDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\CompletedPackageHandler;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\CompletedProductStockDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\CompletedProductStockForm;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\CompletedProductStockHandler;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Delivery\DeliveryProductStockDTO;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PICKUP')]
final class CompletedController extends AbstractController
{
    /**
     * Выдать заказ клиенту.
     */
    #[Route('/admin/product/stocks/completed/{id}', name: 'admin.pickup.completed', methods: ['GET', 'POST'])]
    public function delivery(
        Request $request,
        #[MapEntity] ProductStock $ProductStock,
        CompletedProductStockHandler $CompletedProductStockHandler,
        ProductsByProductStocksInterface $productDetail,
    ): Response
    {
        /**
         * @var DeliveryProductStockDTO $DeliveryProductStockDTO
         */
        $CompletedProductStockDTO = new CompletedProductStockDTO(
            $ProductStock->getEvent(),
            $this->getProfileUid()
        );

        // Форма
        $form = $this->createForm(CompletedProductStockForm::class, $CompletedProductStockDTO, [
            'action' => $this->generateUrl(
                'products-stocks:admin.pickup.completed',
                ['id' => $ProductStock->getId()]
            )]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('completed_package'))
        {
            $this->refreshTokenForm($form);

            $handle = $CompletedProductStockHandler->handle($CompletedProductStockDTO);

            $this->addFlash
            (
                'page.edit',
                $handle instanceof ProductStock ? 'success.pickup' : 'danger.pickup',
                'products-stocks.package',
                $handle
            );

            return $this->redirectToReferer();
        }

        //dd($productDetail->fetchAllProductsByProductStocksAssociative($ProductStock->getId()));

        return $this->render(
            [
                'form' => $form->createView(),
                'products' => $productDetail->fetchAllProductsByProductStocksAssociative($ProductStock->getId())
            ]
        );
    }
}
