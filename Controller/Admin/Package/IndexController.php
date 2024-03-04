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
final class IndexController extends AbstractController
{
    /**
     * Заказы на сборке
     */
    #[Route('/admin/product/stocks/package/{page<\d+>}', name: 'admin.package.index', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        AllProductStocksPackageInterface $allPackage,
        int $page = 0
    ): Response
    {

        // Поиск
        $search = new SearchDTO();
        $searchForm = $this->createForm(SearchForm::class, $search,
            ['action' => $this->generateUrl('products-stocks:admin.package.index')]
        );
        $searchForm->handleRequest($request);



        // Фильтр
        $filter = new ProductStockPackageFilterDTO($request);
        $filterForm = $this->createForm(ProductStockPackageFilterForm::class, $filter);
        $filterForm->handleRequest($request);

        if($filterForm->isSubmitted())
        {
            if($filterForm->get('back')->isClicked())
            {
                $filter->setDate($filter->getDate()?->sub(new DateInterval('P1D')));
                return $this->redirectToReferer();
            }

            if($filterForm->get('next')->isClicked())
            {
                $filter->setDate($filter->getDate()?->add(new DateInterval('P1D')));
                return $this->redirectToReferer();
            }
        }

        // Получаем список заявок на упаковку
        $query = $allPackage
            ->search($search)
            ->filter($filter)
            ->fetchAllProductStocksAssociative($this->getProfileUid());

        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
            ]
        );
    }
}
