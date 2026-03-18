<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Invariable;

use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Stocks\Entity\Quantity\Invariable\ProductStockQuantityInvariableInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

/** @see ProductStockQuantityInvariable */
final class ProductStockQuantityNewEditInvariableDTO implements ProductStockQuantityInvariableInterface
{
    /** ID продукта */
    private ProductInvariableUid $invariable;

    /** ID пользователя */
    private ?UserUid $usr = null;

    /** ID профиля (склад) */
    private UserProfileUid $profile;

    /** Место складирования */
    private ?string $storage = null;

    /** Место с высоким приоритетом */
    private bool $priority = false;

    public function getInvariable(): ProductInvariableUid
    {
        return $this->invariable;
    }

    public function getUsr(): ?UserUid
    {
        return $this->usr;
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function isPriority(): bool
    {
        return $this->priority;
    }

    public function setInvariable(ProductInvariableUid $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    public function setUsr(?UserUid $usr): self
    {
        $this->usr = $usr;
        return $this;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function setStorage(?string $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function setPriority(bool $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
}