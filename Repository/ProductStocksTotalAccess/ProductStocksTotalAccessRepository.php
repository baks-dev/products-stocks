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

namespace BaksDev\Products\Stocks\Repository\ProductStocksTotalAccess;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use InvalidArgumentException;
use Symfony\Contracts\Service\ResetInterface;

final class ProductStocksTotalAccessRepository implements ProductStocksTotalAccessInterface, ResetInterface
{

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    private UserProfileUid|false $profile = false;

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function forProfile(UserProfile|UserProfileUid $profile): self
    {

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function forProduct(Product|ProductUid $product): self
    {
        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function forOfferConst(ProductOfferConst|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        $this->offer = $offer;

        return $this;
    }

    public function forVariationConst(ProductVariationConst|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        $this->variation = $variation;

        return $this;
    }

    public function forModificationConst(ProductModificationConst|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        $this->modification = $modification;

        return $this;
    }

    /**
     * Метод возвращает общее количество ДОСТУПНОЙ продукции на складе (за вычетом резерва)
     */
    public function get(): int
    {
        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Invalid Argument product');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('(SUM(stock.total) - SUM(stock.reserve))')
            ->from(ProductStockTotal::class, 'stock')
            ->andWhere('stock.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);

        $dbal
            ->andWhere('stock.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->UserProfileTokenStorage->getUser(),
                type: UserUid::TYPE,
            );

        /**
         * Поиск только по определенному профилю пользователя
         */
        if($this->profile instanceof UserProfileUid)
        {
            $dbal
                ->andWhere('stock.profile = :profile')
                ->setParameter(
                    key: 'profile',
                    value: $this->UserProfileTokenStorage->getProfile(),
                    type: UserProfileUid::TYPE,
                );
        }

        if($this->offer instanceof ProductOfferConst)
        {
            $dbal
                ->andWhere('stock.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE,
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
                    type: ProductVariationConst::TYPE,
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
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }

        return max($dbal->fetchOne(), 0);
    }

    /**
     * Сбрасываем свойства после результата
     */
    public function reset(): void
    {
        $this->product =
        $this->profile =
        $this->offer =
        $this->variation =
        $this->modification =
            false;
    }
}
