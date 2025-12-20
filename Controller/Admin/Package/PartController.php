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

namespace BaksDev\Products\Stocks\Controller\Admin\Package;

use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Stocks\Entity\Stock\Event\Part\ProductStockPart;
use BaksDev\Products\Stocks\Messenger\Part\ProductStockPartMessage;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\AllProductStocksOrdersProductInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\ProductStocksOrdersProductResult;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\AllProductStocksOrdersPartInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksPart\ProductStocksOrdersPartResult;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionSelectedProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionSelectedProductStockForm;
use BaksDev\Products\Stocks\UseCase\Admin\Part\ProductStockPartDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Part\ProductStockPartHandler;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PART')]
final class PartController extends AbstractController
{
    /**
     * Сборочный лист по заказам
     */
    #[Route('/admin/product/stocks/part/{part}', name: 'admin.package.part', methods: ['GET', 'POST'])]
    public function part(
        Request $request,
        Environment $environment,
        AllProductStocksOrdersPartInterface $AllProductStocksOrdersPartRepository,
        AllProductStocksOrdersProductInterface $AllProductStocksOrdersProductRepository,
        ProductStocksEventInterface $ProductStocksEventRepository,
        ProductStockPartHandler $ProductStockPartHandler,
        BarcodeWrite $BarcodeWrite,
        MessageDispatchInterface $MessageDispatch,
        ?CentrifugoPublishInterface $publish = null,
        #[ParamConverter(ProductStockPartUid::class)] ?ProductStockPartUid $part = null,

    ): Response
    {

        /**
         * Получаем список заказов в партии
         */

        if($part instanceof ProductStockPartUid)
        {
            /** Получаем все заказы в сборочном листе */

            $products = $AllProductStocksOrdersPartRepository
                ->forProductStockPart($part)
                ->findAll();
        }

        /** Получаем список продукции из формы */

        else
        {

            $ExtraditionSelectedProductStockDTO = new ExtraditionSelectedProductStockDTO();

            $form = $this
                ->createForm(
                    type: ExtraditionSelectedProductStockForm::class,
                    data: $ExtraditionSelectedProductStockDTO,
                    options: ['action' => $this->generateUrl('products-stocks:admin.package.extradition-selected')],
                )
                ->handleRequest($request);

            /** Если НЕ указан список идентификаторов складской заявки */
            if(true === $ExtraditionSelectedProductStockDTO->getCollection()->isEmpty())
            {
                throw new InvalidArgumentException('Page Not Found');
            }


            /**
             * Получаем список продукции в складской заявке
             * + честные знаки
             * + место складирования продукции
             */

            $ids = $ExtraditionSelectedProductStockDTO
                ->getCollection()
                ->map(fn($element) => (string) $element->getId())->getValues();

            $products = $AllProductStocksOrdersProductRepository
                ->findAll($ids);

        }

        /**
         * Создаем партию выбранных
         */

        // Фильтр
        //        $filter = new ProductStockPackageFilterDTO();
        //
        //        $filterForm = $this
        //            ->createForm(
        //                type: ProductStockPackageFilterForm::class,
        //                data: $filter,
        //                options: ['action' => $this->generateUrl('products-stocks:admin.package.index')],
        //            );


        /** Если ни один способ не нашел продукцию - возвращаем пустой список */
        if(false === $products || false === $products->valid())
        {
            return $this->render(['query' => false], file: 'content.html.twig');
        }


        $parts = null;


        $call = $environment->getExtension(CallTwigFuncExtension::class);


        /** @var ProductStocksOrdersProductResult|ProductStocksOrdersPartResult $var */
        foreach($products as $result)
        {
            $ProductStockPartUid = $result->getIdentifierPart();

            /** Если продукция из заказов выбирается в первый раз - сохраняем сборочный лист */
            if(true === ($result instanceof ProductStocksOrdersProductResult) && $result->getProductStocksEvents())
            {
                foreach($result->getProductStocksEvents() as $event)
                {
                    /** Обновляем заявку на упаковку партией  */
                    $ProductStockPartDTO = new ProductStockPartDTO($event)
                        ->setValue($ProductStockPartUid);

                    $ProductStockPart = $ProductStockPartHandler->handle($ProductStockPartDTO);

                    if(false === ($ProductStockPart instanceof ProductStockPart))
                    {
                        $this->addFlash(
                            type: 'danger',
                            message: '%s: Ошибка печати сборочного листа',
                            domain: 'products-stocks',
                            arguments: $ProductStockPart,
                        );

                        continue 2;
                    }
                }


                //                foreach($result->getMains() as $main)
                //                {
                //
                //                }


                //                /**
                //                 * Обновляем продукция в заявке применяя партию
                //                 */

                /*foreach($result->getProductsCollection() as $ProductStockCollectionUid)
                {
                    $ProductStockPartDTO = new ProductStockPartDTO($ProductStockCollectionUid)
                        ->setValue($ProductStockPartUid);

                    $ProductStockProduct = $ProductStockPartHandler->handle($ProductStockPartDTO);

                    if(false === ($ProductStockProduct instanceof ProductStockProduct))
                    {
                        $this->addFlash(
                            type: 'danger',
                            message: '%s: Ошибка печати сборочного листа',
                            domain: 'products-stocks',
                            arguments: $ProductStockProduct,
                        );

                        return $this->redirectToReferer();
                    }

                }*/
            }

            $strOffer = '';

            /**
             * Множественный вариант
             */

            $variation = $call->call(
                $environment,
                $result->getProductVariationValue(),
                $result->getProductVariationReference().'_render',
            );

            $strOffer .= $variation ? ' '.trim($variation) : '';

            /**
             * Модификация множественного варианта
             */

            $modification = $call->call(
                $environment,
                $result->getProductModificationValue(),
                $result->getProductModificationReference().'_render',
            );

            $strOffer .= $modification ? trim($modification) : '';

            /**
             * Торговое предложение
             */

            $offer = $call->call(
                $environment,
                $result->getProductOfferValue(),
                $result->getProductOfferReference().'_render',
            );

            $strOffer .= $modification ? ' '.trim($offer) : '';
            $strOffer .= $result->getProductOfferPostfix() ? ' '.$result->getProductOfferPostfix() : '';
            $strOffer .= $result->getProductVariationPostfix() ? ' '.$result->getProductVariationPostfix() : '';
            $strOffer .= $result->getProductModificationPostfix() ? ' '.$result->getProductModificationPostfix() : '';

            /** Бросаем событие для получения стикеров маркировки и честных знаков */

            $ProductStockPartMessage = new ProductStockPartMessage(
                $ProductStockPartUid,
                $result->getProduct(),
                $result->getOfferConst(),
                $result->getVariationConst(),
                $result->getModificationConst(),
            );

            $ProductStockPartMessage->setOrders($result->getOrdersCollection());
            $MessageDispatch->dispatch(message: $ProductStockPartMessage);

            $parts[(string) $ProductStockPartUid] = $ProductStockPartMessage->getStickers();
            $parts[(string) $ProductStockPartUid]['name'] = $result->getProductName();
            $parts[(string) $ProductStockPartUid]['offer'] = $strOffer;
            $parts[(string) $ProductStockPartUid]['total'] = $result->getTotal();
            $parts[(string) $ProductStockPartUid]['stock'] = $result->getStocksQuantity();


            /** Скрываем идентификатор у всех пользователей */
            if($publish instanceof CentrifugoPublishInterface)
            {
                foreach($result->getMains() as $main)
                {
                    $publish
                        ->addData(['identifier' => $main])
                        ->send('remove');
                }
            }
        }

        return $this->render(
            [
                'query' => $parts,
                //'search' => $searchForm->createView(),
                //'filter' => $filterForm->createView(),
            ], file: 'content.html.twig',
        );
    }
}
