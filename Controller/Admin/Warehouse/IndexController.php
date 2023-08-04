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

namespace BaksDev\Products\Stocks\Controller\Admin\Warehouse;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterDTO;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocksWarehouse\AllProductStocksWarehouseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_WAREHOUSE')]
final class IndexController extends AbstractController
{
    /**
     * Поступления на склад
     */
    #[Route('/admin/product/stocks/warehouse/{page<\d+>}', name: 'admin.warehouse.index', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        AllProductStocksWarehouseInterface $allPurchase,
        int $page = 0
    ): Response {
        $ROLE_ADMIN = $this->isGranted('ROLE_ADMIN');

        // Поиск
        $search = new SearchDTO();
        $searchForm = $this->createForm(SearchForm::class, $search);
        $searchForm->handleRequest($request);

        // Фильтр
        $filter = new ProductsStocksFilterDTO($request, $ROLE_ADMIN ? null : $this->getProfileUid());
        $filterForm = $this->createForm(ProductsStocksFilterForm::class, $filter);
        $filterForm->handleRequest($request);

        /* Получаем список поступлений на склад */
        $query = $allPurchase->fetchAllProductStocksAssociative($search, $filter, $ROLE_ADMIN ? null : $this->getProfileUid());

        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
            ]
        );
    }
}