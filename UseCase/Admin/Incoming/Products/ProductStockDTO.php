<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Products;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProductInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockProduct */
final class ProductStockDTO implements ProductStockProductInterface
{
    /** Продукт */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ProductUid $product;

    /** Торговое предложение */
    #[Assert\Uuid]
    private readonly ?ProductOfferConst $offer;

    /** Множественный вариант */
    #[Assert\Uuid]
    private readonly ?ProductVariationConst $variation;

    /** Модификация множественного варианта */
    #[Assert\Uuid]
    private readonly ?ProductModificationConst $modification;

    /** Количество */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1)]
    private readonly int $total;

    /** Место складирования */
    private ?string $storage = null;

    /** Вспомогательные свойства */
    public ?array $detail = null;

    /** Продукт */
    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    //    public function setProduct(ProductUid $product): void
    //    {
    //        $this->product = $product;
    //    }

    /** Торговое предложение */
    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }

    //    public function setOffer(ProductOfferConst $offer): void
    //    {
    //        $this->offer = $offer;
    //    }

    /** Множественный вариант */
    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation;
    }

    //    public function setVariation(?ProductVariationConst $variation): void
    //    {
    //        $this->variation = $variation;
    //    }

    /** Модификация множественного варианта */
    public function getModification(): ?ProductModificationConst
    {
        return $this->modification;
    }

    //    public function setModification(?ProductModificationConst $modification): void
    //    {
    //        $this->modification = $modification;
    //    }

    /** Количество */
    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    /**
     * Storage
     */
    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(?string $storage): self
    {
        $this->storage = $storage;
        return $this;
    }


}
