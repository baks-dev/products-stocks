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

namespace BaksDev\Products\Stocks\UseCase\Admin\Decommission;

use BaksDev\Orders\Order\Entity\Event\OrderEventInterface;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusDecommission;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Invariable\NewDecommissionOrderInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Decommission\Products\NewDecommissionOrderProductDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see OrderEvent */
final class NewDecommissionOrderDTO implements OrderEventInterface
{
    /** Идентификатор события */
    #[Assert\Uuid]
    private ?OrderEventUid $id = null;

    /** Коллекция продукции в заказе */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Постоянная величина */
    #[Assert\Valid]
    private NewDecommissionOrderInvariableDTO $invariable;

    /** Статус заказа */
    #[Assert\NotBlank]
    private OrderStatus $status;

    /** Нужно ли списывать честные знаки */
    private ?bool $signs;

    /** Идентификатор складского места на складе */
    private ProductStockTotalUid|false $storage = false;

    public function __construct()
    {
        $this->invariable = new NewDecommissionOrderInvariableDTO();

        $this->product = new ArrayCollection();
        $this->status = new OrderStatus(OrderStatusDecommission::STATUS);
    }


    public function getEvent(): ?OrderEventUid
    {
        return $this->id;
    }

    public function resetId(): void
    {
        $this->id = null;
    }

    /**
     * Коллекция продукции в заказе
     *
     * @return ArrayCollection<int, NewDecommissionOrderProductDTO>
     */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(NewDecommissionOrderProductDTO $product): void
    {
        if(!$this->product->contains($product))
        {
            $this->product->add($product);
        }
    }

    public function removeProduct(NewDecommissionOrderProductDTO $product): void
    {
        $this->product->removeElement($product);
    }

    /** Статус заказа */
    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    /**
     * Invariable
     */
    public function getInvariable(): NewDecommissionOrderInvariableDTO
    {
        return $this->invariable;
    }

    public function isSigns(): bool
    {
        return true === $this->signs;
    }

    public function setSigns(bool $signs): void
    {
        $this->signs = $signs;
    }

    public function setInvariable(NewDecommissionOrderInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    public function getStorage(): ProductStockTotalUid|false
    {
        return $this->storage;
    }

    public function setStorage(ProductStockTotalUid|false $storage): self
    {
        $this->storage = $storage;
        return $this;
    }
}