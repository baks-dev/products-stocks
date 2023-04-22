<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Controller\Admin;

// use App\Module\Materials\Stock\Forms\StockFilter\User\StockFilterDTO;
// use App\Module\Materials\Stock\Forms\StockFilter\User\StockFilterForm;
// use App\Module\Materials\Stock\Repository\AllStocks\AllStockInterface;
//
// use App\System\Handler\Search\SearchDTO;
// use App\System\Handler\Search\SearchForm;
// use App\System\Helper\Paginator;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Services\Security\RoleSecurity;
use BaksDev\Products\Stocks\Repository\AllProductStocks\AllProductStocksInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Состояние склада ответственного лица

#[RoleSecurity('ROLE_PRODUCT_STOCK')]
final class StocksController extends AbstractController
{
    /** Состояние склада */
    #[Route('/admin/product/stocks/{page<\d+>}', name: 'admin.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksInterface $allProductStocks,
        int $page = 0
    ): Response {
        $ROLE_ADMIN = $this->isGranted('ROLE_ADMIN');

        // Поиск
        $search = new SearchDTO();
        $searchForm = $this->createForm(SearchForm::class, $search);
        $searchForm->handleRequest($request);
//
//        // Фильтр
//        $filter = new StockFilterDTO($ROLE_ADMIN ? null : $this->getProfileUid(), $request);
//        $filterForm = $this->createForm(StockFilterForm::class, $filter);
//        $filterForm->handleRequest($request);
//
//        if ($ROLE_ADMIN) {
//            $stmt = $allStock->get($search, $filter);
//        } else {
//            // Получаем состояние склада ответственного лица
//            $stmt = $allStock->getByProfile($search, $filter, $this->getProfileUid());
//        }
//
//        $query = new Paginator($page, $stmt, $request);

        $query = $allProductStocks->fetchAllProductStocksAssociative($search, $ROLE_ADMIN ? null : $this->getProfileUid());

        dump( current(($query->getData())));

        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                // 'filter' => $filterForm->createView(),
            ]
        );
    }
}
