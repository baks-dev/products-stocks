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

namespace BaksDev\Products\Stocks\Repository\ProductStocksPickupDetail;

use BaksDev\Core\Type\Field\InputField;

final readonly class ProductStocksPickupDetailResult
{
    public function __construct(
        private int $total,
        private ?string $product_url,
        private ?string $product_name,
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
        private ?string $product_image,
        private ?string $product_image_ext,
        private ?bool $product_image_cdn,
        private ?string $category_name,
        private ?string $category_url,
    ) {}

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProductUrl(): ?string
    {
        return $this->product_url;
    }

    public function getProductName(): ?string
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

    public function getProductOfferReference(): ?InputField
    {
        return new InputField($this->product_offer_reference);
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?InputField
    {
        return new InputField($this->product_variation_reference);
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?InputField
    {
        return new InputField($this->product_modification_reference);
    }

    public function getProductArticle(): ?string
    {
        return $this->product_article;
    }

    public function getProductImage(): ?string
    {
        return $this->product_image;
    }

    public function getProductImageExt(): ?string
    {
        return $this->product_image_ext;
    }

    public function getProductImageCdn(): bool
    {
        return true === $this->product_image_cdn;
    }

    public function getCategoryName(): ?string
    {
        return $this->category_name;
    }

    public function getCategoryUrl(): ?string
    {
        return $this->category_url;
    }
}