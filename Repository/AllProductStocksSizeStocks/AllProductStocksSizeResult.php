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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksSizeStocks;

use BaksDev\Products\Product\Repository\ProductPriceResultInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final readonly class AllProductStocksSizeResult
{
    public function __construct(

        private ?int $stock_total, //" => 1100
        private ?int $stock_reserve, //" => 4
        private ?string $product_offer_value, //" => "15"
        private ?string $product_offer_reference, //" => "tire_radius_field"
        private ?string $product_variation_value, //" => "185"
        private ?string $product_variation_reference, //" => "tire_width_field"
        private ?string $product_modification_value, //" => "55"
        private ?string $product_modification_reference, //" => "tire_profile_field"

    ) {}


    public function getStockTotal(): ?int
    {
        return $this->stock_total ?: 0;
    }


    public function getStockReserve(): ?int
    {
        return $this->stock_reserve ?: 0;
    }

    public function getQuantity(): int
    {
        return $this->getStockTotal() - $this->getStockReserve();
    }


    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value ?: null;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference ?: null;
    }

    /** Variation */

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value ?: null;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference ?: null;
    }

    /** Modification */

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value ?: null;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference ?: null;
    }

    public function getProductOldPrice(): Money|false
    {
        return false;
    }
}