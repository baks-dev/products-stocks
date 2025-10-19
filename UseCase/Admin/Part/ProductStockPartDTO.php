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

namespace BaksDev\Products\Stocks\UseCase\Admin\Part;

use BaksDev\Core\Type\UidType\Uid;
use BaksDev\Products\Stocks\Entity\Stock\Event\Part\ProductStockPartInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockPart */
final class ProductStockPartDTO implements ProductStockPartInterface
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    /** Значение свойства */
    #[Assert\NotBlank]
    private ?ProductStockPartUid $value = null;

    public function __construct(ProductStockEvent|ProductStockEventUid $id)
    {

        $this->id = $id instanceof ProductStockEvent ? $id->getId() : $id;
    }

    public function getProductStockEventId(): ProductStockEventUid
    {
        return $this->id;
    }

    public function setValue(?ProductStockPartUid $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): ?ProductStockPartUid
    {
        return $this->value;
    }
}