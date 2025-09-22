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

namespace BaksDev\Products\Stocks\Entity\Total;

use BaksDev\Core\Entity\EntityState;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

// ProductStockTotal

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_total')]
#[ORM\Index(columns: ['usr', 'profile'])]
class ProductStockTotal extends EntityState
{
    /** ID  */
    #[ORM\Id]
    #[ORM\Column(type: ProductStockTotalUid::TYPE)]
    private ProductStockTotalUid $id;

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


    /** ID пользователя */
    #[ORM\Column(type: UserUid::TYPE, nullable: true, options: ['default' => null])]
    private ?UserUid $usr = null;

    /** ID профиля (склад) */
    #[ORM\Column(type: UserProfileUid::TYPE)]
    private UserProfileUid $profile;


    /** Комментарий */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $comment = null;

    /** Стоимость продукции на указанном складе */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $price = 0;


    /** Место складирования */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $storage = null;

    /** Общее количество на данном складе */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $total = 0;

    /** Зарезервировано на данном складе */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $reserve = 0;


    public function __construct(
        UserUid $usr,
        UserProfileUid $profile,
        ProductUid $product,
        ProductOfferConst|null|false $offer,
        ProductVariationConst|null|false $variation,
        ProductModificationConst|null|false $modification,
        ?string $storage
    )
    {
        $this->id = clone new ProductStockTotalUid();

        $this->product = $product;
        $this->offer = $offer ?: null;
        $this->variation = $variation ?: null;
        $this->modification = $modification ?: null;
        $this->storage = $storage ?: null;

        $this->profile = $profile;
        $this->usr = $usr;
    }

    /**
     * Id
     */
    public function getId(): ProductStockTotalUid
    {
        return $this->id;
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }


    /** Количество */

    // Увеличиваем количество
    public function addTotal(int $total): self
    {
        $this->total += $total;
        return $this;
    }

    // Уменьшаем количество
    public function subTotal(int $total): self
    {
        $this->total -= $total;
        return $this;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /** Резервирование */

    // Увеличиваем количество
    public function addReserve(int $reserve): self
    {
        $this->reserve += $reserve;
        return $this;
    }

    // Уменьшаем количество
    public function subReserve(int $reserve): self
    {
        $this->reserve -= $reserve;

        if($this->reserve < 0)
        {
            $this->reserve = 0;
        }

        return $this;
    }

    public function getReserve(): int
    {
        return $this->reserve;
    }

    /**
     * Storage
     */
    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ProductStockTotalInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockTotalInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function getModification(): ?ProductModificationConst
    {
        return $this->modification;
    }

    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }

    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}
