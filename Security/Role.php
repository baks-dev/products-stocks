<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Security;

use BaksDev\Menu\Admin\DataFixtures\Menu\MenuAdminFixturesInterface;
use BaksDev\Menu\Admin\Type\SectionGroup\MenuAdminSectionGroupEnum;
use BaksDev\Users\Groups\Group\DataFixtures\Security\RoleFixturesInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('baks.security.role')]
#[AutoconfigureTag('baks.menu.admin')]
class Role implements RoleFixturesInterface, MenuAdminFixturesInterface
{
    public const ROLE = 'ROLE_PRODUCT_STOCK';

    public function getRole(): string
    {
        return self::ROLE;
    }

    /**
     * Добавляем раздел в меню администрирвоания.
     */

    /** Метод возвращает PATH раздела */
    public function getPath(): string
    {
        return 'ProductStocks:admin.index';
    }

    /** Метод возвращает секцию, в которую помещается ссылка на раздел */
    public function getGroupMenu(): MenuAdminSectionGroupEnum|bool
    {
        if (enum_exists(MenuAdminSectionGroupEnum::class)) {
            return MenuAdminSectionGroupEnum::PRODUCTS;
        }

        return false;
    }

    /** Метод возвращает позицию, в которую распологается ссылка в секции меню */
    public function getSortMenu(): int
    {
        return 600;
    }
}
