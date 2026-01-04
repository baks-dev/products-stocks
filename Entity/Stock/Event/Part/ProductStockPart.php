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

namespace BaksDev\Products\Stocks\Entity\Stock\Event\Part;


use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Core\Type\UidType\Uid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProductStockPart
 *
 * @see ProductStockEvent
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_stock_part')]
#[ORM\Index(columns: ['value'])]
#[ORM\Index(columns: ['number'])]
class ProductStockPart extends EntityEvent
{
    /** Связь на событие */
    #[Assert\NotBlank]
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: ProductStockEvent::class, inversedBy: 'part')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockEvent $event;

    /** Значение свойства */
    #[Assert\NotBlank]
    #[ORM\Column(type: ProductStockPartUid::TYPE)]
    private ProductStockPartUid $value;

    /** Номер партии */
    #[Assert\Length(max: 36)]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $number = null;


    public function __construct(ProductStockEvent $event)
    {
        $this->event = $event;
    }

    public function __toString(): string
    {
        return (string) $this->event;
    }

    public function getValue(): ProductStockPartUid
    {
        return $this->value;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    /** @return ProductStockPartInterface */
    public function getDto($dto): mixed
    {
        if(is_string($dto) && class_exists($dto))
        {
            $dto = new $dto();
        }

        if($dto instanceof ProductStockPartInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    /** @var ProductStockPartInterface $dto */
    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductStockPartInterface)
        {
            if(false === ($dto->getValue() instanceof ProductStockPartUid))
            {
                return false;
            }

            /** Если номер не передан - присваиваем из UID */
            if(empty($dto->getNumber()))
            {
                $this->number = number_format(microtime(true) * 100, 0, '.', '.');
                usleep(12000); //  Добавляем задержка выполнения
            }

            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}