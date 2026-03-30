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

namespace BaksDev\Products\Stocks\Repository\Quantity\ProductStocksQuantityStorage;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEvent;
use BaksDev\Products\Stocks\Entity\Quantity\Invariable\ProductStockQuantityInvariable;
use BaksDev\Products\Stocks\Entity\Quantity\ProductStockQuantity;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class ProductStocksQuantityStorageRepository implements ProductStocksQuantityStorageInterface
{
    private UserProfileUid|string $profile;

    private ProductInvariableUid $invariable;

    private ?string $storage = null;

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

    public function invariable(ProductInvariableUid|string $invariable): self
    {
        if(is_string($invariable))
        {
            $invariable = new ProductInvariableUid($invariable);
        }

        $this->invariable = $invariable;
        return $this;
    }

    public function storage(string|false|null $storage): self
    {
        if(empty($storage))
        {
            $this->storage = null;
            return $this;
        }

        $storage = trim($storage);
        $storage = mb_strtolower($storage);

        $this->storage = $storage;

        return $this;
    }


    /** Метод возвращает складской остаток (место для хранения указанной продукции) указанного профиля */
    public function find(): ?ProductStockQuantityEvent
    {
        if(empty($this->profile))
        {
            throw new InvalidArgumentException('Invalid Argument profile');
        }

        if(empty($this->invariable))
        {
            throw new InvalidArgumentException('Invalid Argument invariable');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('event')
            ->from(ProductStockQuantityInvariable::class, 'stock_invariable')
            ->where('stock_invariable.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );

        $orm
            ->join(
                ProductStockQuantity::class,
                'stock',
                'WITH',
                'stock.id = stock_invariable.main'
            );

        $orm
            ->join(
                ProductStockQuantityEvent::class,
                'event',
                'WITH',
                'event.id = stock.event'
            );

        $orm
            ->andWhere('stock_invariable.invariable = :invariable')
            ->setParameter(
                key: 'invariable',
                value: $this->invariable,
                type: ProductInvariableUid::TYPE
            );

        if($this->storage)
        {
            $orm
                ->andWhere('LOWER(stock_invariable.storage) = :storage')
                ->setParameter(
                    key: 'storage',
                    value: $this->storage
                );
        }
        else
        {
            $orm->andWhere('stock_invariable.storage IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm->getOneOrNullResult();
    }
}
