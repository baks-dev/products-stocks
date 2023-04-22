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

namespace BaksDev\Products\Stocks\Entity\Products;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Core\Type\Modify\ModifyAction;
use BaksDev\Core\Type\Modify\ModifyActionEnum;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductOfferVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductOfferVariationModificationConst;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Product\ProductStockCollectionUid;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Core\Entity\EntityState;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/* ProductStockProduct */

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_product')]
class ProductStockProduct extends EntityEvent
{
    public const TABLE = 'product_stock_product';

    /** ID */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockCollectionUid::TYPE)]
    private ProductStockCollectionUid $id;

    /** ID события */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\ManyToOne(targetEntity: ProductStockEvent::class, inversedBy: 'product')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockEvent $event;

    /** ID склада */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ContactsRegionCallUid::TYPE)]
    private ContactsRegionCallUid $warehouse;

    /** ID продукта */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductUid::TYPE)]
    private ProductUid $product;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductOfferConst::TYPE, nullable: true)]
    private ?ProductOfferConst $offer;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductOfferVariationConst::TYPE, nullable: true)]
    private ?ProductOfferVariationConst $variation;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductOfferVariationModificationConst::TYPE, nullable: true)]
    private ?ProductOfferVariationModificationConst $modification;

    /** Количество */
    #[Assert\Range(min: 1)]
    #[ORM\Column(type: Types::INTEGER)]
    private int $total;

    /** Статус (Приход, заявка) */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $package = false;


    public function __construct(ProductStockEvent $event) {
        $this->event = $event;
        $this->id = new ProductStockCollectionUid();
    }

    public function __clone() : void
    {
        $this->id = new ProductStockCollectionUid();
    }


    public function getDto($dto): mixed
    {
        if ($dto instanceof ProductStockProductInterface) {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if ($dto instanceof ProductStockProductInterface) {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }


    public function getWarehouse(): ContactsRegionCallUid
    {
        return $this->warehouse;
    }


    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }


    public function getVariation(): ?ProductOfferVariationConst
    {
        return $this->variation;
    }


    public function getModification(): ?ProductOfferVariationModificationConst
    {
        return $this->modification;
    }


    public function getTotal(): int
    {
        return $this->total;
    }


}