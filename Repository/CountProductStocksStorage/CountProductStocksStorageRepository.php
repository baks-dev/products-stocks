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

namespace BaksDev\Products\Stocks\Repository\CountProductStocksStorage;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;


final class CountProductStocksStorageRepository implements CountProductStocksStorageInterface
{
    private UserProfileUid|string $profile;

    private ProductUid|string $product;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfileUid|string $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function forProduct(ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function forOffer(ProductOfferConst|string|false|null $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function forVariation(ProductVariationConst|string|false|null $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function forModification(ProductModificationConst|string|false|null $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }

    /**
     * Метод возвращает количество мест складирования продукции
     */
    public function count(): int
    {
        if(false === ($this->profile instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Invalid Argument Product');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('COUNT(*)')
            ->from(ProductStockTotal::class, 'stock');

        $dbal
            ->andWhere('stock.profile = :profile')
            ->setParameter('profile', $this->profile, UserProfileUid::TYPE);

        $dbal
            ->andWhere('stock.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE);

        if($this->offer instanceof ProductOfferConst)
        {
            $dbal
                ->andWhere('stock.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE
                );
        }
        else
        {
            $dbal->andWhere('stock.offer IS NULL');
        }

        if($this->variation instanceof ProductVariationConst)
        {
            $dbal
                ->andWhere('stock.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE
                );
        }
        else
        {
            $dbal->andWhere('stock.variation IS NULL');
        }

        if($this->modification instanceof ProductModificationConst)
        {
            $dbal
                ->andWhere('stock.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE
                );
        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }

        return $dbal->fetchOne();
    }
}