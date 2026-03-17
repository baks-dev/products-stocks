<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Entity\Quantity\Approve;

use BaksDev\Core\Entity\EntityState;
use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEvent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductStockQuantityApprove */
#[ORM\Entity]
#[ORM\Table(name: 'product_stock_quantity_approve')]
class ProductStockQuantityApprove extends EntityState
{
    /** Связь на событие */
    #[Assert\NotBlank]
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: ProductStockQuantityEvent::class, inversedBy: 'approve')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockQuantityEvent $event;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $value = true;

    public function __construct(ProductStockQuantityEvent $event)
    {
        $this->event = $event;
    }

    public function __toString(): string
    {
        return (string) $this->event;
    }

    public function isValue(): bool
    {
        return $this->value === true;
    }

    public function getDto($dto): mixed
    {
        if($dto instanceof ProductStockQuantityApproveInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockQuantityApproveInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setValue(bool $value): self
    {
        $this->value = $value;
        return $this;
    }
}