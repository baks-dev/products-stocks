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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Commands;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Elastic\BaksDevElasticBundle;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Quantity\ProductOfferQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Quantity\ProductModificationQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Offers\Variation\Quantity\ProductVariationQuantity;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalByReserve\ProductStocksTotalByReserveInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:product:card:verify',
    description: 'Сверяем наличие в карточке товаров и на складе '
)]
class ProductCardVerify extends Command
{
    private DBALQueryBuilder $DBALQueryBuilder;
    private ProductStocksTotalInterface $productStocksTotal;
    private ProductStocksTotalByReserveInterface $productStocksTotalByReserve;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ProductWarehouseTotalInterface $productWarehouseTotal,
        ProductStocksTotalInterface $productStocksTotal,
        ProductStocksTotalByReserveInterface $productStocksTotalByReserve,
    )
    {
        parent::__construct();
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->productStocksTotal = $productStocksTotal;
        $this->productStocksTotalByReserve = $productStocksTotalByReserve;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('product.id AS product_id')
            ->from(Product::class, 'product');


        $dbal
            ->addSelect('product_quantity.quantity AS product_quantity')
            ->addSelect('product_quantity.reserve AS product_reserve')
            ->leftJoin(
                'product',
                ProductPrice::class,
                'product_quantity',
                'product_quantity.event = product.event'
            );


        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );

        $dbal
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.const as product_offer_const')
            ->addSelect('product_offer.article as product_offer_article')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event'
            );


        $dbal
            ->addSelect('product_offer_quantity.quantity as product_offer_quantity')
            ->addSelect('product_offer_quantity.reserve as product_offer_reserve')
            ->leftJoin(
                'product_offer',
                ProductOfferQuantity::class,
                'product_offer_quantity',
                'product_offer_quantity.offer = product_offer.id'
            );

        $dbal
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.article as product_variation_article')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id'
            );

        $dbal
            ->addSelect('product_variation_quantity.quantity as product_variation_quantity')
            ->addSelect('product_variation_quantity.reserve as product_variation_reserve')
            ->leftJoin(
                'product_variation',
                ProductVariationQuantity::class,
                'product_variation_quantity',
                'product_variation_quantity.variation = product_variation.id'
            );


        $dbal
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.const as product_modification_const')
            ->addSelect('product_modification.id as product_modification_id')
            ->addSelect('product_modification.article as product_modification_article')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id '
            );

        $dbal
            ->addSelect('product_modification_quantity.quantity as product_modification_quantity')
            ->addSelect('product_modification_quantity.reserve as product_modification_reserve')
            ->leftJoin(
                'product_modification',
                ProductModificationQuantity::class,
                'product_modification_quantity',
                'product_modification_quantity.modification = product_modification.id'
            );

        //dd($dbal->fetchAllAssociative()[0]);

        $errorTotal = '';
        $errorReserve = '';


        foreach($dbal->fetchAllAssociative() as $product)
        {
            $total = $this->productStocksTotal
                ->product($product['product_id'])
                ->offer($product['product_offer_const'])
                ->variation($product['product_variation_const'])
                ->modification($product['product_modification_const'])
                ->get();

            $reserve = $this->productStocksTotalByReserve
                ->product($product['product_id'])
                ->offer($product['product_offer_const'])
                ->variation($product['product_variation_const'])
                ->modification($product['product_modification_const'])
                ->get();

            if($product['product_modification_const'])
            {


                if($product['product_modification_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_value'].': '.
                        'card '.$product['product_modification_quantity'].' != склад '.$total.' '.$product['product_modification_article'];

                    $errorTotal .= PHP_EOL;
                }


                if($product['product_modification_reserve'] !== $reserve)
                {

                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_value'].': ';

                    if($product['product_modification_reserve'] > $reserve)
                    {
                        $errorReserve .=
                            'card '.$product['product_modification_reserve'].' != склад '.$reserve.' (ожидается резерв на склад '.$product['product_modification_reserve'] - $reserve.') '.$product['product_modification_article'];
                    }
                    else
                    {
                        $errorReserve .=
                            'card '.$product['product_modification_reserve'].' != склад '.$reserve.' (в карточке не снят резерв '.$product['product_modification_reserve'] - $reserve.') '.$product['product_modification_article'];
                    }


                    $errorReserve .= PHP_EOL;
                }
            }
            elseif($product['product_variation_const'])
            {

                if($product['product_variation_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].': '.
                        $product['product_variation_quantity'].' != '.$total.' (ожидается '.$total.') '.$product['product_modification_article'];

                    $errorTotal .= PHP_EOL;
                }

                if($product['product_variation_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].': '.
                        $product['product_variation_reserve'].' != '.$reserve.' (ожидается '.$reserve.') '.$product['product_modification_article'];

                    $errorReserve .= PHP_EOL;
                }

            }
            elseif($product['product_offer_const'])
            {

                if($product['product_offer_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].': '.
                        $product['product_offer_quantity'].' != '.$total.' (ожидается '.$total.') '.$product['product_offer_article'];


                    $errorTotal .= PHP_EOL;
                }

                if($product['product_offer_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].': '.
                        $product['product_offer_reserve'].' != '.$reserve.' (ожидается '.$reserve.') '.$product['product_offer_article'];

                    $errorReserve .= PHP_EOL;
                }
            }
            else
            {
                if($product['product_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].': '.
                        $product['product_modification_quantity'].' != '.$total.' (ожидается '.$total.') ';

                    $errorTotal .= PHP_EOL;
                }

                if($product['product_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].': '.
                        $product['product_modification_reserve'].' != '.$reserve.' (ожидается '.$reserve.') ';

                    $errorReserve .= PHP_EOL;
                }

            }
        }

        if($errorTotal)
        {
            $io->note('Различие наличие');
            $io->error($errorTotal);
        }

        if($errorReserve)
        {
            $io->note('Различие резервов');
            $io->error($errorReserve);
        }

        if(class_exists(BaksDevElasticBundle::class))
        {
            $io->warning('После изменений переиндексируйте ELASTIC SEARCH : sudo php bin/console baks:elastic:index');
        }

        return Command::SUCCESS;
    }
}
