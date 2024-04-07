<?php


declare(strict_types=1);

namespace BaksDev\Products\Stocks\Commands;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
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
    name: 'baks:product:stocks:verify',
    description: 'Сверяем все транзакции c остатками'
)]
class ProductStocksVerify extends Command
{
    private DBALQueryBuilder $DBALQueryBuilder;
    private ProductWarehouseTotalInterface $productWarehouseTotal;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ProductWarehouseTotalInterface $productWarehouseTotal
    )
    {
        parent::__construct();
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->productWarehouseTotal = $productWarehouseTotal;
    }

    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'Идентификатор профиля');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profile = $input->getArgument('profile');

        if(!$profile)
        {
            $io->error('Не указан идентификатор профиля пользователя (Пример: php bin/console baks:product:stocks:verify <UID>)');
            return Command::INVALID;
        }

        $UserProfileUid = new UserProfileUid($profile);

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // Цесарка
        //$profile = new UserProfileUid('018b81fa-2120-7473-b87f-31d011ffedd6');

        // Денисьевский
        //$profile = new UserProfileUid('018c435d-3ffa-71df-abd0-2345023d042d');

        $dbal
            //->addSelect('event.main AS id')
            //->addSelect('event.id AS event')
            //->addSelect('event.number')

            //->addSelect('event.comment')
            //->addSelect('event.status')
            //->addSelect('event.profile AS user_profile_id')
            //->addSelect('move.destination AS destination_profile_id')

            ->from(ProductStockEvent::class, 'event')
            ->andWhere('event.status = :status ')
            ->setParameter('status', new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()), ProductStockStatus::TYPE)
            ->andWhere('(event.profile = :profile OR move.destination = :profile)')
            ->setParameter('profile', $UserProfileUid, UserProfileUid::TYPE);

        $dbal->addSelect('SUM(CASE WHEN event.profile = :profile THEN stock_product.total ELSE 0 END) AS sum_incoming');
        $dbal->addSelect('SUM(CASE WHEN move.destination = :profile THEN stock_product.total ELSE 0 END) AS sum_destination');


        $dbal
            ->join(
                'event',
                ProductStock::class,
                'stock',
                'stock.event = event.id'

            );


        $dbal
            //->addSelect('move.destination AS move_destination')
            ->leftJoin(
                'event',
                ProductStockMove::class,
                'move',
                'move.event = event.id'
            );


        $dbal
            ->addSelect('stock_product.product')
            ->addSelect('stock_product.offer')
            ->addSelect('stock_product.variation')
            ->addSelect('stock_product.modification')
            ->leftJoin(
                'event',
                ProductStockProduct::class,
                'stock_product',
                'stock_product.event = stock.event'
            )

            //            ->addGroupBy('stock_product.product')
            //            ->addGroupBy('stock_product.offer')
            //            ->addGroupBy('stock_product.variation')
            //            ->addGroupBy('stock_product.modification')

        ;


        // Product
        $dbal
            ->addSelect('product.id as product_id')
            //->addSelect('product.event as product_event')
            ->leftJoin(
                'stock_product',
                Product::class,
                'product',
                'product.id = stock_product.product'
            );

        // Product Event
        $dbal->join(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event'
        );


        // Product Trans
        $dbal
            ->addSelect('product_trans.name as product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        // Торговое предложение

        $dbal
            //->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            //->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product_event.id AND product_offer.const = stock_product.offer'
            );


        // Множественные варианты торгового предложения

        $dbal
            //->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            //->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = stock_product.variation'
            );


        // Модификация множественного варианта торгового предложения

        $dbal
            //->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = stock_product.modification'
            );


        $dbal->allGroupByExclude();

        $dbal->orderBy('sum_incoming', 'DESC');

        $error = '';
        $warning = '';

        foreach($dbal->fetchAllAssociative() as $item)
        {
            $total = $this->productWarehouseTotal->getProductProfileTotalNotReserve(
                $UserProfileUid,
                new ProductUid($item['product']),
                new ProductOfferConst($item['offer']),
                new ProductVariationConst($item['variation']),
                new ProductModificationConst($item['modification'])
            );

            //$total = $item['sum_stock_product_total'] / $item['counter'];

            $sum = ($item['sum_incoming'] - $item['sum_destination']);

            if($sum != $total && $item['modification'])
            {
                $name = $item['product_name']
                    .' R'.$item['product_offer_value']
                    .' '.$item['product_variation_value']
                    .'/'.$item['product_modification_value']
                    .' : '.$sum.' != '.$total;

                if($sum > $total)
                {
                    $warning .= PHP_EOL.$name.PHP_EOL;
                    $warning .= $item['modification'].PHP_EOL;
                    $warning .= 'остаток указан меньше! сумма транзакций БОЛЬШЕ на '.($sum - $total).'. шт.'.PHP_EOL;
                }

                if($sum < $total)
                {
                    $error .= PHP_EOL.$name.PHP_EOL;
                    $error .= $item['modification'].PHP_EOL;
                    $error .= 'отсутствует ТРАНЗАКЦИЯ! Остаток БОЛЬШЕ на '.($total - $sum).' шт.'.PHP_EOL;
                }

                if(!$item['sum_incoming'])
                {
                    $error .= PHP_EOL;
                    $error .= $name;
                    $error .= ' Не найдено прихода! (Приход !0) ';
                }

                $error .= PHP_EOL;
            }
            else
            {
                if($sum !== 0)
                {
                    $msg = $item['product_name']
                        .' R'.$item['product_offer_value']
                        .' '.$item['product_variation_value']
                        .'/'.$item['product_modification_value']
                        .' : '.$sum.' = '.$total;

                    $io->text($msg);
                }
            }
        }

        if(!empty($error))
        {
            $io->error($error);
        }

        if(!empty($warning))
        {
            $io->warning($warning);
        }

        if(!empty($error) || !empty($warning))
        {
            $io->note('Обязательно проверьте все приходы!');
        }

        return Command::SUCCESS;
    }
}
