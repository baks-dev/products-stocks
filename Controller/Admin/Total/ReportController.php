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
use BaksDev\Products\Stocks\Repository\AllProductStocksReport\AllProductStocksReportInterface;
use BaksDev\Products\Stocks\Repository\AllProductStocksReport\AllProductStocksReportResult;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceRepository;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
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
final class ReportController extends AbstractController
{
    #[Route('/admin/products/stock/all/export', name: 'admin.total.all.export', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllProductStocksReportInterface $allProductStocksReportRepository,
        UserProfileChoiceRepository $UserProfileChoiceRepository,
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


        /** @var Generator<AllProductStocksReportResult> $query */
        $query = $allProductStocksReportRepository
            ->forUser($this->getUsr())
            ->filter($filter)
            ->findAll();


        // Создаем новый объект Spreadsheet
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);


        // Получаем текущий активный лист
        $sheet = $spreadsheet->getActiveSheet();

        $call = $environment->getExtension(CallTwigFuncExtension::class);


        /**
         * Задаем список колонок, которые будут отображаться перед колонками с количеством продукции на разных складах
         */
        $columns = ['Артикул', 'Наименование', 'Торговое предложение', 'Стоимость', 'Старая цена'];


        /** Количество колонок */
        $columnsCount = count($columns);

        /**
         * Получаем список профилей
         * @var array<UserProfileUid>|null $profiles
         */
        $profiles = iterator_to_array($UserProfileChoiceRepository->getActiveUserProfile($this->getUsr()->getId()));


        /** Если по какой-то причине ни один профиль не был найден - не выводим отчёт */
        if(empty($profiles))
        {
            return $this->redirectToReferer();
        }


        /** Шапка состоит из двух рядов, в первом - только названия профилей */
        foreach($profiles as $profileKey => $profile)
        {
            /**
             * Мы заполняем одним значением две колонки, объединяя их. Для этого получаем буквенные индексы, в
             * пределах которых должна длиться объединенная колонка (включительно)
             */
            $firstColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2);
            $lastColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2 + 1);

            $sheet->setCellValue($firstColumnLetter.'1', $profile->getAttr());

            $sheet->mergeCells($firstColumnLetter.'1:'.$lastColumnLetter.'1');
        }


        /** Во втором ряду шапки заполняем все остальные колонки */
        foreach($columns as $columnKey => $column)
        {
            $sheet
                ->setCellValue(Coordinate::stringFromColumnIndex($columnKey + 1).'2', $column)
                ->getColumnDimension(Coordinate::stringFromColumnIndex($columnKey + 1))
                ->setAutoSize(true);
        }

        foreach($profiles as $profileKey => $profile)
        {
            /** Для каждого профиля добавляем две колонки - наличие и резерв */
            $firstColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2);
            $lastColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2 + 1);

            $sheet->setCellValue($firstColumnLetter.'2', 'Наличие');
            $sheet->setCellValue($lastColumnLetter.'2', 'Резерв');

            $sheet
                ->getColumnDimension(Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2))
                ->setAutoSize(true);
            $sheet
                ->getColumnDimension(Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2 + 2))
                ->setAutoSize(true);
        }


        /** Заполнение данных начиная с ряда $key */
        $key = 3;

        foreach($query as $data)
        {
            /**
             * Множественный вариант
             */
            $variation = $call->call(
                $environment,
                $data->getProductVariationValue(),
                $data->getProductVariationReference().'_render',
            );

            $strOffer = $variation ? ' '.trim($variation) : null;


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


            /**
             * Информация о стоимости и остатках
             */
            $Money = $data->getProductPrice();


            // Артикул
            $sheet->setCellValue('A'.$key, trim($data->getProductArticle()));


            // Наименование товара
            $sheet->setCellValue('B'.$key, $data->getProductName());


            // Торговое предложение
            $sheet->setCellValue('C'.$key, str_replace(' /', '/', $strOffer));


            // Стоимость
            $sheet->setCellValue('D'.$key, $Money ? $Money->getValue() : 0);


            // Предыдущая стоимость
            $sheet->setCellValue('E'.$key,
                $data->getOldProductPrice() && $data->getOldProductPrice()->getValue() !== $Money->getValue()
                    ? $data->getOldProductPrice()->getValue()
                    : '',
            );


            foreach($profiles as $profileKey => $profile)
            {
                /** Для каждого профиля добавляем две колонки - наличие и резерв */
                $firstColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2);
                $lastColumnLetter = Coordinate::stringFromColumnIndex($columnsCount + $profileKey * 2 + 1);

                $total = $data->getProfileTotal($profile);

                if(false === empty($total))
                {
                    $sheet->setCellValue($firstColumnLetter.$key, $total->stock_total);
                    $sheet->setCellValue($lastColumnLetter.$key, $total->stock_reserve);
                    continue;
                }

                $sheet->setCellValue($firstColumnLetter.$key, 0);
                $sheet->setCellValue($lastColumnLetter.$key, 0);
            }

            $key++;
        }


        /* Отдаем результат для скачивания */
        $filename = 'Отчёт_по_складским_остаткам.xlsx';

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);


        // Redirect output to a client’s web browser (Xls)
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="'.str_replace('"', '', $filename).'"'
        );
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}