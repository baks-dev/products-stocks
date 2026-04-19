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
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariable;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
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


        /** Перечисляем все профили, на которые есть остатки */

        $profiles = [


        ];


        foreach($profiles as $profile)
        {

            $UserProfileUid = new UserProfileUid($profile);


            /** Получаем все остатки по складу текущего профиля */

            $dbalStocks = $this->DBALQueryBuilder
                ->createQueryBuilder(self::class)
                ->bindLocal();

            $dbalStocks
                ->select('SUM(stock_product.total) AS total')
                ->addSelect('SUM(stock_product.reserve) AS reserve')
                ->addSelect('stock_product.product')
                ->addSelect('stock_product.offer')
                ->addSelect('stock_product.variation')
                ->addSelect('stock_product.modification')
                ->from(ProductStockTotal::class, 'stock_product');


            $dbalStocks->andWhere('stock_product.profile = :profile AND stock_product.total != 0')
                ->setParameter(
                    key: 'profile',
                    value: $UserProfileUid,
                    type: UserProfileUid::TYPE,
                );

            $dbalStocks->allGroupByExclude();

            $resultStocks = $dbalStocks->fetchAllAssociative();

            if(empty($resultStocks))
            {
                continue;
            }

            foreach($resultStocks as $stock)
            {
                /**
                 * Получаем все ПРИХОДЫ
                 */

                $dbalStocksEventIncoming = $this->DBALQueryBuilder
                    ->createQueryBuilder(self::class);

                $dbalStocksEventIncoming
                    ->from(ProductStockEvent::class, 'event')
                    ->andWhere('event.status = :incoming ')
                    ->setParameter(
                        'incoming',
                        new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()),
                        ProductStockStatus::TYPE,
                    );

                $dbalStocksEventIncoming
                    // номер ордера
                    //->addSelect('product_stock_invariable.number')
                    ->join(
                        'event',
                        ProductStocksInvariable::class,
                        'product_stock_invariable',
                        'product_stock_invariable.event = event.id AND product_stock_invariable.profile = :profile',
                    )
                    ->setParameter(
                        'profile',
                        $UserProfileUid,
                        UserProfileUid::TYPE,
                    );


                $dbalStocksEventIncoming
                    ->join(
                        'event',
                        ProductStock::class,
                        'stock',
                        'stock.event = event.id',

                    );


                $dbalStocksEventIncoming
                    ->join(
                        'event',
                        ProductStockProduct::class,
                        'stock_product',
                        '
                            stock_product.event = event.id
                            AND stock_product.product = :product
                            AND stock_product.offer = :offer
                            AND stock_product.variation = :variation
                            AND stock_product.modification = :modification
                        ',
                    )
                    ->setParameter('product', $stock['product'])
                    ->setParameter('offer', $stock['offer'])
                    ->setParameter('variation', $stock['variation'])
                    ->setParameter('modification', $stock['modification']);


                /** Приход */
                $dbalStocksEventIncoming->addSelect('SUM(stock_product.total) AS total');
                $transactionTotal = $dbalStocksEventIncoming->fetchOne();

                /**
                 * Получаем все РАСХОДЫ по заказам
                 */

                $dbalStocksEventOrder = $this->DBALQueryBuilder
                    ->createQueryBuilder(self::class);

                $dbalStocksEventOrder
                    ->from(ProductStockEvent::class, 'event')
                    ->andWhere('event.status = :completed OR event.status = :decommission')
                    ->setParameter(
                        'completed',
                        new ProductStockStatus(new ProductStockStatus\ProductStockStatusCompleted()),
                        ProductStockStatus::TYPE,
                    )
                    ->setParameter(
                        'decommission',
                        new ProductStockStatus(new ProductStockStatus\ProductStockStatusDecommission()),
                        ProductStockStatus::TYPE,
                    );


                $dbalStocksEventOrder->join(
                    'event',
                    ProductStocksInvariable::class,
                    'product_stock_invariable',
                    'product_stock_invariable.event = event.id AND product_stock_invariable.profile = :profile',
                )
                    ->setParameter(
                        'profile',
                        $UserProfileUid,
                        UserProfileUid::TYPE,
                    );


                $dbalStocksEventOrder
                    ->join(
                        'event',
                        ProductStockProduct::class,
                        'stock_product',
                        '
                            stock_product.event = event.id
                            AND stock_product.product = :product
                            AND stock_product.offer = :offer
                            AND stock_product.variation = :variation
                            AND stock_product.modification = :modification
                        ',
                    )
                    ->setParameter('product', $stock['product'])
                    ->setParameter('offer', $stock['offer'])
                    ->setParameter('variation', $stock['variation'])
                    ->setParameter('modification', $stock['modification']);


                /** Расход */
                $dbalStocksEventOrder->addSelect('SUM(stock_product.total) AS total');
                $orderTotal = $dbalStocksEventOrder->fetchOne();


                /**
                 * Получаем все ПЕРЕМЕЩЕНИЯ по заказам
                 */

                $dbalStocksEventMove = $this->DBALQueryBuilder
                    ->createQueryBuilder(self::class);

                $dbalStocksEventMove
                    ->from(ProductStockEvent::class, 'event')
                    ->andWhere('event.status = :incoming ')
                    ->setParameter(
                        'incoming',
                        new ProductStockStatus(new ProductStockStatus\ProductStockStatusIncoming()),
                        ProductStockStatus::TYPE,
                    );


                $dbalStocksEventMove
                    ->join(
                        'event',
                        ProductStock::class,
                        'stock',
                        'stock.event = event.id',

                    );


                $dbalStocksEventMove
                    //->addSelect('move.destination AS move_destination')
                    ->join(
                        'event',
                        ProductStockMove::class,
                        'move',
                        'move.event = event.id AND move.destination = :profile',
                    )->setParameter(
                        'profile',
                        $UserProfileUid,
                        UserProfileUid::TYPE,
                    );


                $dbalStocksEventMove
                    ->join(
                        'event',
                        ProductStockProduct::class,
                        'stock_product',
                        '
                            stock_product.event = event.id
                            AND stock_product.product = :product
                            AND stock_product.offer = :offer
                            AND stock_product.variation = :variation
                            AND stock_product.modification = :modification
                        ',
                    )
                    ->setParameter('product', $stock['product'])
                    ->setParameter('offer', $stock['offer'])
                    ->setParameter('variation', $stock['variation'])
                    ->setParameter('modification', $stock['modification']);


                /** Перемещения */
                $dbalStocksEventMove->addSelect('SUM(stock_product.total) AS total');
                $moveTotal = $dbalStocksEventMove->fetchOne();

                /**
                 * Результат вычислений
                 */

                $total = $transactionTotal;

                if($orderTotal)
                {
                    $total -= $orderTotal;
                }

                if($moveTotal)
                {
                    $total -= $moveTotal;
                }

                if($stock['total'] !== $total)
                {

                    /** Получаем артикул для сверки */

                    $dbalArticle = $this->DBALQueryBuilder->createQueryBuilder(self::class);

                    if($stock['modification'])
                    {
                        $dbalArticle
                            ->select('product_modification.article')
                            ->from(ProductModification::class, 'product_modification')
                            ->where('product_modification.const = :modification')
                            ->setParameter(
                                'modification',
                                new ProductModificationConst($stock['modification']),
                                ProductModificationConst::TYPE,
                            )
                            ->orderBy('product_modification.id', 'DESC');
                    }


                    $article = $dbalArticle->fetchOne();

                    $io->text(sprintf(
                        '%s; транзакций %s, склад %s ',
                        $article,
                        $total,
                        $stock['total'],
                    ));

                    //                    if($article === 'PL02-20-275-35-102V')
                    //                    {
                    //                        echo "Артикул товара: ".$article.PHP_EOL;
                    //                        echo "Приходов: ".$transactionTotal.PHP_EOL;
                    //                        echo "Расходов: ".$orderTotal.PHP_EOL;
                    //
                    //                        if($moveTotal)
                    //                        {
                    //                            echo "Перемещений на др. склад: ".$orderTotal.PHP_EOL;
                    //                        }
                    //
                    //                        // номер ордера
                    //                        $dbalStocksEventIncoming->select('product_stock_invariable.number');
                    //                        $dbalStocksEventIncoming->addSelect('stock_product.total');
                    //                        $transactionAssociative = $dbalStocksEventIncoming->fetchAllAssociative();
                    //                        dump($transactionAssociative);
                    //                    }


                }


                //$dbalStocksEvent->addSelect('SUM(CASE WHEN event.profile = :profile THEN stock_product.total ELSE 0 END) AS sum_incoming');
                //$dbalStocksEvent->addSelect('SUM(CASE WHEN move.destination = :profile THEN stock_product.total ELSE 0 END) AS sum_destination');

            }


            dd($UserProfileUid);

        }

        return Command::SUCCESS;
    }
}
