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

namespace BaksDev\Products\Stocks\Controller\Admin\Total\StockSettings;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEvent;
use BaksDev\Products\Stocks\Entity\StocksSettings\ProductStockSettings;
use BaksDev\Products\Stocks\Repository\ProductStockSettingsEvent\ProductStockSettingsEventByProfileInterface;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\ProductStockSettingsDTO;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\ProductStockSettingsForm;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\ProductStockSettingsHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Настройка порога (threshold) наличия остатков профиля/склада
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_STOCK_INDEX')]
final class StockSettingsController extends AbstractController
{

    #[Route('/admin/product/stocks/settings', name: 'admin.total.settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProductStockSettingsHandler $settingsHandler,
        ProductStockSettingsEventByProfileInterface $settingsByProfile

    ): Response
    {

        $profile = $this->getProfileUid();
        $ProductStockSettingsDTO = new ProductStockSettingsDTO($profile);

        /* Получить Event для профиля */
        $ProductStockSettingsEvent = $settingsByProfile
            ->profile($profile)
            ->getSettingEvent();

        /* Если у профиля уже есть настройка, то редактировать */
        if(true === ($ProductStockSettingsEvent instanceof ProductStockSettingsEvent))
        {
            $ProductStockSettingsEvent->getDto($ProductStockSettingsDTO);
        }

        /* Форма */
        $form = $this
            ->createForm(
                type: ProductStockSettingsForm::class,
                data: $ProductStockSettingsDTO,
                options: ['action' => $this->generateUrl('products-stocks:admin.total.settings')]
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_stock_settings'))
        {
            $this->refreshTokenForm($form);

            $handle = $settingsHandler->handle($ProductStockSettingsDTO);

            $this->addFlash(
                'page.settings',
                $handle instanceof ProductStockSettings ? 'settings.update' : 'settings.danger',
                'products-stocks.admin',
                $handle
            );

            return $this->redirectToReferer();
        }

        return $this->render([
            'form' => $form->createView(),
        ]);

    }
}