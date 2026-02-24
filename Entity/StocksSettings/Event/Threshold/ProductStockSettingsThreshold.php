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

namespace BaksDev\Products\Stocks\Entity\StocksSettings\Event\Threshold;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEvent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/* ProductStockSettingsThreshold */

/* Порог наличия складских остатков ProductStockSettings */

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_settings_threshold')]
class ProductStockSettingsThreshold extends EntityEvent
{
    /** Связь на событие */
    #[Assert\NotBlank]
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: ProductStockSettingsEvent::class, inversedBy: 'threshold')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockSettingsEvent $event;

    /**
     * Порог складских остатков (default 0)
     */
    #[Assert\Range(min: 0)]
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $value;

    public function __construct(ProductStockSettingsEvent $event)
    {
        $this->event = $event;
    }

    public function __toString(): string
    {
        return (string) $this->event;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    /** @return ProductStockSettingsThresholdInterface */
    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ProductStockSettingsThresholdInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    /** @var ProductStockSettingsThresholdInterface $dto */
    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockSettingsThresholdInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}