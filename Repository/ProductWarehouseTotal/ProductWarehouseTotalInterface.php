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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseTotal;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

interface ProductWarehouseTotalInterface
{
    /**
     * Метод возвращает доступное количество (с учетом резерва!!!) данной продукции на указанном складе
     */
    public function getProductProfileTotal(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer,
        ProductVariationConst|false|null $variation,
        ProductModificationConst|false|null $modification
    ): int;


    /**
     * Метод возвращает весь резерв данной продукции на указанном складе
     */
    public function getProductProfileReserve(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer,
        ProductVariationConst|false|null $variation,
        ProductModificationConst|false|null $modification
    ): int;

    /**
     * Метод возвращает общее количество (без резерва!!!) данной продукции на указанном складе
     */
    public function getProductProfileTotalNotReserve(
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|false|null $offer = null,
        ProductVariationConst|false|null $variation = null,
        ProductModificationConst|false|null $modification = null
    ): int;

}