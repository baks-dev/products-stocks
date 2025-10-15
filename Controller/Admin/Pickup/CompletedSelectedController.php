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
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductsByProductStocks\ProductsByProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use InvalidArgumentException;
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

        $productStockEventEntity = null;

        /** @var CompletedProductStockDTO $CompletedProductStockDTO */
        foreach($CompletedSelectedProductStockDTO->getCollection() as $CompletedProductStockDTO)
        {
            $productStocksEventEntity = $productStocksEvent->forEvent($CompletedProductStockDTO->getEvent())->find();

            if(false === ($productStocksEventEntity instanceof ProductStockEvent))
            {
                $CompletedSelectedProductStockDTO->removeCollection($productStocksEventEntity);

                continue;
            }

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

            $isSuccess = true;

            foreach($CompletedSelectedProductStockDTO->getCollection() as $CompletedProductStockDTO)
            {

                $handle = $CompletedProductStockHandler->handle($CompletedProductStockDTO);

                if($handle instanceof ProductStock)
                {
                    /** Скрываем идентификатор у всех пользователей */
                    $isPublish = $publish
                        ->addData(['profile' => false]) // Скрывает у всех
                        ->addData(['identifier' => (string) $handle->getId()])
                        ->send('remove');

                    /** Если не удалось скрыть идентификатор по сокету - редиректим на страницу */
                    if($isPublish === false)
                    {
                        $isSuccess = false;
                    }

                    continue;
                }

                /** Сообщение об ошибке */

                $this->addFlash
                (
                    type: 'page.pickup',
                    message: 'danger.pickup',
                    domain: 'products-stocks.admin',
                    arguments: $handle,
                );

                $isSuccess = false;
            }

            $flash = $this->addFlash
            (
                type: 'page.package',
                message: 'success.extradition',
                domain: 'products-stocks.admin',
                status: $isSuccess ? 200 : 302,
            );

            return $isSuccess && $flash ? $flash : $this->redirectToRoute('products-stocks:admin.pickup.index');
        }

        if(true === $CompletedSelectedProductStockDTO->getCollection()->isEmpty())
        {
            throw new InvalidArgumentException('Page Not Found');
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
