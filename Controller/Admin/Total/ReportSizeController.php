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

namespace BaksDev\Products\Stocks\Controller\Admin\Total;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocks\AllProductStocksResult;
use BaksDev\Products\Stocks\Repository\AllProductStocksSizeStocks\AllProductStocksSizeInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksSizeStocks\AllProductStocksSizeResult;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceRepository;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_REPORT')]
final class ReportSizeController extends AbstractController
{
    #[Route('/admin/products/stock/size/export', name: 'admin.total.size.export', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksSizeInterface $AllProductStocksSize,
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

        $results = $AllProductStocksSize
            ->filter($filter)
            ->findAll();

        if(empty($results) || false === $results->valid())
        {
            return $this->redirectToReferer();
        }

        // Создаем новый объект Spreadsheet
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);
        // Получаем текущий активный лист
        $sheet = $spreadsheet->getActiveSheet();

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        // Запись заголовков
        $sheet
            ->setCellValue('A1', 'Торговое предложение')
            ->setCellValue('B1', 'Наличие')
            ->setCellValue('C1', 'Резерв')
            ->setCellValue('D1', 'Доступно');


        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);

        $key = 2;

        $allTotal = 0;
        $allReserve = 0;
        $allQuantity = 0;


        /** @var AllProductStocksSizeResult $data */

        foreach($results as $data)
        {


            $strOffer = '';

            /**
             * Множественный вариант
             */

            $variation = $call->call(
                $environment,
                $data->getProductVariationValue(),
                $data->getProductVariationReference().'_render',
            );

            $strOffer .= $variation ? ' '.trim($variation) : null;


            /**
             * Модификация множественного варианта
             */

            $modification = $call->call(
                $environment,
                $data->getProductModificationValue(),
                $data->getProductModificationReference().'_render',
            );

            $strOffer .= $modification ? ' '.trim($modification) : null;

            /**
             * Торговое предложение
             */

            $offer = $call->call(
                $environment,
                $data->getProductOfferValue(),
                $data->getProductOfferReference().'_render',
            );

            $strOffer .= $offer ? ' '.trim($offer) : null;


            /**
             * Информация о стоимости и остатках
             */

            $sheet->setCellValue('A'.$key, str_replace(' /', '/', $strOffer)); // Торговое предложение
            $sheet->setCellValue('B'.$key, $data->getStockTotal()); // Наличие
            $sheet->setCellValue('C'.$key, $data->getStockReserve()); // Резерв
            $sheet->setCellValue('D'.$key, $data->getQuantity()); // Доступно


            /** Подсчет ИТОГО */
            $allTotal += $data->getStockTotal();
            $allReserve += $data->getStockReserve();
            $allQuantity += $data->getQuantity();

            $key++;
        }


        /** Общее количество продукции */

        $sheet->setCellValue('A'.$key, 'Итого:');
        $sheet->setCellValue('B'.$key, $allTotal); // Наличие
        $sheet->setCellValue('C'.$key, $allReserve); // Резерв
        $sheet->setCellValue('D'.$key, $allQuantity); // Доступно


        /**
         * Отдаем результат для скачивания
         */

        /** @var AllProductStocksResult $data */
        $filename = 'size.xlsx';

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