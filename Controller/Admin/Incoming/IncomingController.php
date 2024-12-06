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

namespace BaksDev\Products\Stocks\Controller\Admin\Incoming;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStockMinQuantity\ProductStockQuantityInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\Products\ProductStockDTO;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INCOMING_ACCEPT')]
final class IncomingController extends AbstractController
{
    /**
     * Добавить приход на склад
     */
    #[Route('/admin/product/stock/incoming/{id}', name: 'admin.incoming.accept', methods: ['GET', 'POST'])]
    public function incoming(
        #[MapEntity] ProductStockEvent $ProductStockEvent,
        Request $request,
        IncomingProductStockHandler $IncomingProductStockHandler,
        ProductStockQuantityInterface $productStockQuantity
    ): Response
    {

        $IncomingProductStockDTO = new IncomingProductStockDTO();
        $ProductStockEvent->getDto($IncomingProductStockDTO);


        // Форма добавления
        $form = $this->createForm(IncomingProductStockForm::class, $IncomingProductStockDTO, [
            'action' => $this->generateUrl('products-stocks:admin.incoming.accept', ['id' => $IncomingProductStockDTO->getEvent()]),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('incoming'))
        {
            $this->refreshTokenForm($form);

            $handle = $IncomingProductStockHandler->handle($IncomingProductStockDTO);

            $this->addFlash(
                'page.orders',
                $handle instanceof ProductStock ? 'success.accept' : 'danger.accept',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToRoute('products-stocks:admin.warehouse.index');
        }


        /** Рекомендуемое место складирвоания */

        /** @var ProductStockDTO $ProductStockDTO */

        $ProductStockDTO = $IncomingProductStockDTO->getProduct()->current();

        $productStorage = $productStockQuantity
            ->profile($IncomingProductStockDTO->getProfile())
            ->product($ProductStockDTO->getProduct())
            ->offerConst($ProductStockDTO->getOffer())
            ->variationConst($ProductStockDTO->getVariation())
            ->modificationConst($ProductStockDTO->getModification())
            ->findOneByTotalMax();


        return $this->render([
            'form' => $form->createView(),
            'name' => $ProductStockEvent->getNumber(),
            'order' => $ProductStockEvent->getOrder() !== null,
            'recommender' => $productStorage
            //'products' => $productDetail->fetchAllProductsByProductStocksAssociative($ProductStockEvent->getMain())
        ]);
    }
}
