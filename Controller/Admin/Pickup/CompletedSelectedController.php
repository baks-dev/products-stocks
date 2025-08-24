<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedProductStockDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedProductStockHandler;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedSelectedProductStockDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\Package\Completed\ProductStock\CompletedSelectedProductStockForm;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PICKUP')]
final class CompletedSelectedController extends AbstractController
{
    /**
     * Выдать выбранные заказы клиенту.
     */
    #[Route('/admin/product/stocks/completed-selected', name: 'admin.pickup.completed-selected', methods: ['GET', 'POST'])]
    public function delivery(
        Request $request,
        CompletedProductStockHandler $CompletedProductStockHandler,
        ProductsByProductStocksInterface $productDetail,
        CentrifugoPublishInterface $publish,
        ProductStocksEventInterface $productStocksEvent,
    ): Response
    {

        $CompletedSelectedProductStockDTO = new CompletedSelectedProductStockDTO();

        $form = $this
            ->createForm(
                type: CompletedSelectedProductStockForm::class,
                data: $CompletedSelectedProductStockDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.pickup.completed-selected')],
            )
            ->handleRequest($request);

        $products = [];


        /** @var CompletedProductStockDTO $CompletedProductStockDTO */
        foreach($CompletedSelectedProductStockDTO->getCollection() as $CompletedProductStockDTO)
        {
            $productStocksEventEntity = $productStocksEvent->forEvent($CompletedProductStockDTO->getEvent())->find();

            /** Скрываем идентификатор у остальных пользователей */
            $publish
                ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                ->addData(['identifier' => (string) $productStocksEventEntity->getMain()])
                ->send('remove');

            $products[] = $productDetail->fetchAllProductsByProductStocksAssociative($productStocksEventEntity->getMain());
        }


        if($form->isSubmitted() && $form->isValid() && $form->has('completed_selected_package'))
        {

            $this->refreshTokenForm($form);

            foreach($CompletedSelectedProductStockDTO->getCollection() as $CompletedProductStockDTO)
            {

                $handle = $CompletedProductStockHandler->handle($CompletedProductStockDTO);

                /** Скрываем идентификатор у всех пользователей */
                $remove = $publish
                    ->addData(['profile' => false]) // Скрывает у всех
                    ->addData(['identifier' => (string) $handle->getId()])
                    ->send('remove');

                $flash = $this->addFlash
                (
                    'page.pickup',
                    $handle instanceof ProductStock ? 'success.pickup' : 'danger.pickup',
                    'products-stocks.admin',
                    $handle,
                    $remove ? 200 : 302,
                );

            }
            return $flash ?: $this->redirectToRoute('products-stocks:admin.pickup.index');

        }


        /** Выводим несколько заказов */
        if($CompletedSelectedProductStockDTO->getCollection()->count() > 1)
        {
            return $this->render([
                'form' => $form->createView(),
                'products' => $products,
            ]);
        }

        /** Выводим один заказ */
        return $this->render(
            parameters: [
                'form' => $form->createView(),
                'products' => $products,
            ],
            module: 'products-stocks',
            dir: '/admin/pickup/completed',
            file: 'content.html.twig',
        );
    }
}
