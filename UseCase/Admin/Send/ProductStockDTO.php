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

namespace BaksDev\Products\Stocks\UseCase\Admin\Send;

use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusMoving;
use BaksDev\Products\Stocks\UseCase\Admin\Send\Invariable\ProductStockInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Send\Move\ProductStockMoveDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Send\Orders\ProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Send\Products\ProductStockProductDTO;
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

    /** Склад назначения при перемещении */
    #[Assert\Valid]
    private Move\ProductStockMoveDTO $move;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;

    /** Идентификатор заказа на сборку */
    private ProductStockOrderDTO $ord;

    private ProductStockInvariableDTO $invariable;

    public function __construct()
    {
        $this->status = new ProductStockStatus(new ProductStockStatusMoving());
        $this->product = new ArrayCollection();

        $this->move = new ProductStockMoveDTO();
        $this->ord = new ProductStockOrderDTO();

        $this->invariable = new ProductStockInvariableDTO();
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
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(ProductStockProductDTO $product): void
    {
        $containsProducts = $this->product->filter(function(ProductStockProductDTO $element) use ($product) {

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

    public function removeProduct(ProductStockProductDTO $product): void
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

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }


    /** Склад назначения при перемещении */
    public function getMove(): ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(ProductStockMoveDTO $move): void
    {
        $this->move = $move;
    }

    /** Идентификатор заказа на сборку */

    public function getOrd(): ProductStockOrderDTO
    {
        return $this->ord;
    }


    public function setOrd(ProductStockOrderDTO $ord): void
    {
        $this->ord = $ord;
    }

    public function getInvariable(): ProductStockInvariableDTO
    {
        return $this->invariable;
    }
}
