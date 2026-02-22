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

namespace BaksDev\Products\Stocks\Repository\ProductStockSettingsEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEvent;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\Profile\ProductStockSettingsProfile;
use BaksDev\Products\Stocks\Entity\StocksSettings\ProductStockSettings;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

/**
 * Получение Event настройки порога ProductStockSettings
 */
final class ProductStockSettingsEventByProfile implements ProductStockSettingsEventByProfileInterface
{

    private UserProfileUid|false $profile = false;

    /**
     * Profile
     */
    public function profile(UserProfile|UserProfileUid|string $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function __construct(
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
        private readonly ORMQueryBuilder $ORMQueryBuilder
    ) {}

    public function getSettingEvent(): ProductStockSettingsEvent|false
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $qb->from(ProductStockSettings::class, 'settings');

        $qb
            ->select('event')
            ->join(
                ProductStockSettingsEvent::class,
                'event',
                'WITH',
                'settings.event = event.id'
            );

        $qb
            ->join(
                ProductStockSettingsProfile::class,
                'profile',
                'WITH',
                'profile.event = settings.event
                      AND
                      profile.value = :profile',
            )
            ->setParameter(
                key: 'profile',
                value: ($this->profile instanceof UserProfileUid) ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE,
            );

        $result = $qb->getOneOrNullResult();
        
        return $result ?? false;

    }

}