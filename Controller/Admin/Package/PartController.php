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

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Forms\PackageFilter\Admin\ProductStockPackageFilterDTO;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersPart\AllProductStocksOrdersPartInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct\AllProductStocksOrdersProductInterface;
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

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PART')]
final class PartController extends AbstractController
{
    /**
     * Сборочный лист по заказам
     */
    #[Route('/admin/product/stocks/part/{part}', name: 'admin.part', methods: ['GET', 'POST'])]
    public function part(
        Request $request,
        AllProductStocksOrdersPartInterface $AllProductStocksOrdersPartRepository,
        AllProductStocksOrdersProductInterface $AllProductStocksOrdersProductRepository,
        ProductStocksEventInterface $ProductStocksEventRepository,
        ProductStockPartHandler $ProductStockPartHandler,
        #[ParamConverter(ProductStockPartUid::class)] ?ProductStockPartUid $part = null,
    ): Response
    {

        /**
         * Получаем список заказов в партии
         */

        if($part instanceof ProductStockPartUid)
        {
            return new Response((string) $part);
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


        $ExtraditionSelectedProductStockDTO = new ExtraditionSelectedProductStockDTO();

        $form = $this
            ->createForm(
                type: ExtraditionSelectedProductStockForm::class,
                data: $ExtraditionSelectedProductStockDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.package.extradition-selected')],
            )
            ->handleRequest($request);

        if(true === $ExtraditionSelectedProductStockDTO->getCollection()->isEmpty())
        {
            throw new InvalidArgumentException('Page Not Found');
        }


        /** Генерируем идентификатор партии заказов */

        $ProductStockPartUid = new ProductStockPartUid();

        foreach($ExtraditionSelectedProductStockDTO->getCollection() as $ExtraditionProductStockDTO)
        {

            $ProductStockEvent = $ProductStocksEventRepository
                ->forEvent($ExtraditionProductStockDTO->getEvent())
                ->find();

            if(false === ($ProductStockEvent instanceof ProductStockEvent))
            {
                $ExtraditionSelectedProductStockDTO->removeCollection($ExtraditionProductStockDTO);

                continue;
            }

            if($ProductStockEvent->isProductStockPart())
            {
                /* TODO: комментарий !!! */
                //$ExtraditionSelectedProductStockDTO->removeCollection($ExtraditionProductStockDTO);

                continue;
            }


            $ProductStockPartDTO = new ProductStockPartDTO($ProductStockEvent)
                ->setValue($ProductStockPartUid);

            $ProductStockPartHandler->handle($ProductStockPartDTO);


            //dump($ExtraditionProductStockDTO);  /* TODO: удалить !!! */
        }

        if(true === $ExtraditionSelectedProductStockDTO->getCollection()->isEmpty())
        {
            throw new InvalidArgumentException('Page Not Found');
        }

        //return $this->redirectToReferer();

        dd($ExtraditionSelectedProductStockDTO->getCollection()); /* TODO: удалить !!! */

        /** Получаем список всех заказов на упаковке согласно фильтру */

        dd($ExtraditionSelectedProductStockDTO); /* TODO: удалить !!! */

        return new Response('Page Not Found');

        // Поиск
        $search = new SearchDTO();
        //        $searchForm = $this->createForm(SearchForm::class, $search,
        //            ['action' => $this->generateUrl('products-stocks:admin.package.index')]
        //        );
        //$searchForm->handleRequest($request);
        //$searchForm->createView();


        // Фильтр
        $filter = new ProductStockPackageFilterDTO();
        //$filterForm = $this->createForm(ProductStockPackageFilterForm::class, $filter);
        //$filterForm->handleRequest($request);
        //$filterForm->createView();


        // Получаем список заявок на упаковку
        $query = $allPackage
            ->setLimit(1000)
            ->search($search)
            ->filter($filter)
            ->findAllProducts($this->getProfileUid());


        return $this->render(
            [
                'query' => $query,
                //'search' => $searchForm->createView(),
                //'filter' => $filterForm->createView(),
            ], file: 'content.html.twig',
        );
    }
}
