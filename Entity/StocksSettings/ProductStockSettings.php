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

namespace BaksDev\Products\Stocks\Entity\StocksSettings;

use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEvent;
use BaksDev\Products\Stocks\Type\Settings\Event\ProductStockSettingsEventUid;
use BaksDev\Products\Stocks\Type\Settings\Id\ProductStockSettingsUid;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductStocksSettings */

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_settings')]
class ProductStockSettings
{
    /**
     * Идентификатор сущности
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockSettingsUid::TYPE)]
    private ProductStockSettingsUid $id;

    /**
     * Идентификатор события
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductStockSettingsEventUid::TYPE, unique: true)]
    private ProductStockSettingsEventUid $event;

    public function __construct()
    {
        $this->id = new ProductStockSettingsUid();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * Идентификатор
     */
    public function getId(): ProductStockSettingsUid
    {
        return $this->id;
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ProductStockSettingsEventUid
    {
        return $this->event;
    }

    public function setEvent(ProductStockSettingsEventUid|ProductStockSettingsEvent $event): void
    {
        $this->event = $event instanceof ProductStockSettingsEvent ? $event->getId() : $event;
    }
}