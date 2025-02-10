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

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialStockEvent */
final class WarehouseProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    //    /** Склад */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private ?ContactsRegionCallConst $warehouse = null;

    //    /** Целевой склад при перемещении */
    //    #[Assert\Uuid]
    //    private ?UserProfileUid $destination = null;

    //    /** Ответственное лицо (Профиль пользователя) */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private UserProfileUid $profile;

    /** Статус заявки - ОТПАРВЛЕН НА СКЛАД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Комментарий */
    private ?string $comment = null;

    //    /** Вспомогательные свойства - для выбора доступных профилей */
    //    private readonly UserUid $usr;

    /** Коллекция перемещения  */
    #[Assert\Valid]
    private ?Move\ProductStockMoveDTO $move;

    /** Фиксация заявки пользователем  */
    #[Assert\IsNull]
    private readonly ?UserProfileUid $fixed;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;


    private Invariable\WarehouseProductStocksInvariableDTO $invariable;


    public function __construct(User|UserUid $usr)
    {
        //$this->usr = $usr instanceof User ? $usr->getId() : $usr;
        $this->status = new ProductStockStatus(ProductStockStatusWarehouse::class);
        $this->fixed = null;
        $this->product = new ArrayCollection();
        $this->invariable = new Invariable\WarehouseProductStocksInvariableDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
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

    //    /** Профиль назначения */
    //    public function getProfile(): UserProfileUid
    //    {
    //        return $this->profile;
    //    }
    //
    //    public function setProfile(UserProfileUid $profile): self
    //    {
    //        $this->profile = $profile;
    //        return $this;
    //    }


    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    /**
     * Move
     */
    public function getMove(): ?Move\ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(?Move\ProductStockMoveDTO $move): self
    {
        $this->move = $move;
        return $this;
    }


    /** Фиксация заявки пользователем  */
    public function getFixed(): ?UserProfileUid
    {
        return $this->fixed;
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

    public function addProduct(Products\ProductStockDTO $product): void
    {
        $this->product->add($product);
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\WarehouseProductStocksInvariableDTO
    {
        return $this->invariable;
    }
}
