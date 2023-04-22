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

namespace BaksDev\Products\Stocks\DataFixtures\Security\Role;

use BaksDev\Products\Stocks\DataFixtures\Security\Role;
use BaksDev\Users\Groups\Role\Entity\Event\RoleEventInterface;
use BaksDev\Users\Groups\Role\Type\Event\RoleEventUid;
use BaksDev\Users\Groups\Role\Type\RolePrefix\RolePrefix;
use BaksDev\Users\Groups\Role\Type\VoterPrefix\VoterPrefix;
use BaksDev\Core\Type\Locale\Locale;
use Doctrine\Common\Collections\ArrayCollection;

final class RoleDTO implements RoleEventInterface
{
	public const ROLE_PREFIX = 'ROLE_PRODUCT_STOCK';
	
	public const ROLE_NAME = [
		'ru' => 'Складской учет',
		'en' => 'Inventory control',
	];
	
	public const ROLE_DESC = [
		'ru' => 'Складской учет продукции',
		'en' => 'Warehouse accounting of products',
	];
	
	/** Идентификатор */
	private ?RoleEventUid $id = null;
	
	/** Префикс Роли */
	private RolePrefix $role;
	
	/** Настройки локали */
	private ArrayCollection $translate;
	
	/** Правила роли */
	private ArrayCollection $voter;
	
	
	public function __construct()
	{
		
		$this->translate = new ArrayCollection();
		$this->voter = new ArrayCollection();
		$this->role = new RolePrefix(self::ROLE_PREFIX);
	}
	
	
	public function getEvent() : ?RoleEventUid
	{
		return $this->id;
	}
	
	
	public function setId(RoleEventUid $id) : void
	{
		$this->id = $id;
	}
	
	
	/**
	 * @return RolePrefix
	 */
	public function getRole() : RolePrefix
	{
		return $this->role;
	}
	
	
	/* TRANSLATE */
	
	/**
	 * @return ArrayCollection
	 */
	public function getTranslate() : ArrayCollection
	{
		/* Вычисляем расхождение и добавляем неопределенные локали */
		foreach(Locale::diffLocale($this->translate) as $locale)
		{
			
			$TransFormDTO = new Role\Trans\RoleTransDTO();
			$TransFormDTO->setLocal($locale);
			$TransFormDTO->setName(self::ROLE_NAME[$locale->getValue()]);
			$TransFormDTO->setDescription(self::ROLE_DESC[$locale->getValue()]);
			$this->addTranslate($TransFormDTO);
		}
		
		return $this->translate;
	}
	
	
	/**
	 * @param Role\Trans\RoleTransDTO $translate
	 */
	public function addTranslate(Role\Trans\RoleTransDTO $translate) : void
	{
		$this->translate->add($translate);
	}
	
	
	/** Метод для инициализации и маппинга сущности на DTO в коллекции  */
	public function getTranslateClass() : Role\Trans\RoleTransDTO
	{
		return new Role\Trans\RoleTransDTO();
	}
	
	
	/* VOTER */
	
	/**
	 * @return ArrayCollection
	 */
	public function getVoter() : ArrayCollection
	{
		
		/* TODO return array_diff(self::cases(), $search); */
		
		if($this->voter->isEmpty())
		{
			foreach(Role\Voter\RoleVoterDTO::VOTERS as $prefix => $voter)
			{
				$RoleVoterDTO = new Role\Voter\RoleVoterDTO();
				$RoleVoterDTO->setVoter(new VoterPrefix(self::ROLE_PREFIX.'_'.$prefix));
				$RoleVoterDTO->setKey($prefix);
				$this->addVoter($RoleVoterDTO);
			}
		}
		
		return $this->voter;
	}
	
	
	public function addVoter(Role\Voter\RoleVoterDTO $voter) : void
	{
		$this->voter->add($voter);
	}
	
	
	/** Метод для инициализации и маппинга сущности на DTO в коллекции  */
	public function getVoterClass() : Role\Voter\RoleVoterDTO
	{
		return new Role\Voter\RoleVoterDTO();
	}
	
}

