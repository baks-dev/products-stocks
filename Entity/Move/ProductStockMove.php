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

namespace BaksDev\Products\Stocks\Entity\Move;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductStockMove */

#[ORM\Entity]
#[ORM\Table(name: 'product_stock_move')]
class ProductStockMove extends EntityEvent
{
    public const TABLE = 'product_stock_move';

    /** ID события */
    #[Assert\NotBlank]
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'move', targetEntity: ProductStockEvent::class)]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductStockEvent $event;

    /** Константа склада назначения (при перемещении) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ContactsRegionCallConst::TYPE, nullable: true)]
    private ?ContactsRegionCallConst $destination = null;

    /** Идентификатор заказа, если перемещение для упаковки заказа */
    #[Assert\Uuid]
    #[ORM\Column(type: OrderUid::TYPE, nullable: true)]
    private ?OrderUid $ord = null;

    public function __construct(ProductStockEvent $event)
    {
        $this->event = $event;
    }

    public function getDto($dto): mixed
    {
        if ($dto instanceof ProductStockMoveInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if (empty($dto->getDestination()))
        {
            return false;
        }

        if ($dto instanceof ProductStockMoveInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    /**
     * Константа склада назначения (при перемещении)
     */
    public function getDestination(): ?ContactsRegionCallConst
    {
        return $this->destination;
    }

    /**
     * Идентификатор заказа, если перемещение для упаковки заказа
     */
    public function getOrder(): ?OrderUid
    {
        return $this->ord;
    }
}
