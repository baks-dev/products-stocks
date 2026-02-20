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

namespace BaksDev\Products\Stocks\UseCase\Admin\StockSettings\Tests;

use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEvent;
use BaksDev\Products\Stocks\Entity\StocksSettings\ProductStockSettings;
use BaksDev\Products\Stocks\Type\Settings\Id\ProductStockSettingsUid;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\ProductStockSettingsDTO;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\ProductStockSettingsHandler;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\Threshold\ProductStockSettingsThresholdDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductStockSettingsHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $ProductStockSettings = $em->getRepository(ProductStockSettings::class)
            ->find(ProductStockSettingsUid::TEST);

        if($ProductStockSettings)
        {
            $em->remove($ProductStockSettings);
        }

        $ProductStockSettingsEvent = $em->getRepository(ProductStockSettingsEvent::class)
            ->findBy(['main' => ProductStockSettingsUid::TEST]);

        foreach($ProductStockSettingsEvent as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
    }

    public function testUseCase(): void
    {
        $profile = UserProfileUid::TEST;
        $ProductStockSettingsDTO = new ProductStockSettingsDTO(new UserProfileUid($profile));

        $ProductStockSettingsThresholdDTO = new ProductStockSettingsThresholdDTO();
        $ProductStockSettingsThresholdDTO->setValue(10);
        $ProductStockSettingsDTO->setThreshold($ProductStockSettingsThresholdDTO);


        /** @var ProductStockSettingsHandler $ProductStockSettingsHandler */
        $ProductStockSettingsHandler = self::getContainer()->get(ProductStockSettingsHandler::class);
        $handle = $ProductStockSettingsHandler->handle($ProductStockSettingsDTO);

        self::assertTrue(($handle instanceof ProductStockSettings), $handle.': Ошибка ProductStockSettings');
    }

}