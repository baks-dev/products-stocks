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
declare(strict_types=1);

namespace BaksDev\Products\Stocks\Security;

use BaksDev\Menu\Admin\DataFixtures\Menu\MenuAdminFixturesInterface;
use BaksDev\Menu\Admin\Type\SectionGroup\MenuAdminSectionGroupEnum;
use BaksDev\Users\Groups\Group\DataFixtures\Security\RoleFixturesInterface;
use BaksDev\Users\Groups\Group\DataFixtures\Security\VoterFixturesInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('baks.security.voter')]
final class VoterPurchase implements VoterFixturesInterface, MenuAdminFixturesInterface
{
    
    /** Закупки продукции */
    public const VOTER = 'PURCHASE';

    public static function getVoter(): string
    {
        return Role::ROLE.'_'.self::VOTER;
    }

    public function equals(RoleFixturesInterface $role): bool
    {
        return $role->getRole() === Role::ROLE;
    }
    
    /** Метод возвращает префикс роли доступа */
    public function getRole(): string
    {
        return self::getVoter();
    }

    /** Метод возвращает PATH раздела */
    public function getPath(): string
    {
        return 'ProductStocks:admin.purchase.index';
    }

    /** Метод возвращает секцию, в которую помещается ссылка на раздел */
    public function getGroupMenu(): MenuAdminSectionGroupEnum|bool
    {
        if (enum_exists(MenuAdminSectionGroupEnum::class))
        {
            return MenuAdminSectionGroupEnum::STOCKS;
        }

        return false;
    }

    /** Метод возвращает позицию, в которую располагается ссылка в секции меню */
    public function getSortMenu(): int
    {
        return 110;
    }

    /** Метод возвращает флаг "Показать в выпадающем меню"  */
    public function getDropdownMenu(): bool
    {
        return true;
    }

    /**
     * Метод возвращает флаг "Модальное окно".
     */
    public function getModal(): bool
    {
        return false;
    }
}
