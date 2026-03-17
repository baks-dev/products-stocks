<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Entity\Quantity\Invariable;

use BaksDev\Core\Entity\EntityState;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Quantity\Event\ProductStockQuantityEvent;
use BaksDev\Products\Stocks\Type\Quantity\Id\ProductStockQuantityUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_quantity_invariable')]
#[ORM\Index(columns: ['usr', 'profile'])]
#[ORM\Index(columns: ['profile', 'product', 'offer', 'variation', 'modification'])]
class ProductStockQuantityInvariable extends EntityState
{
    /**
     * Идентификатор Main
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockQuantityUid::TYPE)]
    private ProductStockQuantityUid $main;

    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\OneToOne(targetEntity: ProductStockQuantityEvent::class, inversedBy: 'invariable')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockQuantityEvent $event;

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

    /** Место складирования */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $storage = null;

    /** Место с высоким приоритетом */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $priority = false;

    public function __construct(ProductStockQuantityEvent $event)
    {
        $this->event = $event;
        $this->main = $event->getMain();
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
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
        return (string) $this->main;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ProductStockQuantityInvariableInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockQuantityInvariableInterface || $dto instanceof self)
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
}