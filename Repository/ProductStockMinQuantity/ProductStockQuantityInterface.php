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

namespace BaksDev\Products\Stocks\Repository\ProductStockMinQuantity;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

interface ProductStockQuantityInterface
{

    public function profile(UserProfileUid $profile): self;

    public function product(ProductUid $product): self;

    public function offerConst(?ProductOfferConst $offer): self;

    public function variationConst(?ProductVariationConst $variation): self;

    public function modificationConst(?ProductModificationConst $modification): self;


    /**
     * Метод возвращает одно место складирования продукции с минимальным количеством в наличии без учета резерва, но с наличием резерва
     */
    public function findOneByTotalMin(): ?ProductStockTotal;

    /**
     * Метод возвращает место складирования продукции с максимальным количеством в наличии без учета резерва
     */
    public function findOneByTotalMax(): ?ProductStockTotal;

    /**
     * Метод возвращает место складирования продукции с максимальным количеством в наличии и резервом > 0
     */
    public function findOneByReserveMax(): ?ProductStockTotal;

    /**
     * Метод возвращает одно место складирования продукции с минимальным количеством в наличии c учетом разности резерва
     */
    public function findOneBySubReserve(): ?ProductStockTotal;

}