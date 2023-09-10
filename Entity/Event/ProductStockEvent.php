<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Entity\Event;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Move\ProductStockMove;
use BaksDev\Products\Stocks\Entity\Orders\ProductStockOrder;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

// ProductStockEvent

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_event')]
class ProductStockEvent extends EntityEvent
{
    public const TABLE = 'product_stock_event';

    /** ID */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockEventUid::TYPE)]
    private ProductStockEventUid $id;

    /** ID ProductStock */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductStockUid::TYPE, nullable: false)]
    private ?ProductStockUid $main = null;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    #[ORM\Column(type: Types::STRING)]
    private string $number;

    /** Статус заявки */
    #[Assert\NotBlank]
    #[ORM\Column(type: ProductStockStatus::TYPE)]
    protected ProductStockStatus $status;

    /** Коллекция продукции в заявке */
    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: ProductStockProduct::class, cascade: ['all'])]
    protected Collection $product;

    /** Модификатор */
    #[ORM\OneToOne(mappedBy: 'event', targetEntity: ProductStockModify::class, cascade: ['all'])]
    private ProductStockModify $modify;

    /** Профиль пользователя */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: UserProfileUid::TYPE)]
    private UserProfileUid $profile;

    /** Константа склада */
    #[Assert\Uuid]
    #[ORM\Column(type: ContactsRegionCallConst::TYPE, nullable: true)]
    private ?ContactsRegionCallConst $warehouse = null;

    /** Склад назначения (при перемещении) */
    #[ORM\OneToOne(mappedBy: 'event', targetEntity: ProductStockMove::class, cascade: ['all'])]
    private ?ProductStockMove $move = null;

    /** ID Заказа на сборку */
    #[ORM\OneToOne(mappedBy: 'event', targetEntity: ProductStockOrder::class, cascade: ['all'])]
    private ?ProductStockOrder $ord = null;
    

    /** Комментарий */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->id = new ProductStockEventUid();
        $this->modify = new ProductStockModify($this);
    }

    public function __clone()
    {
        $this->id = new ProductStockEventUid();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ProductStockEventUid
    {
        return $this->id;
    }

    public function setMain(ProductStockUid|ProductStock $main): void
    {
        $this->main = $main instanceof ProductStock ? $main->getId() : $main;
    }

    public function getMain(): ?ProductStockUid
    {
        return $this->main;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Идентификатор склада.
     */
    public function getWarehouse(): ?ContactsRegionCallConst
    {
        return $this->warehouse;
    }

    /**
     * Идентификатор заказа.
     */
    public function getOrder(): ?OrderUid
    {
        return $this->ord?->getOrder();
    }

    /**
     * Идентификатор заказа при перемещении.
     */
    public function getMoveOrder(): ?OrderUid
    {
        return $this->move?->getOrder();
    }

    /**
     * Идентификатор целевого склада при перемещении.
     */
    public function getMoveDestination(): ?ContactsRegionCallConst
    {
        return $this->move?->getDestination();
    }


    public function getMove(): ProductStockMove
    {
        return $this->move;
    }


    /**
     * Идентификатор ответственного.
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Product.
     */
    public function getProduct(): Collection
    {
        return $this->product;
    }

    public function getDto($dto): mixed
    {
        if ($dto instanceof ProductStockEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if ($dto instanceof ProductStockEventInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}
