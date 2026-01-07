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

namespace BaksDev\Products\Stocks\Messenger\Orders\EditProductStockProduct;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final readonly class EditProductStockProductMessage
{
    private string $id;

    private string $profile;

    private string $current;

    private string $number;

    public function __construct(
        OrderUid $id,
        UserProfileUid $profile,
        UserUid $current,
        string $number,
    )
    {
        $this->id = (string) $id;

        $this->profile = (string) $profile;
        $this->current = (string) $current;
        $this->number = $number;
    }

    /**
     * Идентификатор заказа
     */
    public function getOrderId(): OrderUid
    {
        return new OrderUid($this->id);
    }

    /**
     * Идентификатор профиля
     */
    public function getUserProfile(): UserProfileUid
    {
        return new UserProfileUid($this->profile);
    }

    /**
     * Идентификатор текущего пользователя
     */
    public function getCurrentUser(): UserUid
    {
        return new UserUid($this->current);
    }

    /**
     * Номер заказа
     */
    public function getNumber(): string
    {
        return $this->number;
    }
}
