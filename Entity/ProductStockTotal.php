<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Entity;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// ProductStockTotal

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_total')]
final class ProductStockTotal
{
    public const TABLE = 'product_stock_total';

    /** ID  */
    #[ORM\Id]
    #[ORM\Column(type: ProductStockTotalUid::TYPE)]
    private ProductStockTotalUid $id;

    /** ID склада */
    #[ORM\Column(type: ContactsRegionCallConst::TYPE)]
    private ContactsRegionCallConst $warehouse;

    /** ID продукта */
    #[ORM\Column(type: ProductUid::TYPE)]
    private ProductUid $product;
    
    /** Постоянный уникальный идентификатор ТП */
    #[ORM\Column(type: ProductOfferConst::TYPE, nullable: true)]
    private ?ProductOfferConst $offer;

    /** Постоянный уникальный идентификатор варианта */
    #[ORM\Column(type: ProductVariationConst::TYPE, nullable: true)]
    private ?ProductVariationConst $variation;

    /** Постоянный уникальный идентификатор модификации */
    #[ORM\Column(type: ProductModificationConst::TYPE, nullable: true)]
    private ?ProductModificationConst $modification;

    /** Общее количество на данном складе */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $total = 0;

    /** Зарезервированно на данном складе */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $reserve = 0;

    public function __construct(
        ContactsRegionCallConst   $warehouse,
        ProductUid                $product,
        ?ProductOfferConst        $offer,
        ?ProductVariationConst    $variation,
        ?ProductModificationConst $modification
    ) {
        $this->id = new ProductStockTotalUid();
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->offer = $offer;
        $this->variation = $variation;
        $this->modification = $modification;
    }

    /** Количество */

    // Увеличиваем количество
    public function addTotal(int $total): void
    {
        $this->total += $total;
    }

    // Уменьшаем количество
    public function subTotal(int $total): void
    {
        $this->total -= $total;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /** Резервирование */

    // Увеличиваем количество
    public function addReserve(int $reserve): void
    {
        $this->reserve += $reserve;
    }

    // Уменьшаем количество
    public function subReserve(int $reserve): void
    {
        $this->reserve -= $reserve;
        
        if ($this->reserve < 0)
        {
            $this->reserve = 0;
        }

    }

    public function getReserve(): int
    {
        return $this->reserve;
    }


}
