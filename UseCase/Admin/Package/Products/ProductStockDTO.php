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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package\Products;

use BaksDev\Orders\Order\Entity\Products\OrderProductInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProductInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see ProductStockProduct
 * @see OrderProduct
 */
final class ProductStockDTO implements ProductStockProductInterface, OrderProductInterface
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

    /** Стоимость и количество в заказе */
    #[Assert\Valid]
    private Price\PackageOrderPriceDTO $price;

    /** Количество в заявке */
    private int $total = 0;


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

    /** Стоимость и количество */
    public function getPrice() : Price\PackageOrderPriceDTO
    {
        return $this->price;
    }

    public function setPrice(Price\PackageOrderPriceDTO $price) : void
    {
        $this->price = $price;
    }

    /** Количество в заявке */
    public function getTotal(): int
    {
        /* Присваиваем значение из заказа */
        $this->total = $this->getPrice()->getTotal();
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        //$this->getPrice()->setTotal($total);
        $this->total = $total;
    }

}
