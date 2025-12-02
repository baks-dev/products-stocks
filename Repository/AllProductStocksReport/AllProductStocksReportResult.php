<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksReport;

use BaksDev\Reference\Money\Type\Money;

final readonly class AllProductStocksReportResult
{
    public function __construct(
        private string $product_id,
        private string $product_name,
        private ?string $stock_comment,
        private ?string $stock_storage,
        private ?string $product_offer_value,
        private ?string $product_offer_postfix,
        private ?string $product_offer_reference,
        private ?string $product_variation_value,
        private ?string $product_variation_postfix,
        private ?string $product_variation_reference,
        private ?string $product_modification_value,
        private ?string $product_modification_postfix,
        private ?string $product_modification_reference,
        private ?string $product_article,
        private ?string $profiles_totals,
        private ?int $product_price,
        private ?int $old_product_price,
    ) {}

    public function getProductId(): string
    {
        return $this->product_id;
    }

    public function getStockComment(): ?string
    {
        return $this->stock_comment;
    }

    public function getStockStorage(): ?string
    {
        return $this->stock_storage;
    }

    public function getProductName(): string
    {
        return $this->product_name;
    }

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

    public function getProductArticle(): ?string
    {
        return $this->product_article;
    }

    public function getProfilesTotals(): ?array
    {
        if(is_null($this->profiles_totals))
        {
            return null;
        }

        if(false === json_validate($this->profiles_totals))
        {
            return null;
        }

        $profilesTotals = json_decode($this->profiles_totals, false, 512, JSON_THROW_ON_ERROR);

        if(null === current($profilesTotals))
        {
            return null;
        }

        return $profilesTotals;
    }

    public function getProductPrice(): ?Money
    {
        return false === empty($this->product_price) ? new Money($this->product_price, true) : null;
    }

    public function getOldProductPrice(): ?Money
    {
        return false === empty($this->old_product_price) ? new Money($this->old_product_price, true) : null;
    }
}