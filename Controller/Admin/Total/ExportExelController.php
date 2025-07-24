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
final class ExportExelController extends AbstractController
{
    /**
     * Печать остатков всего склада
     */
    #[Route('/admin/product/stock/export', name: 'admin.total.export', methods: ['GET', 'POST'])]
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


        // Создаем новый объект Spreadsheet
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);
        // Получаем текущий активный лист
        $sheet = $spreadsheet->getActiveSheet();

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        // Запись заголовков
        $sheet
            ->setCellValue('A1', 'Артикул')
            ->setCellValue('B1', 'Наименование')
            ->setCellValue('C1', 'Стоимость')
            ->setCellValue('D1', 'Наличие')
            ->setCellValue('E1', 'Резерв')
            ->setCellValue('F1', 'Доступно')
            ->setCellValue('G1', 'Сумма')
            ->setCellValue('H1', 'Место');


        $sheet->getColumnDimension('A')->setAutoSize(25);
        $sheet->getColumnDimension('B')->setAutoSize(50);
        $sheet->getColumnDimension('C')->setAutoSize(10);
        $sheet->getColumnDimension('D')->setAutoSize(10);
        $sheet->getColumnDimension('E')->setAutoSize(10);
        $sheet->getColumnDimension('F')->setAutoSize(10);
        $sheet->getColumnDimension('G')->setAutoSize(10);
        $sheet->getColumnDimension('H')->setAutoSize(10);

        $key = 2;

        $allTotal = 0;
        $allPrice = 0;

        foreach($result as $data)
        {
            $name = $data['product_name'];

            $variation = $call->call($environment, $data['product_variation_value'], $data['product_variation_reference'].'_render');
            $name .= $variation ? ' '.trim($variation) : null;

            $modification = $call->call($environment, $data['product_modification_value'], $data['product_modification_reference'].'_render');
            $name .= $modification ? trim($modification) : null;

            $offer = $call->call($environment, $data['product_offer_value'], $data['product_offer_reference'].'_render');
            $name .= $offer ? ' '.trim($offer) : null;

            $name .= $data['product_offer_postfix'] ? ' '.trim($data['product_offer_postfix']) : null;
            $name .= $data['product_variation_postfix'] ? ' '.trim($data['product_variation_postfix']) : null;
            $name .= $data['product_modification_postfix'] ? ' '.trim($data['product_modification_postfix']) : null;

            $Money = new Money($data['product_price'], true);
            $quantity = ($data['stock_total'] - $data['stock_reserve']);
            $total_price = ($Money->getValue() * $data['stock_total']);


            $sheet->setCellValue('A'.$key, trim($data['product_article'])); // Артикул
            $sheet->setCellValue('B'.$key, trim($name)); // Наименование товара
            $sheet->setCellValue('C'.$key, $Money->getValue()); // Стоимость
            $sheet->setCellValue('D'.$key, $data['stock_total']); // Наличие
            $sheet->setCellValue('E'.$key, $data['stock_reserve']); // Резерв
            $sheet->setCellValue('F'.$key, $quantity); // Доступно
            $sheet->setCellValue('G'.$key, $total_price); // Сумма
            $sheet->setCellValue('H'.$key, $data['stock_storage']); // Место


            /** Подсчет ИТОГО */
            $allTotal += $data['stock_total'];
            $allPrice += $total_price;

            $key++;
        }


        /** Общее количество продукции и общая стоимость */

        $sheet->setCellValue('A'.$key, 'Итого:');
        $sheet->setCellValue('D'.$key, $allTotal); // Наличие
        $sheet->setCellValue('G'.$key, $allPrice); // Сумма


        /**
         * Отдаем результат для скачивания
         */

        $filename = current($result)['users_profile_username'].'.xlsx';

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);


        // Redirect output to a client’s web browser (Xls)
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;

    }
}
