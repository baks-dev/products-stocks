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

namespace BaksDev\Products\Stocks\Controller\Admin\Moving;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Stocks\Forms\MoveFilter\Admin\ProductStockMoveFilterDTO;
use BaksDev\Products\Stocks\Forms\MoveFilter\Admin\ProductStockMoveFilterForm;
use BaksDev\Products\Stocks\Repository\AllProductStocks\AllProductStocksResult;
use BaksDev\Products\Stocks\Repository\AllProductStocksMove\AllProductStocksMoveInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksMove\AllProductStocksMoveResult;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;


#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_MOVING')]
final class ReportController extends AbstractController
{
    /**
     * Печать информации о перемещениях
     */
    #[Route('/admin/products/stocks/report', name: 'admin.moving.report', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksMoveInterface $allMove,
        Environment $environment
    ): Response
    {
        // Поиск
        $search = new SearchDTO();

        $this
            ->createForm(
                type: SearchForm::class,
                data: $search,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);


        /**
         * Фильтр продукции по ТП
         */
        $productFilter = new ProductFilterDTO()->hiddenMaterials();

        $this
            ->createForm(
                type: ProductFilterForm::class,
                data: $productFilter,
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);


        // Фильтр
        $this
            ->createForm(
                type: ProductStockMoveFilterForm::class,
                data: $moveFilter = new ProductStockMoveFilterDTO(),
                options: ['action' => $this->generateUrl('products-stocks:admin.moving.index')],
            )
            ->handleRequest($request);

        $result = $allMove
            ->search($search)
            ->productFilter($productFilter)
            ->filter($moveFilter)
            ->setLimit(1000)
            ->findPaginator()
            ->getData();

        if(empty($result))
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
            ->setCellValue('A1', 'Номер заявки')
            ->setCellValue('B1', 'Артикул')
            ->setCellValue('C1', 'Наименование')
            ->setCellValue('D1', 'Торговое предложение')
            ->setCellValue('E1', 'Количество')
            ->setCellValue('F1', 'Наличие')
            ->setCellValue('G1', 'Место')
            ->setCellValue('H1', 'Склад отгрузки')
            ->setCellValue('I1', 'Склад назначения');

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setAutoSize(true);
        $sheet->getColumnDimension('I')->setAutoSize(true);

        $key = 2;


        /** @var AllProductStocksMoveResult $data */
        foreach($result as $data)
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

            $strOffer .= $data->getProductOfferPostfix() ? ' '.$data->getProductOfferPostfix() : '';
            $strOffer .= $data->getProductVariationPostfix() ? ' '.$data->getProductVariationPostfix() : '';
            $strOffer .= $data->getProductModificationPostfix() ? ' '.$data->getProductModificationPostfix() : '';


            $sheet->setCellValue('A'.$key, $data->getNumber());
            $sheet->setCellValue('B'.$key, trim($data->getProductArticle()));
            $sheet->setCellValue('C'.$key, $data->getProductName()); // Наименование товара
            $sheet->setCellValue('D'.$key, $strOffer);
            $sheet->setCellValue('E'.$key, $data->getTotal());
            $sheet->setCellValue('F'.$key, $data->getStockTotal());
            $sheet->setCellValue('G'.$key, $data->getStockStorage());
            $sheet->setCellValue('H'.$key, $data->getUsersProfileUsername());
            $sheet->setCellValue('I'.$key, $data->getUsersProfileDestination());

            $key++;
        }


        /**
         * Отдаем результат для скачивания
         */
        /** @var AllProductStocksResult $data */
        $filename = 'Отчет о перемещениях.xlsx';

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);

        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}