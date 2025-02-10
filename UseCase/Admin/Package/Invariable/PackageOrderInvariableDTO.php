<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package\Invariable;

use BaksDev\Orders\Order\Entity\Invariable\OrderInvariableInterface;
use BaksDev\Products\Stocks\Entity\Stock\Invariable\ProductStocksInvariableInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see OrderInvariable */
final class PackageOrderInvariableDTO implements OrderInvariableInterface, ProductStocksInvariableInterface
{
    /**
     * ID пользователя ответственного
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserUid $usr;

    /**
     * ID профиля ответственного
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;


    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /**
     * Usr
     */
    public function getUsr(): ?UserUid
    {
        return $this->usr;
    }

    public function setUsr(?UserUid $usr): self
    {
        if(is_null($usr))
        {
            return $this;
        }

        if(!new ReflectionProperty(self::class, 'usr')->isInitialized($this))
        {
            $this->usr = $usr;
        }

        return $this;
    }

    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * Number
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }

}
