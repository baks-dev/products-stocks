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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving;

use BaksDev\Centrifugo\Services\Token\TokenUserGenerator;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Forms\MoveFilter\Admin\ProductStockMoveFilterDTO;
use BaksDev\Products\Stocks\Forms\MoveFilter\Admin\ProductStockMoveFilterForm;
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
        TokenUserGenerator $tokenUserGenerator,
        int $page = 0
    ): Response
    {
        // Поиск
        $search = new SearchDTO();

        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $search,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);

        /**
         * Фильтр продукции по ТП
         */
        $productFilter = new ProductFilterDTO()
            ->hiddenMaterials();

        $productFilterForm = $this
            ->createForm(
                type: ProductFilterForm::class,
                data: $productFilter,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);


        // Фильтр
        $moveFilter = new ProductStockMoveFilterDTO();

        $filterForm = $this
            ->createForm(
                type: ProductStockMoveFilterForm::class,
                data: $moveFilter,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);

        // Получаем список закупок ответственного лица
        $query = $allMove
            ->search($search)
            ->productFilter($productFilter)
            ->filter($moveFilter)
            ->findPaginator($this->getProfileUid());


        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                'product_filter' => $productFilterForm->createView(),
                'move_filter' => $filterForm->createView(),
                'token' => $tokenUserGenerator->generate($this->getUsr()),
                'current_profile' => $this->getCurrentProfileUid(),
            ],
        );
    }
}
