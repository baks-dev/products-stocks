<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Controller\Admin\Quantity;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\ProductStockSettingsByProfile\ProductStockSettingsByProfileInterface;
use BaksDev\Products\Stocks\Repository\Quantity\AllProductStocks\AllProductStocksInterface;
use BaksDev\Products\Stocks\Repository\Quantity\ProductStockInfo\ProductStockInfoInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INDEX')]
final class IndexController extends AbstractController
{
    /**
     * Состояние склада ответственного лица
     */
    #[Route('/admin/product/stocks/quantity/{page<\d+>}', name: 'admin.quantity.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksInterface $AllProductStocksRepository,
        ProductStockInfoInterface $ProductStockInfoRepository,
        ProductStockSettingsByProfileInterface $ProductStockSettingsByProfileRepository,
        int $page = 0,
    ): Response
    {
        // Поиск
        $searchDTO = new SearchDTO();

        /** Задать теги для использования в поиске */
        $searchDTO->setSearchTags(['products-product']);

        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $searchDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.quantity.index')],
            )
            ->handleRequest($request);

        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO()
            ->allVisible()
            ->hiddenMaterials();

        $filterForm = $this
            ->createForm(
                ProductFilterForm::class,
                $filter,
                ['action' => $this->generateUrl('products-stocks:admin.quantity.index'),],
            )
            ->handleRequest($request);

        /** Если ajax (печать) - показываем только свой склад */
        if($request->isXmlHttpRequest())
        {
            $filter->setAll(false);
            $AllProductStocksRepository->setLimit(1000);
        }

        $query = $AllProductStocksRepository
            ->search($searchDTO)
            ->filter($filter)
            ->findPaginator();


        /* Инфо-блок с предложением переместить остатки по товару */
        $info = $ProductStockInfoRepository
            ->forCategory($filter->getCategory())
            ->find();

        $stockSettings = $ProductStockSettingsByProfileRepository
            ->profile($this->getProfileUid())
            ->find();

        return $this->render(
            [
                'query' => $query,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
                'info' => $info,
                'stock_settings' => $stockSettings
            ],
        );
    }
}