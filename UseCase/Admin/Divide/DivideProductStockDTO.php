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

namespace BaksDev\Products\Stocks\UseCase\Admin\Divide;

use BaksDev\Core\Type\UidType\Uid;
use BaksDev\Orders\Order\Entity\Event\OrderEventInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusDivide;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class DivideProductStockDTO implements ProductStockEventInterface, OrderEventInterface
{
    /** Идентификатор */
    private ?ProductStockEventUid $id = null;

    /**
     * Ответственное лицо (Профиль пользователя)
     * @deprecated Переносится в Invariable
     */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /** Постоянная величина */
    #[Assert\Valid]
    private readonly Invariable\DivideOrderInvariableDTO $invariable;


    /** Статус заявки - УПАКОВКА */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /** Идентификатор заказа на сборку */
    private Orders\DivideProductStockOrderDTO $ord;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct(/*User|UserUid $user*/)
    {
        $this->status = new ProductStockStatus(new ProductStockStatusDivide());
        $this->product = new ArrayCollection();
        $this->number = number_format(microtime(true) * 100, 0, '.', '.');
        $this->ord = new Orders\DivideProductStockOrderDTO();

        $this->invariable = new Invariable\DivideOrderInvariableDTO();

        //        $user = $user instanceof User ? $user->getId() : $user;
        //        $this->invariable->setUsr($user);
    }

    public function getEvent(): ?Uid
    {
        return null;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }

    public function resetId(): void
    {
        $this->id = null;
    }

    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(Products\DivideProductStockProductDTO $product): void
    {
        $this->product->add($product);
    }

    public function removeProduct(Products\DivideProductStockProductDTO $product): void
    {
        $this->product->removeElement($product);
    }

    /** Комментарий */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * Ответственное лицо (Профиль пользователя)
     * @deprecated Переносится в Invariable
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    /** @deprecated Переносится в Invariable */
    public function setProfile(?UserProfileUid $profile): void
    {
        /** Присваиваем постоянную величину  */
        $PackageOrderInvariable = $this->getInvariable();
        $PackageOrderInvariable->setProfile($profile);

        $this->profile = $profile;
    }

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /** Номер заявки */
    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    //    /** Константа Целевого склада */
    //    public function getWarehouse(): ?ContactsRegionCallConst
    //    {
    //        return $this->warehouse;
    //    }
    //
    //    public function setWarehouse(?ContactsRegionCallConst $warehouse): void
    //    {
    //        $this->warehouse = $warehouse;
    //    }


    /** Идентификатор заказа на сборку */

    public function getOrd(): Orders\DivideProductStockOrderDTO
    {
        return $this->ord;
    }


    public function setOrd(Orders\DivideProductStockOrderDTO $ord): void
    {
        $this->ord = $ord;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\DivideOrderInvariableDTO
    {
        return $this->invariable;
    }

}
