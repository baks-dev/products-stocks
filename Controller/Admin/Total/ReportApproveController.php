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
use BaksDev\Products\Stocks\Repository\ProductStocksApproveReport\ProductStocksApproveReportInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksApproveReport\ProductStocksApproveReportResult;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\ProductStockSettingsByProfileInterface;
use BaksDev\Products\Stocks\Repository\ProductStockSettings\ProductStockSettingsByProfileResult;
use Generator;
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
final class ReportApproveController extends AbstractController
{
    /**
     * Печатать остатки склада, которые требуют подтверждения
     */
    #[Route('/admin/products/stock/approve/export', name: 'admin.total.approve.export', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProductStocksApproveReportInterface $productStocksApproveReport,
        ProductStockSettingsByProfileInterface $productStockSettingsByProfile,
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

        /* Настройки порога остатков */
        $stockSettings = $productStockSettingsByProfile
            ->profile($this->getProfileUid())
            ->find();

        $threshold = true === ($stockSettings instanceof ProductStockSettingsByProfileResult) ?
            $stockSettings->getThreshold() :
            false;

        if(false === $threshold)
        {
            $this->addFlash(
                'page.settings',
                'settings.empty',
                'products-stocks.admin',
            );

            return $this->redirectToReferer();
        }


        /** @var Generator<int, ProductStocksApproveReportResult> $result */
        $result = $productStocksApproveReport
            ->filter($filter)
            ->threshold($threshold)
            ->findAll();

        if(false === $result)
        {
            $this->addFlash(
                'page.report',
                'report.info',
                'products-stocks.admin',
            );

            return $this->redirectToReferer();
        }


        /* Создать новый объект Spreadsheet */
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);

        /* Получить текущий активный лист */
        $sheet = $spreadsheet->getActiveSheet();

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        /* Задать заголовки */
        $sheet
            ->setCellValue('A1', 'Обновление товара')
            ->setCellValue('B1', 'Артикул')
            ->setCellValue('C1', 'Наименование')
            ->setCellValue('D1', 'Торговое предложение')
            ->setCellValue('E1', 'Стоимость')
            ->setCellValue('F1', 'Наличие')
            ->setCellValue('G1', 'Резерв')
            ->setCellValue('H1', 'Место');

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setAutoSize(true);


        /* Задать данные */

        $key = 2;

        /** @var ProductStocksApproveReportResult $data */
        foreach($result as $data)
        {

            $strOffer = '';

            /* Множественный вариант торгового предложения */
            $variation = $call->call(
                $environment,
                $data->getProductVariationValue(),
                $data->getProductVariationReference().'_render',
            );

            $strOffer .= $variation ? ' '.trim($variation) : null;


            /* Модификация множественного варианта торгового предложения */
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

            /**
             * Информация о стоимости и остатках
             */

            $Money = $data->getProductPrice();

            $sheet->setCellValue('A'.$key, $data->getDateModify()->format('d.m.Y H:i')); // Обновление товара
            $sheet->setCellValue('B'.$key, trim($data->getProductArticle())); // Артикул
            $sheet->setCellValue('C'.$key, $data->getProductName()); // Наименование товара
            $sheet->setCellValue('D'.$key, str_replace(' /', '/', $strOffer)); // Торговое предложение
            $sheet->setCellValue('E'.$key, $Money ? $Money->getValue() : 0); // Стоимость

            $sheet->setCellValue('F'.$key, $data->getStockTotal()); // Наличие
            $sheet->setCellValue('G'.$key, $data->getStockReserve()); // Резерв
            $sheet->setCellValue('H'.$key, $data->getStockStorage()); // Место

            $key++;
        }

        /**
         * Отдать результат для скачивания
         */

        /** @var ProductStocksApproveReportResult $data */
        $filename = 'Отчёт_об_остатках_требующих_подтверждение.xlsx';

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);


        /* Задать HTTP-заголовки для HTTP-ответа с целью инициировать скачивание файла браузером пользователя */
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;

    }
}