<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving\Products;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProductInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ProductStockDTO implements ProductStockProductInterface
{

    /** Продукт */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ProductUid $product;

    /** Торговое предложение */
    #[Assert\Uuid]
    private ?ProductOfferConst $offer = null;

    /** Множественный вариант */
    #[Assert\Uuid]
    private ?ProductVariationConst $variation = null;

    /** Модификация множественного варианта */
    #[Assert\Uuid]
    private ?ProductModificationConst $modification = null;

    /** Количество */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1)]
    private int $total;


    //    /** Склад */
    //    public function getWarehouse(): ContactsRegionCallUid
    //    {
    //        return $this->warehouse;
    //    }
    //
    //    public function setWarehouse(ContactsRegionCallUid $warehouse): void
    //    {
    //        $this->warehouse = $warehouse;
    //    }

    /** Продукт */
    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function setProduct(ProductUid $product): void
    {
        $this->product = $product;
    }

    /** Торговое предложение */
    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }

    public function setOffer(ProductOfferConst $offer): void
    {
        $this->offer = $offer;
    }

    /** Множественный вариант */
    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation;
    }

    public function setVariation(?ProductVariationConst $variation): void
    {
        $this->variation = $variation;
    }

    /** Модификация множественного варианта */
    public function getModification(): ?ProductModificationConst
    {
        return $this->modification;
    }

    public function setModification(?ProductModificationConst $modification): void
    {
        $this->modification = $modification;
    }

    /** Количество */
    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
