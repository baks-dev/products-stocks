<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Products\Stocks\BaksDevProductsStocksBundle;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventType;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockType;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Parameters\ProductStockParameterType;
use BaksDev\Products\Stocks\Type\Parameters\ProductStockParameterUid;
use BaksDev\Products\Stocks\Type\Product\ProductStockCollectionType;
use BaksDev\Products\Stocks\Type\Product\ProductStockCollectionUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatusType;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalType;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {

    $doctrine->dbal()->type(ProductStockUid::TYPE)->class(ProductStockType::class);
    $doctrine->dbal()->type(ProductStockEventUid::TYPE)->class(ProductStockEventType::class);
    $doctrine->dbal()->type(ProductStockCollectionUid::TYPE)->class(ProductStockCollectionType::class);
    $doctrine->dbal()->type(ProductStockStatus::TYPE)->class(ProductStockStatusType::class);
    $doctrine->dbal()->type(ProductStockTotalUid::TYPE)->class(ProductStockTotalType::class);
    $doctrine->dbal()->type(ProductStockParameterUid::TYPE)->class(ProductStockParameterType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $emDefault->mapping('products-stocks')
        ->type('attribute')
        ->dir(BaksDevProductsStocksBundle::PATH.'Entity')
        ->isBundle(false)
        ->prefix(BaksDevProductsStocksBundle::NAMESPACE.'\\Entity')
        ->alias('products-stocks');
};
