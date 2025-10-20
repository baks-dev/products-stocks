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

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Repository\CurrentProductEvent\CurrentProductEventInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstResult;
use BaksDev\Products\Sign\UseCase\Admin\Decommission\DecommissionProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\Decommission\DecommissionProductSignHandler;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\NewDecommissionOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\NewDecommissionOrderForm;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\NewDecommissionOrderHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Products\NewDecommissionOrderProductDTO;
use BaksDev\Users\User\Type\Id\UserUid;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_ORDERS_STATUS_DECOMMISSION')]
final class DecommissionController extends AbstractController
{
    #[Route('/admin/order/decommission/{id}', name: 'admin.total.decommission', methods: ['GET', 'POST'])]
    public function news(
        Request $request,
        #[MapEntity] ProductStockTotal $productStocksTotal,
        NewDecommissionOrderHandler $NewDecommissionOrderHandler,
        ProductDetailByConstInterface $productDetailByConst,
        DecommissionProductSignHandler $DecommissionProductSignHandler,
    ): Response
    {
        $UserUid = $this->getUsr()?->getId();

        if(false === ($UserUid instanceof UserUid))
        {
            throw new InvalidArgumentException('Invalid Argument User');
        }

        /** Получаем идентификаторы события продукта для заказа */

        $ProductDetailByConstResult = $productDetailByConst
            ->product($productStocksTotal->getProduct())
            ->offerConst($productStocksTotal->getOffer())
            ->variationConst($productStocksTotal->getVariation())
            ->modificationConst($productStocksTotal->getModification())
            ->findResult();

        if(false === ($ProductDetailByConstResult instanceof ProductDetailByConstResult))
        {
            return $this->redirectToReferer();
        }

        $NewDecommissionOrderDTO = new NewDecommissionOrderDTO()
            ->setStorage($productStocksTotal->getId());

        /** Добавляем продукт в коллекцию заказа */

        $productDTO = new NewDecommissionOrderProductDTO()
            ->setProduct($ProductDetailByConstResult->getEvent())
            ->setOffer($ProductDetailByConstResult->getProductOfferUid())
            ->setVariation($ProductDetailByConstResult->getProductVariationUid())
            ->setModification($ProductDetailByConstResult->getProductModificationUid());

        $NewDecommissionOrderDTO->addProduct($productDTO);

        /** Присваиваем профиль пользователя в качестве целевого склада */
        $NewDecommissionOrderDTO
            ->getInvariable()
            ->setProfile($this->getProfileUid())
            ->setUsr($UserUid);


        $form = $this
            ->createForm(
                type: NewDecommissionOrderForm::class,
                data: $NewDecommissionOrderDTO,
                options: ['action' => $this->generateUrl(
                    'products-stocks:admin.total.decommission',
                    ['id' => (string) $productStocksTotal],
                )],
            )
            ->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('new_decommission_order'))
        {
            $this->refreshTokenForm($form);

            /** Сохраняем заказ со статусом Decommission «Списание» */
            $handle = $NewDecommissionOrderHandler->handle($NewDecommissionOrderDTO);

            $this->addFlash(
                'page.new',
                ($handle instanceof Order) ? 'success.new' : 'danger.new',
                'orders-order.admin',
                ($handle instanceof Order) ? $NewDecommissionOrderDTO->getInvariable()->getNumber() : $handle,
            );

            /**
             * Если выбрано списание честных знаков - отправляем на поиск и списание честных знаков
             */

            if(true === ($handle instanceof Order) && true === $NewDecommissionOrderDTO->isSigns())
            {
                $NewDecommissionOrderProductDTO = $NewDecommissionOrderDTO->getProduct()->current();

                $DecommissionProductSignHandler->handle(new DecommissionProductSignDTO()
                    ->setUsr($UserUid)
                    ->setProfile($this->getProfileUid())
                    ->setTotal($NewDecommissionOrderProductDTO->getPrice()->getTotal())
                    ->setOffer($ProductDetailByConstResult->getProductOfferConst())
                    ->setVariation($ProductDetailByConstResult->getProductVariationConst())
                    ->setModification($ProductDetailByConstResult->getProductModificationConst())
                    ->setProduct($ProductDetailByConstResult->getId())
                    ->setSeller($this->getProfileUid())
                    ->setOrd($handle->getId()),
                );
            }

            /** Создаем списание с указанного места */


            return ($handle instanceof Order) ? $this->redirectToRoute('products-stocks:admin.total.index') : $this->redirectToReferer();
        }

        return $this->render([
            'form' => $form->createView(),
            'card' => $ProductDetailByConstResult,
            'total' => $productStocksTotal->getTotal() - $productStocksTotal->getReserve(),
        ]);
    }
}