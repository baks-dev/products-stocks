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

namespace BaksDev\Products\Stocks\Messenger\Part;


use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;

final class ProductStockPartMessage
{
    private array $stickers = [];

    /**
     * Идентификатор
     */
    private array $orders;

    public function __construct(
        private readonly ProductStockPartUid $part,
        private readonly string $number,
        private readonly ProductUid $product,
        private readonly ProductOfferConst|null|false $offer,
        private readonly ProductVariationConst|null|false $variation,
        private readonly ProductModificationConst|null|false $modification,
    ) {}


    public function setOrders(?array $orders): self
    {
        if(empty($orders))
        {
            $this->orders = [];
            return $this;
        }

        $this->orders = $orders;
        return $this;
    }

    /** @return array{id : OrderUid, number: string} */
    public function getOrders(): array
    {
        return $this->orders;
    }


    public function getStickers(): array
    {
        return $this->stickers;
    }

    public function addSticker(array|null $stickers): self
    {
        if(false === empty($stickers))
        {
            $this->stickers = array_merge_recursive($this->stickers, $stickers);
        }

        return $this;
    }

    public function getPart(): string
    {
        return (string) $this->part;
    }

    public function getPartNumber(): string
    {
        return $this->number;
    }

    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function getOfferConst(): ProductOfferConst|false
    {
        return $this->offer ?: false;
    }

    public function getVariationConst(): ProductVariationConst|false
    {
        return $this->variation ?: false;
    }

    public function getModificationConst(): ProductModificationConst|false
    {
        return $this->modification ?: false;
    }
}
