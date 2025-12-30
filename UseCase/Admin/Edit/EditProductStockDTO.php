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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Edit;

use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Invariable\ProductStockInvariableDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Orders\ProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Edit\Products\ProductStockProductDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class EditProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    /** Постоянная величина */
    #[Assert\Valid]
    private readonly ProductStockInvariableDTO $invariable;

    /**
     * @var ArrayCollection<int, ProductStockProductDTO> $product
     * Коллекция продукции из заказа
     */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Идентификатор заказа */
    #[Assert\Valid]
    private ProductStockOrderDTO $ord;

    /**
     * @deprecated переносится в Invariable
     *
     * Ответственное лицо (Профиль пользователя)
     */
    private ?UserProfileUid $profile = null;

    public function __construct(ProductStockEventUid $id)
    {
        $this->id = $id;
        $this->product = new ArrayCollection();
        $this->ord = new ProductStockOrderDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    /**
     * Постоянная величина
     */
    public function getInvariable(): ProductStockInvariableDTO
    {
        return $this->invariable;
    }

    /**
     * Идентификатор заказа
     */
    public function getOrd(): ProductStockOrderDTO
    {
        return $this->ord;
    }

    public function setOrd(ProductStockOrderDTO $ord): void
    {
        $this->ord = $ord;
    }

    /**
     * Коллекция продукции
     */

    /**
     * @return ArrayCollection<int, ProductStockProductDTO>
     */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    /**
     * @param ArrayCollection<int, ProductStockProductDTO> $product
     */
    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(ProductStockProductDTO $product): void
    {
        $exist = $this->product->exists(function(int $k, ProductStockProductDTO $element) use ($product) {

            return $element->getProduct()->equals($product->getProduct())
                &&
                ((is_null($element->getOffer()) && is_null($product->getOffer())) || $element->getOffer()->equals($product->getOffer()))
                &&
                ((is_null($element->getVariation()) && is_null($product->getVariation())) || $element->getVariation()->equals($product->getVariation()))
                &&
                ((is_null($element->getModification()) && is_null($product->getModification())) || $element->getModification()->equals($product->getModification()));
        });

        if(false === $exist)
        {
            $this->product->add($product);
        }
    }

    public function removeProduct(ProductStockProductDTO $product): void
    {
        $this->product->removeElement($product);
    }
}
