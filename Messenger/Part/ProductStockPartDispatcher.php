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

namespace BaksDev\Products\Stocks\Messenger\Part;


use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


/** Генерируем QR-код партии */
#[AsMessageHandler(priority: 100)]
final readonly class ProductStockPartDispatcher
{
    public function __construct(private BarcodeWrite $BarcodeWrite) {}

    public function __invoke(ProductStockPartMessage $message): void
    {
        $isBarcode = $this->BarcodeWrite
            ->text($message->getPart())
            ->type(BarcodeType::QRCode)
            ->format(BarcodeFormat::SVG)
            ->generate();

        // Condition is always 'false' because 'BarcodeWrite $BarcodeWrite' is evaluated at this point
        if(false === $isBarcode)
        {
            /**
             * Проверить права на исполнение
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Writer/Generate
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Reader/Decode
             * */
            throw new RuntimeException('Barcode write error');
        }

        $render = $this->BarcodeWrite->render();
        $render = strip_tags($render, ['path']);
        $render = trim($render);

        /** Создаем стикер партии */

        $sticker[$message->getPart()]['part'] = $render;

        $this->BarcodeWrite->remove();


        /** Добавляем информацию о продукции */


        $message->addSticker($sticker);
    }
}
