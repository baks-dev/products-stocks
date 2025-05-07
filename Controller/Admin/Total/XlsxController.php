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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocks\AllProductStocksInterface;
use BaksDev\Reference\Money\Type\Money;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INDEX')]
final class XlsxController extends AbstractController
{
    /**
     * Печать остатков всего склада
     */
    #[Route('/admin/product/stock/xlsx', name: 'admin.total.xlsx', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksInterface $allProductStocks,
        Environment $environment
    ): Response
    {
        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO();
        $filterForm = $this->createForm(ProductFilterForm::class, $filter);
        $filterForm->handleRequest($request);

        $filter->setAll(false);

        $query = $allProductStocks
            ->filter($filter)
            ->setLimit(100000)
            ->findPaginator();

        if(empty($query->getData()))
        {
            return $this->redirectToReferer();
        }

        $result = $query->getData();

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        // Создаем новый объект Spreadsheet
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);

        // Получаем текущий активный лист
        $sheet = $spreadsheet->getActiveSheet();

        // Запись заголовков
        $sheet->setCellValue('A1', 'Наименование');
        $sheet->setCellValue('B1', 'Артикул');
        $sheet->setCellValue('C1', 'Стоимость');
        $sheet->setCellValue('D1', 'Наличие');
        $sheet->setCellValue('E1', 'Резерв');
        $sheet->setCellValue('F1', 'Доступно');
        $sheet->setCellValue('G1', 'Сумма');
        $sheet->setCellValue('H1', 'Место');

        $allTotal = 0;
        $allPrice = 0;
        $key = 2;

        // Запись данных
        foreach($result as $data)
        {
            $name = trim($data['product_name']);

            $variation = $call->call(
                $environment,
                $data['product_variation_value'],
                $data['product_variation_reference'].'_render'
            );
            $name .= $variation ? ' '.trim($variation) : '';

            $modification = $call->call(
                $environment,
                $data['product_modification_value'],
                $data['product_modification_reference'].'_render'
            );
            $name .= $modification ? trim($modification) : '';

            $offer = $call->call(
                $environment,
                $data['product_offer_value'],
                $data['product_offer_reference'].'_render'
            );
            $name .= $offer ? ' '.trim($offer) : '';

            $name .= $data['product_offer_postfix'] ? ' '.$data['product_offer_postfix'] : '';
            $name .= $data['product_variation_postfix'] ? ' '.$data['product_variation_postfix'] : '';
            $name .= $data['product_modification_postfix'] ? ' '.$data['product_modification_postfix'] : '';

            $Money = new Money($data['product_price'], true);

            $quantity = ($data['stock_total'] - $data['stock_reserve']);
            $total_price = ($Money->getValue() * $data['stock_total']);

            $sheet->setCellValue('A'.$key, $name);
            $sheet->setCellValue('B'.$key, $data['product_article']);
            $sheet->setCellValue('C'.$key, $Money->getValue());
            $sheet->setCellValue('D'.$key, $data['stock_total']);
            $sheet->setCellValue('E'.$key, $data['stock_reserve']);
            $sheet->setCellValue('F'.$key, $quantity);
            $sheet->setCellValue('G'.$key, $total_price);
            $sheet->setCellValue('H'.$key, $data['stock_storage']);

            $allTotal += $data['stock_total'];
            $allPrice += $total_price;
            $key++;
        }

        /** Итого */
        $sheet->setCellValue('A'.$key, "Итого");
        $sheet->setCellValue('D'.$key, $allTotal);
        $sheet->setCellValue('G'.$key, $allPrice);

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);

        $filename = current($result)['users_profile_username'].'.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
