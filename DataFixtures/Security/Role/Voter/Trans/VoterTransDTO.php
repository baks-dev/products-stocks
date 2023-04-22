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

namespace BaksDev\Products\Stocks\DataFixtures\Security\Role\Voter\Trans;

use BaksDev\Users\Groups\Role\Entity\Voters\Trans\VoterTransInterface;
use BaksDev\Core\Type\Locale\Locale;

final class VoterTransDTO implements VoterTransInterface
{
	
	/** Локаль */
	private Locale $local;
	
	/** Название */
	private string $name;
	
	
	/**
	 * @return Locale
	 */
	public function getLocal() : Locale
	{
		return $this->local;
	}
	
	
	/**
	 * @param Locale $local
	 */
	public function setLocal(Locale $local) : void
	{
		$this->local = $local;
	}
	
	
	/**
	 * @return string
	 */
	public function getName() : string
	{
		return $this->name;
	}
	
	
	/**
	 * @param string $name
	 */
	public function setName(string $name) : void
	{
		$this->name = $name;
	}
	
}

