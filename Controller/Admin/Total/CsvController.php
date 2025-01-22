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

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocks\AllProductStocksInterface;
use BaksDev\Reference\Money\Type\Money;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INDEX')]
final class CsvController extends AbstractController
{
    /**
     * Печать остатков всего склада
     */
    #[Route('/admin/product/stock/csv', name: 'admin.total.csv', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksInterface $allProductStocks,
        Environment $environment
    ): Response
    {
        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO($request);
        $filterForm = $this->createForm(ProductFilterForm::class, $filter);
        $filterForm->handleRequest($request);

        $filter->setAll(false);

        $query = $allProductStocks
            ->filter($filter)
            ->setLimit(1000)
            ->findPaginator(
                $this->getUsr()?->getId(),
                $this->getProfileUid()
            );

        if(empty($query->getData()))
        {
            return $this->redirectToReferer();
        }

        $result = $query->getData();

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        $response = new StreamedResponse(function() use ($call, $result, $environment) {

            $handle = fopen('php://output', 'w+');

            // Запись заголовков
            fputcsv($handle, ['Артикул', 'Наименование', 'Стоимость', 'Наличие', 'Резерв', 'Доступно', 'Сумма', 'Место']);

            $allTotal = 0;
            $allPrice = 0;

            // Запись данных
            foreach($result as $data)
            {
                $name = $data['product_name'];

                $variation = $call->call($environment, $data['product_variation_value'], $data['product_variation_reference'].'_render');
                $name .= $variation ? ' '.$variation : null;

                $modification = $call->call($environment, $data['product_modification_value'], $data['product_modification_reference'].'_render');
                $name .= $modification ?: null;

                $offer = $call->call($environment, $data['product_offer_value'], $data['product_offer_reference'].'_render');
                $name .= $offer ? ' '.$offer : null;

                $name .= $data['product_offer_postfix'] ? ' '.$data['product_offer_postfix'] : null;
                $name .= $data['product_variation_postfix'] ? ' '.$data['product_variation_postfix'] : null;
                $name .= $data['product_modification_postfix'] ? ' '.$data['product_modification_postfix'] : null;

                $Money = new Money($data['product_price'], true);
                $quantity = ($data['stock_total'] - $data['stock_reserve']);
                $total_price = ($Money->getValue() * $data['stock_total']);

                $allTotal += $data['stock_total'];
                $allPrice += $total_price;

                fputcsv($handle, [
                    $data['product_article'],
                    $name,
                    $Money->getValue(),
                    $data['stock_total'],
                    $data['stock_reserve'],
                    $quantity,
                    $total_price,
                    '. '.$data['stock_storage']
                ]);
            }

            /** Общее количество продукции и общая стоимость */
            fputcsv($handle, [
                '',
                '',
                '',
                $allTotal,
                '',
                '',
                $allPrice,
                ''
            ]);

            fclose($handle);
        });

        $filename = current($result)['users_profile_username'].'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;

    }
}
