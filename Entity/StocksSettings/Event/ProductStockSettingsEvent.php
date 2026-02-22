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

namespace BaksDev\Products\Stocks\Entity\StocksSettings\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\Modify\ProductStockSettingsModify;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\Profile\ProductStockSettingsProfile;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\Threshold\ProductStockSettingsThreshold;
use BaksDev\Products\Stocks\Entity\StocksSettings\ProductStockSettings;
use BaksDev\Products\Stocks\Type\Settings\Event\ProductStockSettingsEventUid;
use BaksDev\Products\Stocks\Type\Settings\Id\ProductStockSettingsUid;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductStockSettingsEvent */

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_settings_event')]
class ProductStockSettingsEvent extends EntityEvent
{
    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductStockSettingsEventUid::TYPE)]
    private ProductStockSettingsEventUid $id;

    /**
     * Идентификатор ProductStockSettings
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductStockSettingsUid::TYPE, nullable: false)]
    private ?ProductStockSettingsUid $main = null;

    /**
     * Модификатор
     */
    #[ORM\OneToOne(mappedBy: 'event', targetEntity: ProductStockSettingsModify::class, cascade: ['all'])]
    private ProductStockSettingsModify $modify;

    /**
     * Profile
     */
    #[ORM\OneToOne(targetEntity: ProductStockSettingsProfile::class, mappedBy: 'event', cascade: ['all'], fetch: 'EAGER')]
    private ProductStockSettingsProfile $profile;

    /**
     * Threshold
     */
    #[ORM\OneToOne(targetEntity: ProductStockSettingsThreshold::class, mappedBy: 'event', cascade: ['all'])]
    private ?ProductStockSettingsThreshold $threshold = null;

    public function __construct()
    {
        $this->id = new ProductStockSettingsEventUid();
        $this->modify = new ProductStockSettingsModify($this);
    }

    /**
     * Идентификатор события
     */

    public function __clone()
    {
        $this->id = clone new ProductStockSettingsEventUid();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ProductStockSettingsEventUid
    {
        return $this->id;
    }

    /**
     * Идентификатор ProductStockSettings
     */
    public function setMain(ProductStockSettingsUid|ProductStockSettings $main): void
    {
        $this->main = $main instanceof ProductStockSettings ? $main->getId() : $main;
    }

    public function getMain(): ?ProductStockSettingsUid
    {
        return $this->main;
    }

    public function getDto($dto): mixed
    {
        if($dto instanceof ProductStockSettingsEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockSettingsEventInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

}