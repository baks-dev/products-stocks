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

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving\Move;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Stock\Move\ProductStockMoveInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialStockMove */
final class ProductStockMoveDTO implements ProductStockMoveInterface
{

    //    /** Константа склада назначения при перемещении */
    #[Assert\Uuid]
    private ?UserProfileUid $warehouse = null;

    /** Константа склада назначения при перемещении */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?UserProfileUid $destination = null;

    /** Идентификатор заказа, если перемещение для упаковки заказа */
    #[Assert\Uuid]
    private ?OrderUid $ord = null;

    /**
     * Warehouse
     */
    public function getWarehouse(): ?UserProfileUid
    {
        return $this->warehouse;
    }

    public function setWarehouse(?UserProfileUid $warehouse): self
    {
        $this->warehouse = $warehouse;
        return $this;
    }


    /** Константа склада назначения при перемещении */

    public function getDestination(): ?UserProfileUid
    {
        return $this->destination;
    }

    public function setDestination(?UserProfileUid $destination): void
    {
        $this->destination = $destination;
    }

    /** Идентификатор заказа, если перемещение для упаковки заказа */

    public function getOrd(): ?OrderUid
    {
        return $this->ord;
    }

    public function setOrd(?OrderUid $ord): void
    {
        $this->ord = $ord;
    }
}
