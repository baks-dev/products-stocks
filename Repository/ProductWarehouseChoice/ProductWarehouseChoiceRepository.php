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

namespace BaksDev\Products\Stocks\Repository\ProductWarehouseChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Repository\UserTokenStorage\UserTokenStorageInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use Generator;
use InvalidArgumentException;

final class ProductWarehouseChoiceRepository implements ProductWarehouseChoiceInterface
{
    private UserUid|false $user = false;

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserTokenStorageInterface $UserTokenStorage
    ) {}

    public function forUser(User|UserUid|null|false $user): self
    {
        if(empty($user))
        {
            $this->user = false;
        }

        if($user instanceof User)
        {
            $user = $user->getId();
        }

        $this->user = $user;

        return $this;
    }

    public function product(ProductUid|null|false $product): self
    {
        if(empty($product))
        {
            $this->product = false;
            return $this;
        }

        $this->product = $product;

        return $this;
    }


    public function offerConst(ProductOfferConst|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        $this->offer = $offer;

        return $this;
    }

    public function variationConst(ProductVariationConst|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;

        }

        $this->variation = $variation;

        return $this;
    }

    public function modificationConst(ProductModificationConst|null|false $modification): self
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
     * Возвращает список складов (профилей пользователя) на которых имеется данный вид продукта
     *
     * @return Generator<UserProfileUid>
     */
    public function fetchWarehouseByProduct(): Generator
    {
        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Необходимо передать все параметры');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        $dbal->from(ProductStockTotal::class, 'stock');

        $dbal
            ->andWhere('stock.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->user instanceof UserUid ? $this->user : $this->UserTokenStorage->getUser(),
                type: UserUid::TYPE,
            );


        $dbal
            ->andWhere('stock.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE);


        $dbal->andWhere('(stock.total - stock.reserve) > 0');


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
                    'modification',
                    $this->modification,
                    ProductModificationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('stock.modification IS NULL');
        }


        $dbal
            ->join(
                'stock',
                UserProfile::class,
                'profile',
                'profile.id = stock.profile',
            );


        $dbal->join(
            'profile',
            UserProfilePersonal::class,
            'profile_personal',
            'profile_personal.event = profile.event',
        );

        $dbal->addGroupBy('stock.modification');

        $dbal->addSelect('stock.profile AS value');//->groupBy('stock.profile');
        $dbal->addSelect('profile_personal.username AS attr'); //->addGroupBy('profile_personal.username');
        $dbal->addSelect('(SUM(stock.total) - SUM(stock.reserve)) AS property');

        $dbal->allGroupByExclude();

        return $dbal->fetchAllHydrate(UserProfileUid::class);

    }
}
