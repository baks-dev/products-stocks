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

namespace BaksDev\Products\Stocks\DataFixtures\Security\Check;

use BaksDev\Users\Groups\Group\Entity\CheckRole\CheckRoleInterface;
use BaksDev\Users\Groups\Group\Entity\Event\GroupEvent;
use BaksDev\Users\Groups\Role\Entity\Event\RoleEventInterface;
use BaksDev\Users\Groups\Role\Type\RolePrefix\RolePrefix;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckRoleDTO implements CheckRoleInterface
{
	
	/** Связь на событие группы */
	#[Assert\NotBlank]
	private GroupEvent $event;
	
	/** Префикс роли */
	#[Assert\NotBlank]
	private RolePrefix $role;
	
	/** Правила роли */
	#[Assert\Valid]
	private ArrayCollection $voter;
	
	
	public function __construct(GroupEvent $event, RoleEventInterface $roleDTO)
	{
		$this->event = $event;
		$this->voter = new ArrayCollection();
		
		$this->role = $roleDTO->getRole();
		
		foreach($roleDTO->getVoter() as $voter)
		{
			$CheckVoterDTO = new Voter\CheckVoterDTO();
			$CheckVoterDTO->setVoter($voter->getVoter());
			$this->addVoter($CheckVoterDTO);
		}
	}
	
	
	/**
	 * @return GroupEvent
	 */
	public function getEvent() : GroupEvent
	{
		return $this->event;
	}
	
	/* role */
	
	/**
	 * @return RolePrefix
	 */
	public function getRole() : RolePrefix
	{
		return $this->role;
	}
	
	
	/* VOTER */
	
	/**
	 * @return ArrayCollection
	 */
	public function getVoter() : ArrayCollection
	{
		return $this->voter;
	}
	
	
	public function addVoter(Voter\CheckVoterDTO $voter) : void
	{
		
		if(!$this->voter->contains($voter))
		{
			$this->voter->add($voter);
		}
	}
	
	
	/** Метод для инициализации и маппинга сущности на DTO в коллекции  */
	public function getVoterClass() : Voter\CheckVoterDTO
	{
		return new Voter\CheckVoterDTO();
	}
	
}

