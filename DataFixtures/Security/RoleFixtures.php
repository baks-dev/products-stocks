<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\DataFixtures\Security;

use BaksDev\Products\Stocks\DataFixtures\Security\Role\RoleDTO;
use BaksDev\Users\Groups\Group\DataFixtures\Security\Group\GroupFixtures;
use BaksDev\Users\Groups\Group\Entity\Event\GroupEvent;
use BaksDev\Users\Groups\Group\UseCase\Admin\NewEdit\CheckRoleHandler;
use BaksDev\Users\Groups\Role\Entity\Role;
use BaksDev\Users\Groups\Role\UseCase\Admin\NewEdit\RoleHandler;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class RoleFixtures extends Fixture implements DependentFixtureInterface
{
	
	private RoleHandler $roleAggregate;
	
	private CheckRoleHandler $checkRoleAggregate;
	
	
	public function __construct(
		RoleHandler $roleAggregate,
		CheckRoleHandler $checkRoleAggregate,
	)
	{
		$this->roleAggregate = $roleAggregate;
		$this->checkRoleAggregate = $checkRoleAggregate;
	}
	
	
	public function load(ObjectManager $manager) : void
	{
		# php bin/console doctrine:fixtures:load --append
		
		/* Role */
		
		$RoleDTO = new RoleDTO();
		$RoleEvent = $manager->getRepository(Role::class)->find($RoleDTO->getRole());
		
		if(empty($RoleEvent))
		{
			//if($RoleEvent) { $RoleDTO->setId($RoleEvent->getEvent()); }
			$this->roleAggregate->handle($RoleDTO);
		}
		
		/* CheckRole */
		
		/** @var GroupEvent $GroupEvent */
		$GroupEvent = $this->getReference(GroupFixtures::class);
		
		$CheckRoleDTO = new Check\CheckRoleDTO($GroupEvent, $RoleDTO);
		$this->checkRoleAggregate->handle($CheckRoleDTO);
		
	}
	
	
	public function getDependencies() : array
	{
		return [
			GroupFixtures::class,
		];
	}
	
}