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

namespace BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit;

use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEventInterface;
use BaksDev\Products\Stocks\Type\Quantity\Event\ProductStockQuantityEventUid;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Approve\ProductStockQuantityNewEditApproveDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Comment\ProductStockQuantityNewEditCommentDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Quantity\NewEdit\Invariable\ProductStockQuantityNewEditInvariableDTO;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockQuantityEvent */
final class ProductStockQuantityNewEditDTO implements ProductStockQuantityEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ProductStockQuantityEventUid $id = null;

    #[Assert\Valid]
    private ProductStockQuantityNewEditInvariableDTO $invariable;

    #[Assert\Valid]
    private ProductStockQuantityNewEditCommentDTO $comment;

    #[Assert\Valid]
    private ProductStockQuantityNewEditApproveDTO $approve;

    /** Общее количество на данном складе */
    #[Assert\NotBlank]
    private int $total = 0;

    /** Зарезервировано на данном складе */
    #[Assert\NotBlank]
    private int $reserve = 0;

    public function __construct()
    {
        $this->invariable = new ProductStockQuantityNewEditInvariableDTO();
        $this->approve = new ProductStockQuantityNewEditApproveDTO();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ProductStockQuantityEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockQuantityEventUid $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getInvariable(): ProductStockQuantityNewEditInvariableDTO
    {
        return $this->invariable;
    }

    public function getComment(): ?ProductStockQuantityNewEditCommentDTO
    {
        return $this->comment;
    }

    public function getApprove(): ProductStockQuantityNewEditApproveDTO
    {
        return $this->approve;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getReserve(): int
    {
        return $this->reserve;
    }

    public function setReserve(int $reserve): self
    {
        $this->reserve = $reserve;
        return $this;
    }

    public function setComment(ProductStockQuantityNewEditCommentDTO $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}