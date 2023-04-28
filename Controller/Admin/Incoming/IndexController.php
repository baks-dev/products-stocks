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

namespace BaksDev\Products\Stocks\Controller\Admin\Incoming;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Services\Security\RoleSecurity;
use BaksDev\Products\Stocks\Repository\AllProductStocksIncoming\AllProductStocksIncomingInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[RoleSecurity('ROLE_MATERIAL_STOCK_INCOMING')]
final class IndexController extends AbstractController
{
    // Список приходов
    #[Route('/admin/product/stocks/incoming/{page<\d+>}', name: 'admin.incoming.index', methods: ['GET', 'POST'])]
    public function incoming(
        Request $request,
        AllProductStocksIncomingInterface $allIncoming,
        int $page = 0
    ): Response {
        $ROLE_ADMIN = $this->isGranted('ROLE_ADMIN');

        // Поиск
        $search = new SearchDTO();
        $searchForm = $this->createForm(SearchForm::class, $search);
        $searchForm->handleRequest($request);

        // Фильтр
//        $filter = new StockFilterDTO($ROLE_ADMIN ? null : $this->getProfileUid(), $request);
//        $filterForm = $this->createForm(StockFilterForm::class, $filter);
//        $filterForm->handleRequest($request);

        // Получаем псисок приходов ответственного лица
        $query = $allIncoming->fetchAllProductStocksAssociative($search, $ROLE_ADMIN ? null : $this->getProfileUid());

        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
            ]
        );
    }
}
