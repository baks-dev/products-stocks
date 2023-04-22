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

namespace BaksDev\Products\Stocks\DataFixtures\Menu;

use BaksDev\Menu\Admin\DataFixtures\Menu\MenuAdminPath;
use BaksDev\Menu\Admin\DataFixtures\Menu\MenuAdminSectionFixtures;
use BaksDev\Menu\Admin\DataFixtures\Menu\MenuAdminFixturesHandler;

use BaksDev\Menu\Admin\Repository\ActiveEventMenuAdmin\ActiveMenuAdminEventRepositoryInterface;
use BaksDev\Menu\Admin\Repository\ExistPath\MenuAdminExistPathRepositoryInterface;
use BaksDev\Menu\Admin\Type\SectionGroup\MenuAdminSectionGroupEnum;

use BaksDev\Products\Stocks\DataFixtures\Security\Role\RoleDTO;
use BaksDev\Users\Groups\Role\Type\RolePrefix\RolePrefix;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ProductsStocksMenuAdminPathFixtures extends Fixture implements DependentFixtureInterface
{
	private MenuAdminExistPathRepositoryInterface $MenuAdminPath;
	private ActiveMenuAdminEventRepositoryInterface $activeMenuAdminEvent;
	private MenuAdminFixturesHandler $handler;
	
	public function __construct(
		MenuAdminExistPathRepositoryInterface $MenuAdminPath,
		ActiveMenuAdminEventRepositoryInterface $activeMenuAdminEvent,
		MenuAdminFixturesHandler $handler,
	)
	{
		$this->MenuAdminPath = $MenuAdminPath;
		$this->activeMenuAdminEvent = $activeMenuAdminEvent;
		$this->handler = $handler;
	}
	
	private const GROUP = MenuAdminSectionGroupEnum::PRODUCTS;
	private const PATH = 'ProductStocks:admin.index';
	private const SORT = 600;
	
	public function load(ObjectManager $manager)
	{
		# php bin/console doctrine:fixtures:load --append
		
		/* Если пункт меню уже добавлен - пропускаем */
		if($this->MenuAdminPath->isExist(self::PATH))
		{
			return;
		}
		
		$Event = $this->activeMenuAdminEvent->getEventOrNullResult();
		
		if(!$Event)
		{
			return;
		}
		
		$MenuAdminDTO = new MenuAdminPath\MenuAdminDTO();
		$Event->getDto($MenuAdminDTO);
		
		/** @var MenuAdminPath\Section\MenuAdminSectionDTO $MenuAdminSectionDTO */
		foreach($MenuAdminDTO->getSection() as $MenuAdminSectionDTO)
		{
			if($MenuAdminSectionDTO->getGroup()->getType() === self::GROUP)
			{
				/** Настройки локали из фикстуры роли доступа Security
				 *
				 * @see \BaksDev\Products\Category\DataFixtures\Security\Role\RoleDTO
				 */
				$desc = RoleDTO::ROLE_DESC;
				$name = RoleDTO::ROLE_NAME;
				$prefix = RoleDTO::ROLE_PREFIX;
				
				$MenuAdminSectionPathDTO = new MenuAdminPath\Section\Path\MenuAdminSectionPathDTO();
				$MenuAdminSectionPathDTO->setRole(new RolePrefix($prefix));
				$MenuAdminSectionPathDTO->setPath(self::PATH);
				$MenuAdminSectionPathDTO->setSort(self::SORT);
				$MenuAdminSectionDTO->addPath($MenuAdminSectionPathDTO);
				
				/* Настройки локали пункта меню */
				$MenuAdminSectionPathTrans = $MenuAdminSectionPathDTO->getTranslate();
				
				/** @var MenuAdminPath\Section\Path\Trans\MenuAdminSectionPathTransDTO $MenuAdminSectionPathTransDTO */
				foreach($MenuAdminSectionPathTrans as $MenuAdminSectionPathTransDTO)
				{
					$locale = $MenuAdminSectionPathTransDTO->getLocal()->getValue();
					
					$MenuAdminSectionPathTransDTO->setName($name[$locale]);
					$MenuAdminSectionPathTransDTO->setDescription($desc[$locale]);
				}
			}
		}
		
		$MenuAdmin = $this->handler->handle($MenuAdminDTO);
	}
	
	public function getDependencies()
	{
		return [
			MenuAdminSectionFixtures::class,
		];
	}
}