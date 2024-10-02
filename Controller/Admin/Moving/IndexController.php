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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterDTO;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin\ProductsStocksFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocksMove\AllProductStocksMoveInterface;
use chillerlan\QRCode\QRCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING')]
final class IndexController extends AbstractController
{
    /** Перемещения продукции на другой склад */
    #[Route('/admin/product/stocks/movings/{page<\d+>}', name: 'admin.moving.index', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        AllProductStocksMoveInterface $allMove,
        int $page = 0
    ): Response {
        //$ROLE_ADMIN = $this->isGranted('ROLE_ADMIN');

        // Поиск
        $search = new SearchDTO($request);
        $searchForm = $this->createForm(
            SearchForm::class,
            $search,
            ['action' => $this->generateUrl('products-stocks:admin.moving.index')]
        );
        $searchForm->handleRequest($request);

        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO($request);
        $filterForm = $this->createForm(ProductFilterForm::class, $filter, [
            'action' => $this->generateUrl('products-stocks:admin.moving.index'),
        ]);
        $filterForm->handleRequest($request);

        // Получаем список закупок ответственного лица
        $query = $allMove
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
