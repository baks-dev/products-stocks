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

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialStockEvent */
final class ProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    private ?ProductStockEventUid $id = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /** Статус заявки - ПЕРЕМЕЩЕНИЕ */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    //    /** Константа Целевого склада */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private ?ContactsRegionCallConst $warehouse = null;

    /** Склад назначения при перемещении */
    #[Assert\Valid]
    private Move\ProductStockMoveDTO $move;

    //    /** Константа склада назначения при перемещении */
    //    #[Assert\NotBlank]
    //    #[Assert\Uuid]
    //    private ?ContactsRegionCallConst $destination = null;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    /** Идентификатор заказа на сборку */
    private Orders\ProductStockOrderDTO $ord;

    public function __construct()
    {
        $this->status = new ProductStockStatus(new ProductStockStatus\ProductStockStatusMoving());
        $this->product = new ArrayCollection();
        //$this->number = time().random_int(100, 999);

        $this->number = number_format(microtime(true) * 100, 0, '.', '.');
        $this->move = new Move\ProductStockMoveDTO();
        $this->ord = new Orders\ProductStockOrderDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }

    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        /** Сбрасываем идентификатор заявки */
        $this->number = number_format(microtime(true) * 100, 0, '.', '.');
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(Products\ProductStockDTO $product): void
    {
        $containsProducts = $this->product->filter(function(Products\ProductStockDTO $element) use ($product) {

            return
                $element->getProduct()->equals($product->getProduct()) &&
                $element->getOffer()?->equals($product->getOffer()) &&
                $element->getVariation()?->equals($product->getVariation()) &&
                $element->getModification()?->equals($product->getModification());
        });


        if($containsProducts->isEmpty())
        {
            $this->product->add($product);
        }
    }

    public function removeProduct(Products\ProductStockDTO $product): void
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

    /** Ответственное лицо (Профиль пользователя) */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): void
    {
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

    //    /** Константа склада назначения при перемещении */
    //    public function getDestination(): ?ContactsRegionCallConst
    //    {
    //        return $this->destination;
    //    }
    //
    //    public function setDestination(?ContactsRegionCallConst $destination): void
    //    {
    //        $this->destination = $destination;
    //    }

    /** Склад назначения при перемещении */
    public function getMove(): Move\ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(Move\ProductStockMoveDTO $move): void
    {
        $this->move = $move;
    }

    /** Идентификатор заказа на сборку */

    public function getOrd(): Orders\ProductStockOrderDTO
    {
        return $this->ord;
    }


    public function setOrd(Orders\ProductStockOrderDTO $ord): void
    {
        $this->ord = $ord;
    }
}
