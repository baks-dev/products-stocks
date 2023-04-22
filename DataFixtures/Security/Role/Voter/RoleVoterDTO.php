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

namespace BaksDev\Products\Stocks\DataFixtures\Security\Role\Voter;

use BaksDev\Users\Groups\Role\Entity\Voters\RoleVoterInterface;
use BaksDev\Users\Groups\Role\Type\VoterPrefix\VoterPrefix;
use BaksDev\Core\Type\Locale\Locale;
use Doctrine\Common\Collections\ArrayCollection;

final class RoleVoterDTO implements RoleVoterInterface
{
	
	public const VOTERS = [

        'INCOMING' => [
            'ru' => 'Приход и расход',
            'en' => 'income and expense'
        ],

        'ADD' => [
            'ru' => 'Добавить заявку',
            'en' => 'Applications and acceptance'
        ],

        'ACCEPT' => [
            'ru' => 'Закрыть заявку',
            'en' => 'Applications and acceptance'
        ],
		
		'EDIT' => [
			'ru' => 'Редактировать',
			'en' => 'Edit',
		],
		
		'DELETE' => [
			'ru' => 'Удалить',
			'en' => 'Delete',
		],
		
		'RECYCLEBIN' => [
			'ru' => 'Корзина',
			'en' => 'Recyclebin',
		],
		
		'SETTINGS' => [
			'ru' => 'Настройки',
			'en' => 'Settings',
		],
		
		'REMOVE' => [
			'ru' => 'Очистить',
			'en' => 'Remove',
		],
		
		'RESTORE' => [
			'ru' => 'Восстановить',
			'en' => 'Restore',
		],
		
		'HISTORY' => [
			'ru' => 'История',
			'en' => 'History',
		],
		
		'ROLLBACK' => [
			'ru' => 'Откатить',
			'en' => 'Rollback',
		],
	
	];
	
	/** Вспомогательное свойство  */
	private string $key;
	
	/** Префикс правила */
	private VoterPrefix $voter;
	
	/** Настройки локали */
	private ArrayCollection $translate;
	
	
	public function __construct() { $this->translate = new ArrayCollection(); }
	
	
	/* VOTER */
	
	/**
	 * @return VoterPrefix
	 */
	public function getVoter() : VoterPrefix
	{
		return $this->voter;
	}
	
	
	/**
	 * @param VoterPrefix $voter
	 */
	public function setVoter(VoterPrefix $voter) : void
	{
		$this->voter = $voter;
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
			$TransFormDTO = new Trans\VoterTransDTO();
			$TransFormDTO->setLocal($locale);
			$TransFormDTO->setName(self::VOTERS[$this->key][$locale->getValue()]);
			
			$this->addTranslate($TransFormDTO);
		}
		
		return $this->translate;
	}
	
	
	public function addTranslate(Trans\VoterTransDTO $translate) : void
	{
		$this->translate->add($translate);
	}
	
	
	/** Метод для инициализации и маппинга сущности на DTO в коллекции  */
	public function getTranslateClass() : Trans\VoterTransDTO
	{
		return new Trans\VoterTransDTO();
	}
	
	/* KEY */
	
	/**
	 * @param string $key
	 */
	public function setKey(string $key) : void
	{
		$this->key = $key;
	}
	
}

