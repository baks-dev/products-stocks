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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotalByProduct;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

/**
 * Получаем массив складских остатков (ProductStockTotal) ( указанной продукции) профиля
 */
final class ProductStocksTotalByProductRepository implements ProductStocksTotalByProductInterface
{
    private UserProfileUid|string $profile;

    private ProductUid|string $product;

    private ProductOfferConst|string|null $offer = null;

    private ProductVariationConst|string|null $variation = null;

    private ProductModificationConst|string|null $modification = null;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function profile(UserProfileUid|string $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function product(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function offer(ProductOfferConst|string|false|null $offer): self
    {
        if(empty($offer))
        {
            $this->offer = null;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variation(ProductVariationConst|string|false|null $variation): self
    {
        if(empty($variation))
        {
            $this->variation = null;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modification(ProductModificationConst|string|false|null $modification): self
    {
        if(empty($modification))
        {
            $this->modification = null;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }


    /** Метод возвращает складские остатки ( указанной продукции) профиля */
    public function findAll(): array|false
    {
        if(empty($this->profile))
        {
            throw new InvalidArgumentException('Invalid Argument profile');
        }

        if(empty($this->product))
        {
            throw new InvalidArgumentException('Invalid Argument product');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('stock')
            ->from(ProductStockTotal::class, 'stock');

        $orm
            ->andWhere('stock.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );

        $orm
            ->andWhere('stock.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE
            );


        if($this->offer)
        {
            $orm
                ->andWhere('stock.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('stock.offer IS NULL');
        }

        if($this->variation)
        {
            $orm
                ->andWhere('stock.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('stock.variation IS NULL');
        }

        if($this->modification)
        {
            $orm
                ->andWhere('stock.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('stock.modification IS NULL');
        }


        $result = $orm->getResult();
        return $result ?? false;
    }
}
