<?php


declare(strict_types=1);

namespace BaksDev\Products\Stocks\Commands;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
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
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\ProductStocksTotal\ProductStocksTotalInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseTotal\ProductWarehouseTotalInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
    private ProductWarehouseTotalInterface $productWarehouseTotal;
    private ProductStocksTotalInterface $productStocksTotal;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ProductWarehouseTotalInterface $productWarehouseTotal,
        ProductStocksTotalInterface $productStocksTotal,
    )
    {
        parent::__construct();
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->productWarehouseTotal = $productWarehouseTotal;
        $this->productStocksTotal = $productStocksTotal;
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
            $total = $this->productStocksTotal->getProductStocksTotal(
                new ProductUid($product['product_id']),
                $product['product_offer_const'] ? new ProductOfferConst($product['product_offer_const']) : null,
                $product['product_variation_const'] ? new ProductVariationConst($product['product_variation_const']) : null,
                $product['product_modification_const'] ? new ProductModificationConst($product['product_modification_const']) : null
            );

            $reserve = $this->productStocksTotal->getProductStocksReserve(
                new ProductUid($product['product_id']),
                $product['product_offer_const'] ? new ProductOfferConst($product['product_offer_const']) : null,
                $product['product_variation_const'] ? new ProductVariationConst($product['product_variation_const']) : null,
                $product['product_modification_const'] ? new ProductModificationConst($product['product_modification_const']) : null
            );


            if($product['product_modification_const'])
            {


                if($product['product_modification_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_value'].' '.
                        $product['product_modification_quantity'].' != '.$total;

                    $errorTotal .= PHP_EOL;


                }


                if($product['product_modification_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_value'].' '.
                        $product['product_modification_reserve'].' != '.$reserve;

                    $errorReserve .= PHP_EOL;
                }
            }

            else if($product['product_variation_const'])
            {

                if($product['product_variation_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_quantity'].' != '.$total;

                    $errorTotal .= PHP_EOL;
                }

                if($product['product_variation_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_variation_value'].' '.
                        $product['product_modification_reserve'].' != '.$reserve;

                    $errorReserve .= PHP_EOL;
                }

            }

            else if($product['product_offer_const'])
            {

                if($product['product_offer_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_modification_quantity'].' != '.$total;

                    $errorTotal .= PHP_EOL;
                }

                if($product['product_offer_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_offer_value'].' '.
                        $product['product_modification_reserve'].' != '.$reserve;

                    $errorReserve .= PHP_EOL;
                }


            }

            else
            {
                if($product['product_quantity'] !== $total)
                {
                    $errorTotal .=
                        $product['product_name'].' '.
                        $product['product_modification_quantity'].' != '.$total;

                    $errorTotal .= PHP_EOL;
                }

                if($product['product_reserve'] !== $reserve)
                {
                    $errorReserve .=
                        $product['product_name'].' '.
                        $product['product_modification_reserve'].' != '.$reserve;

                    $errorReserve .= PHP_EOL;
                }

            }
        }

        if($errorTotal)
        {
            $io->success('Количественный учет');
            $io->error($errorTotal);
        }

        if($errorReserve)
        {
            $io->success('Резерв');
            $io->error($errorReserve);
        }

        return Command::SUCCESS;
    }
}
