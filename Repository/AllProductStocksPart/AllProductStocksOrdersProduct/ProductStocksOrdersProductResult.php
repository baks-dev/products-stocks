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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPart\AllProductStocksOrdersProduct;

use BaksDev\Core\Type\UidType\UidType;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use BaksDev\Products\Stocks\Type\Product\ProductStockCollectionUid;
use JsonException;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStocksOrdersProductResult */
final class ProductStocksOrdersProductResult
{
    private array|null|false $stocks_decode = null;

    private array|null|false $products_decode = null;

    private array|null|false $orders_decode = null;

    public function __construct(

        //private readonly string $mains,
        private readonly string $events, //
        private readonly string $total, // 3
        private readonly string $product_name, // "Triangle AdvanteX TC101"

        private readonly string $product, // "01876b34-ed23-7c18-ba48-9071e8646a08"
        private readonly ?string $offer, // "01876b38-05f1-7b10-9a26-9dda257c59b8"
        private readonly ?string $variation, // "018b1f19-ea74-7301-a9f4-5cc57ae26833"
        private readonly ?string $modification, // "018b1f19-ea74-7301-a9f4-5cc57a8ede3d"


        private readonly string $products,

        private readonly ?string $product_offer_value, // "17"
        private readonly ?string $product_offer_postfix, // null
        private readonly ?string $product_offer_reference, // "tire_radius_field"

        private readonly ?string $product_variation_value, // "235"
        private readonly ?string $product_variation_postfix, // null
        private readonly ?string $product_variation_reference, // "tire_width_field"

        private readonly ?string $product_modification_value, // "60"
        private readonly ?string $product_modification_postfix, // "106W"
        private readonly ?string $product_modification_reference, // "tire_profile_field"


        // "[{"id": "019a2c0b-f756-7401-a644-fb3e5d74e2ca"}, {"id": "019a2c0d-b574-7862-bf08-f55c9a96b0c9"}]"
        private readonly ?string $orders,
        // "[{"id": "019a2c0b-8380-7020-b432-b48685003fb5", "number": "176.167.550.297"}]
        private readonly ?string $stocks_quantity, // "[{"total": 10, "reserve": 3, "storage": "new"}]"
    ) {}

    //    public function getMains(): ?array
    //    {
    //        if(is_null($this->mains))
    //        {
    //            return null;
    //        }
    //
    //        if(false === json_validate($this->mains))
    //        {
    //            return null;
    //        }
    //
    //        return json_decode($this->mains, false, 512, JSON_THROW_ON_ERROR);
    //    }

    public function getTotal(): int
    {

        if(is_null($this->total))
        {
            return 0;
        }

        if(false === json_validate($this->total))
        {
            return 0;
        }

        $totals = json_decode($this->total, false, 512, JSON_THROW_ON_ERROR);

        return array_sum(array_column($totals, 'total')) ?: 0;
    }

    /** Идентификаторы событий складской заявки */
    public function getProductStocksEvents(): array|false
    {
        if(is_null($this->events))
        {
            return false;
        }

        if(false === json_validate($this->events))
        {
            return false;
        }


        return json_decode($this->events, false, 512, JSON_THROW_ON_ERROR);
    }


    public function getProductName(): string
    {
        return $this->product_name;
    }

    public function getProduct(): ProductUid
    {
        return new ProductUid($this->product);
    }


    public function getOfferConst(): ProductOfferConst|false
    {
        return $this->offer ? new ProductOfferConst($this->offer) : false;
    }

    public function getVariationConst(): ProductVariationConst|false
    {
        return $this->variation ? new ProductVariationConst($this->variation) : false;
    }

    public function getModificationConst(): ProductModificationConst|false
    {
        return $this->modification ? new ProductModificationConst($this->modification) : false;
    }

    /** Offer */

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }

    /** Variation */

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }

    /** Modification */

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }


    /**
     * @return array<int, ProductStockCollectionUid>|null
     * @throws JsonException
     */
    public function getProductsCollection(): ?array
    {
        if(is_null($this->products_decode))
        {
            if(is_null($this->products))
            {
                $this->products_decode = false;
                return null;
            }

            if(false === json_validate($this->products))
            {
                $this->products_decode = false;
                return null;
            }

            $decode = json_decode($this->products, false, 512, JSON_THROW_ON_ERROR);

            foreach($decode as $item)
            {
                $this->products_decode[] = new ProductStockCollectionUid($item->id);
            }
        }

        if(false === $this->products_decode)
        {
            return null;
        }

        return $this->products_decode;

    }

    public function getOrdersCollection(): ?array
    {
        if(is_null($this->orders_decode))
        {
            if(is_null($this->orders))
            {
                $this->orders_decode = false;
                return null;
            }

            if(false === json_validate($this->orders))
            {
                $this->orders_decode = false;
                return null;
            }

            $decode = json_decode($this->orders, false, 512, JSON_THROW_ON_ERROR);

            foreach($decode as $order)
            {
                $order->id = new OrderUid($order->id);
            }

            $this->orders_decode = $decode;
        }

        if(false === $this->orders_decode)
        {
            return null;
        }

        return $this->orders_decode;
    }

    /**
     * @throws JsonException
     */
    public function getStocksQuantity(): ?array
    {
        if(is_null($this->stocks_decode))
        {
            if(is_null($this->stocks_quantity))
            {
                $this->stocks_decode = false;
                return null;
            }

            if(false === json_validate($this->stocks_quantity))
            {
                $this->stocks_decode = false;
                return null;
            }

            $this->stocks_decode = json_decode($this->stocks_quantity, true, 512, JSON_THROW_ON_ERROR);
        }

        if(false === $this->stocks_decode)
        {
            return null;
        }

        return $this->stocks_decode;
    }

    public function getIdentifierPart(): ProductStockPartUid
    {
        return new ProductStockPartUid()
            ->stringToUuid($this->orders.$this->stocks_quantity);
    }

}