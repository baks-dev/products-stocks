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

namespace BaksDev\Products\Stocks\Controller\Admin\Package;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Forms\PackageFilter\Admin\ProductStockPackageFilterDTO;
use BaksDev\Products\Stocks\Forms\PackageFilter\Admin\ProductStockPackageFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocksPackage\AllProductStocksPackageInterface;
use DateInterval;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

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
        $search = new SearchDTO($request);
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


        /** Если ajax (печать) - показываем только свой склад */
        if($request->isXmlHttpRequest())
        {
            $allPackage->setLimit(1000);
        }

        // Получаем список заявок на упаковку
        $query = $allPackage
            ->search($search)
            ->filter($filter)
            ->findPaginator($this->getProfileUid());


        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
            ]
        );
    }
}
