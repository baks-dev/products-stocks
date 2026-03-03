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

namespace BaksDev\Products\Stocks\Repository\ProductStocksApproveReport;

use BaksDev\Reference\Money\Type\Money;
use DateTimeImmutable;

final readonly class ProductStocksApproveReportResult
{
    public function __construct(
        private ?int $stock_total,
        private ?string $stock_storage,
        private ?int $stock_reserve,

        private string $product_name,
        private string $modify,

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
        private ?int $product_price,

        private string|null $profile_discount = null,
        private string|null $project_discount = null,

    ) {}


    /** Складские остатки */

    public function getStockTotal(): ?int
    {
        return $this->stock_total ?: 0;
    }

    public function getStockStorage(): string
    {
        return $this->stock_storage ?: '-';
    }

    public function getStockReserve(): ?int
    {
        return $this->stock_reserve ?: 0;
    }


    /** Торговое предложение */

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value ?: null;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix ?: null;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference ?: null;
    }

    /** Множественный вариант торгового предложения */

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value ?: null;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix ?: null;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference ?: null;
    }

    /** Модификация множественного варианта торгового предложения */

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value ?: null;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix ?: null;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference ?: null;
    }


    /** Товар */

    public function getProductName(): string
    {
        return $this->product_name;
    }

    public function getProductArticle(): ?string
    {
        return $this->product_article;
    }

    public function getProductPrice(): Money|false
    {
        if(empty($this->product_price))
        {
            return false;
        }

        $price = new Money($this->product_price, true);


        /* Скидка магазина */
        if(false === empty($this->project_discount))
        {
            $price->applyString($this->project_discount);
        }

        /* Скидка пользователя */
        if(false === empty($this->profile_discount))
        {
            $price->applyString($this->profile_discount);
        }

        return $price;
    }

    /** Дата модификации (товара) */
    public function getDateModify(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->modify);
    }

}