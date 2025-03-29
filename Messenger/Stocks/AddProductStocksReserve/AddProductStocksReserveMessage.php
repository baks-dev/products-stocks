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

namespace BaksDev\Products\Stocks\Messenger\Stocks\AddProductStocksReserve;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AddProductStocksReserveMessage
{
    private readonly string $profile;
    private readonly string $product;
    private readonly string $stock;

    private readonly ?string $offer;
    private readonly ?string $variation;
    private readonly ?string $modification;

    private int $iterate;

    public function __construct(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer,
        ProductVariationConst|false|null $variation,
        ProductModificationConst|false|null $modification,
        ProductStockUid $stock,
    )
    {
        $this->profile = (string) $profile;
        $this->product = (string) $product;
        $this->stock = (string) $stock;

        $this->offer = $offer ? (string) $offer : null;
        $this->variation = $variation ? (string) $variation : null;
        $this->modification = $modification ? (string) $modification : null;

    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return new UserProfileUid($this->profile);
    }

    /**
     * Product
     */
    public function getProduct(): ProductUid
    {
        return new ProductUid($this->product);
    }

    /**
     * Offer
     */
    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer ? new ProductOfferConst($this->offer) : null;
    }

    /**
     * Variation
     */
    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation ? new ProductVariationConst($this->variation) : null;
    }

    /**
     * Modification
     */
    public function getModification(): ?ProductModificationConst
    {
        return $this->modification ? new ProductModificationConst($this->modification) : null;
    }

    /**
     * Iterator
     */
    public function getIterate(): string
    {
        return md5($this->iterate.$this->stock);
    }

    public function setIterate(int $iterate): self
    {
        $this->iterate = $iterate;
        return $this;
    }
}
