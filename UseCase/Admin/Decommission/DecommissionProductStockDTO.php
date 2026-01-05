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

namespace BaksDev\Products\Stocks\UseCase\Admin\Decommission;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Type\UidType\Uid;
use BaksDev\Orders\Order\Entity\Event\OrderEventInterface;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariableInterface;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusDecommission;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Invariable\DecommissionProductStockInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Orders\DecommissionProductStockOrderDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class DecommissionProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    private ?ProductStockEventUid $id = null;

    /** Статус заявки - УПАКОВКА */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Постоянная величина */
    #[Assert\Valid]
    private DecommissionProductStockInvariableDTO $invariable;

    /** Идентификатор заказа на сборку */
    private DecommissionProductStockOrderDTO $ord;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct()
    {
        $this->status = new ProductStockStatus(ProductStockStatusDecommission::class);
        $this->product = new ArrayCollection();
        $this->ord = new DecommissionProductStockOrderDTO();
        $this->invariable = new DecommissionProductStockInvariableDTO();
    }

    public function getEvent(): ?Uid
    {
        return null;
    }

    public function setId(ProductStockEventUid|OrderEventUid $id): self
    {
        if($id instanceof ProductStockEventUid)
        {
            $this->id = $id;
        }

        return $this;
    }


    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function addProduct(Products\DecommissionProductStockProductDTO $product): void
    {
        $this->product->add($product);
    }

    public function removeProduct(Products\DecommissionProductStockProductDTO $product): void
    {
        $this->product->removeElement($product);
    }

    /** Комментарий */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    public function setNumber(string $number): self
    {
        $this->invariable->setNumber($number);
        return $this;
    }

    /** Идентификатор заказа на сборку */

    public function getOrd(): DecommissionProductStockOrderDTO
    {
        return $this->ord;
    }

    public function setOrd(DecommissionProductStockOrderDTO $ord): self
    {
        $this->ord = $ord;
        return $this;
    }

    /**
     * Invariable
     */
    public function getInvariable(): DecommissionProductStockInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(DecommissionProductStockInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }
}
