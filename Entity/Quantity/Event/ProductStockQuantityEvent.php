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

namespace BaksDev\Products\Stocks\Entity\Quantity\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Products\Stocks\Entity\Quantity\Approve\ProductStockQuantityApprove;
use BaksDev\Products\Stocks\Entity\Quantity\Comment\ProductStockQuantityComment;
use BaksDev\Products\Stocks\Entity\Quantity\Invariable\ProductStockQuantityInvariable;
use BaksDev\Products\Stocks\Entity\Quantity\Modify\ProductStockQuantityModify;
use BaksDev\Products\Stocks\Entity\Quantity\ProductStockQuantity;
use BaksDev\Products\Stocks\Type\Quantity\Event\ProductStockQuantityEventUid;
use BaksDev\Products\Stocks\Type\Quantity\Id\ProductStockQuantityUid;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_quantity_event')]
class ProductStockQuantityEvent extends EntityEvent
{
    /** ID */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockQuantityEventUid::TYPE)]
    private ProductStockQuantityEventUid $id;

    /** ID ProductStockQuantity */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductStockQuantityUid::TYPE, nullable: false)]
    private ?ProductStockQuantityUid $main = null;

    /** Invariable */
    #[ORM\OneToOne(
        targetEntity: ProductStockQuantityInvariable::class,
        mappedBy: 'event',
        cascade: ['all'],
        fetch: 'EAGER'
    )]
    private ProductStockQuantityInvariable $invariable;

    /** Модификатор */
    #[ORM\OneToOne(
        targetEntity: ProductStockQuantityModify::class,
        mappedBy: 'event', cascade: ['all'],
        fetch: 'EAGER'
    )]
    private ProductStockQuantityModify $modify;

    /** Comment - комментарий */
    #[ORM\OneToOne(
        targetEntity: ProductStockQuantityComment::class,
        mappedBy: 'event',
        cascade: ['all'],
        fetch: 'EAGER'
    )]
    private ?ProductStockQuantityComment $comment = null;

    /** Approve - одобрено с учетом настройки порога остатков */
    #[ORM\OneToOne(
        targetEntity: ProductStockQuantityApprove::class,
        mappedBy: 'event',
        cascade: ['all'],
        fetch: 'EAGER'
    )]
    private ProductStockQuantityApprove $approve;

    public function __construct()
    {
        $this->id = new ProductStockQuantityEventUid();
        $this->modify = new ProductStockQuantityModify($this);
        $this->comment = new ProductStockQuantityComment($this);
    }

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    /**
     * Id
     */
    public function getId(): ProductStockQuantityEventUid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ProductStockQuantityEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockQuantityEventInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function getMain(): ?ProductStockQuantityUid
    {
        return $this->main;
    }

    public function setMain(ProductStockQuantityUid|ProductStockQuantity $main): self
    {
        $this->main = $main instanceof ProductStockQuantity ? $main->getId() : $main;
        return $this;
    }

    public function setInvariable(ProductStockQuantityInvariable $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    public function getInvariable(): ProductStockQuantityInvariable
    {
        return $this->invariable;
    }

    public function getComment(): ?ProductStockQuantityComment
    {
        return $this->comment;
    }
}
