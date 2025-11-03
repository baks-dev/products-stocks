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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse;

use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusWarehouse;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Invariable\WarehouseProductStocksInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Move\ProductStockMoveDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Products\ProductStockDTO;
use BaksDev\Products\Supply\UseCase\Admin\ProductStock\ProductStockSupplyDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialStockEvent */
final class WarehouseProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\Uuid]
    private readonly ?ProductStockEventUid $id;

    /** Статус заявки - ОТПАРВЛЕН НА СКЛАД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Комментарий */
    private ?string $comment = null;

    /** Коллекция перемещения  */
    #[Assert\Valid]
    private ?ProductStockMoveDTO $move;

    /** Фиксация заявки пользователем  */
    #[Assert\IsNull]
    private readonly ?UserProfileUid $fixed;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    private WarehouseProductStocksInvariableDTO $invariable;

    private ?ProductStockSupplyDTO $supply = null;

    public function __construct()
    {
        $this->status = new ProductStockStatus(ProductStockStatusWarehouse::class);
        $this->fixed = null;
        $this->product = new ArrayCollection();
        $this->invariable = new WarehouseProductStocksInvariableDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    /** Для инстанса нового объекта */
    public function newId(): void
    {
        if(false === (new \ReflectionProperty(self::class, 'id'))->isInitialized($this))
        {
            $this->id = null;
        }
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

    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Move
     */
    public function getMove(): ?ProductStockMoveDTO
    {
        return $this->move;
    }

    public function setMove(?ProductStockMoveDTO $move): self
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

    public function addProduct(ProductStockDTO $product): void
    {
        $this->product->add($product);
    }

    /**
     * Invariable
     */
    public function getInvariable(): WarehouseProductStocksInvariableDTO
    {
        return $this->invariable;
    }

    /**
     * Supply
     */
    public function setSupply(?ProductStockSupplyDTO $supply): WarehouseProductStockDTO
    {
        $this->supply = $supply;
        return $this;
    }

    public function getSupply(): ?ProductStockSupplyDTO
    {
        return $this->supply;
    }
}
