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


use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksIncoming\ProductStocksIncomingVerifyInterface;
use BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksMove\ProductStocksMoveVerifyInterface;
use BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksOrders\ProductStocksIncomingOrdersInterface;
use BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksReserve\ProductStocksOrdersReserveVerifyInterface;
use BaksDev\Products\Stocks\Repository\VerifyByProfile\ProductStocksTotal\ProductStocksTotalVerifyInterface;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentAllUserProfiles\CurrentAllUserProfilesByUserInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'baks:product:stocks:verify',
    description: 'Сверяем все транзакции c остатками'
)]
class ProductStocksVerifyCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private CurrentProductIdentifierByConstInterface $CurrentProductIdentifierByConstRepository,
        private CurrentAllUserProfilesByUserInterface $CurrentAllUserProfilesByUserRepository,
        private ProductStocksTotalVerifyInterface $ProductStocksTotalByProfileVerifyRepository,
        private ProductStocksIncomingVerifyInterface $ProductStocksIncomingVerifyRepository,
        private ProductStocksMoveVerifyInterface $ProductStocksMoveVerifyRepository,
        private ProductStocksIncomingOrdersInterface $ProductStocksIncomingOrdersRepository,
        private ProductStocksOrdersReserveVerifyInterface $ProductStocksOrdersReserveVerifyRepository,
        #[Autowire(env: 'PROJECT_USER')] private string|null $projectUser = null,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('article', 'a', InputOption::VALUE_OPTIONAL, 'Фильтр по артикулу ((--article=... || -a ...))');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if(empty($this->projectUser))
        {
            $this->io->warning('Не указан идентификатор пользователя проекта');

            return Command::SUCCESS;
        }

        /** Получаем все профили пользователя */

        $profiles = $this->CurrentAllUserProfilesByUserRepository
            ->forUser(new UserUid($this->projectUser))
            ->findAll();


        if(false === $profiles || false === $profiles->valid())
        {
            $this->io->warning(sprintf('%s: Профили пользователя проекта проекта не найдены', $this->projectUser));
            return Command::SUCCESS;
        }

        $profiles = iterator_to_array($profiles);
        $helper = $this->getHelper('question');

        /**
         * Интерактивная форма списка профилей
         */

        $questions[] = 'Все';

        foreach($profiles as $quest)
        {
            $questions[] = $quest->getParams()->username;
        }

        $questions['-'] = 'Выйти';

        /** Объявляем вопрос с вариантами ответов */
        $question = new ChoiceQuestion(
            question: 'Профиль пользователя',
            choices: $questions,
            default: 0,
        );

        $key = $helper->ask($input, $output, $question);


        if(empty($key))
        {
            /** @var UserProfileUid $profile */
            foreach($profiles as $profile)
            {
                $this->io->success($profile->getParams()->username);
                $this->update($profile, $input->getOption('article'));
            }

            return Command::SUCCESS;
        }

        else
        {
            $UserProfileUid = null;

            foreach($profiles as $profile)
            {
                if($profile->getParams()->username === $questions[$key])
                {
                    /* Присваиваем профиль пользователя */
                    $UserProfileUid = $profile;
                    break;
                }
            }

            if($UserProfileUid)
            {
                $this->io->success($UserProfileUid->getParams()->username);
                $this->update($UserProfileUid, $input->getOption('article'));
            }

        }

        /** Перечисляем все профили, на которые есть остатки */

        return Command::SUCCESS;
    }


    public function update(UserProfileUid $profile, ?string $article = null): void
    {
        /**
         * Получаем все остатки по складу текущего профиля
         */
        $resultStocks = $this->ProductStocksTotalByProfileVerifyRepository
            ->forProfile($profile)
            ->findAll();

        if(empty($resultStocks))
        {
            return;
        }

        foreach($resultStocks as $ProductStocksTotalVerifyResult)
        {
            /** Получаем активные идентификаторы продукта */
            $CurrentProductIdentifierResult = $this
                ->CurrentProductIdentifierByConstRepository
                ->forProduct($ProductStocksTotalVerifyResult->getProduct())
                ->forOfferConst($ProductStocksTotalVerifyResult->getProductOfferConst())
                ->forVariationConst($ProductStocksTotalVerifyResult->getProductVariationConst())
                ->forModificationConst($ProductStocksTotalVerifyResult->getProductModificationConst())
                ->find();

            if(false === empty($article) && stripos($CurrentProductIdentifierResult->getArticle(), $article) === false)
            {
                $this->io->writeln(sprintf('<fg=gray>... %s</>', $CurrentProductIdentifierResult->getArticle()));
                continue;
            }

            /**
             * Получаем все ПРИХОДЫ
             */

            $incomingTotal = $this->ProductStocksIncomingVerifyRepository
                ->forProfile($profile)
                ->forProduct($ProductStocksTotalVerifyResult->getProduct())
                ->forOfferConst($ProductStocksTotalVerifyResult->getProductOfferConst())
                ->forVariationConst($ProductStocksTotalVerifyResult->getProductVariationConst())
                ->forModificationConst($ProductStocksTotalVerifyResult->getProductModificationConst())
                ->find();


            /**
             * Получаем все РАСХОДЫ по заказам
             */
            $ordersTotal = $this->ProductStocksIncomingOrdersRepository
                ->forProfile($profile)
                ->forProduct($ProductStocksTotalVerifyResult->getProduct())
                ->forOfferConst($ProductStocksTotalVerifyResult->getProductOfferConst())
                ->forVariationConst($ProductStocksTotalVerifyResult->getProductVariationConst())
                ->forModificationConst($ProductStocksTotalVerifyResult->getProductModificationConst())
                ->find();


            /**
             * Получаем все ПЕРЕМЕЩЕНИЯ по заказам
             */

            $moveTotal = $this->ProductStocksMoveVerifyRepository
                ->forProfile($profile)
                ->forProduct($ProductStocksTotalVerifyResult->getProduct())
                ->forOfferConst($ProductStocksTotalVerifyResult->getProductOfferConst())
                ->forVariationConst($ProductStocksTotalVerifyResult->getProductVariationConst())
                ->forModificationConst($ProductStocksTotalVerifyResult->getProductModificationConst())
                ->find();


            /** Получаем все резервы на продукцию по заказам */


            $reserve = $this->ProductStocksOrdersReserveVerifyRepository
                ->forProfile($profile)
                ->forProduct($ProductStocksTotalVerifyResult->getProduct())
                ->forOfferConst($ProductStocksTotalVerifyResult->getProductOfferConst())
                ->forVariationConst($ProductStocksTotalVerifyResult->getProductVariationConst())
                ->forModificationConst($ProductStocksTotalVerifyResult->getProductModificationConst())
                ->find();


            /**
             * Результат вычислений
             */

            $total = $incomingTotal;

            if($ordersTotal)
            {
                $total -= $ordersTotal;
            }

            if($moveTotal)
            {
                $total -= $moveTotal;
            }

            if($ProductStocksTotalVerifyResult->getTotal() !== $total)
            {
                /** Получаем артикул для сверки */

                $this->io->text(sprintf(
                    '%s => остаток %s | расчетный %s',
                    $CurrentProductIdentifierResult->getArticle(),
                    $ProductStocksTotalVerifyResult->getTotal(),
                    $total,
                ));
            }

            if($ProductStocksTotalVerifyResult->getReserve() !== $reserve)
            {
                $this->io->text(sprintf(
                    '%s => резерв %s | склад %s ',
                    $CurrentProductIdentifierResult->getArticle(),
                    $ProductStocksTotalVerifyResult->getTotal(),
                    $reserve,
                ));
            }


            if($CurrentProductIdentifierResult->getArticle() === $article)
            {
                break;
            }
        }
    }
}
