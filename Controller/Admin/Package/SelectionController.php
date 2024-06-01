<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\Controller\Admin\Package;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Forms\PackageFilter\Admin\ProductStockPackageFilterDTO;
use BaksDev\Products\Stocks\Forms\PackageFilter\Admin\ProductStockPackageFilterForm;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterDTO;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageInterface;
use DateInterval;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_PACKAGE')]
final class SelectionController extends AbstractController
{
    /**
     * Заказы на сборке
     */
    #[Route('/admin/product/stocks/selection', name: 'admin.package.selection', methods: ['GET'])]
    public function incoming(
        Request $request,
        AllProductStocksPackageInterface $allPackage,
        int $page = 0
    ): Response
    {

        // Поиск
        $search = new SearchDTO($request);
//        $searchForm = $this->createForm(SearchForm::class, $search,
//            ['action' => $this->generateUrl('products-stocks:admin.package.index')]
//        );
        //$searchForm->handleRequest($request);
        //$searchForm->createView();


        // Фильтр
        $filter = new ProductStockPackageFilterDTO($request);
        //$filterForm = $this->createForm(ProductStockPackageFilterForm::class, $filter);
        //$filterForm->handleRequest($request);
        //$filterForm->createView();


        // Получаем список заявок на упаковку
        $query = $allPackage
            ->setLimit(1000)
            ->search($search)
            ->filter($filter)
            ->findAllProducts($this->getProfileUid());

        //dump($query);
        //dd($filter);
        //dd($query);

        return $this->render(
            [
                'query' => $query,
                //'search' => $searchForm->createView(),
                //'filter' => $filterForm->createView(),
            ], file: 'content.html.twig'
        );
    }
}
