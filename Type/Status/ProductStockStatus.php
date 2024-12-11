<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Type\Status;

use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusInterface;
use InvalidArgumentException;

final class ProductStockStatus
{
    public const string TYPE = 'product_stock_status_type';

    private ?ProductStockStatusInterface $status = null;

    public function __construct(ProductStockStatusInterface|self|string $status)
    {

        if(is_string($status) && class_exists($status))
        {
            $instance = new $status();

            if($instance instanceof ProductStockStatusInterface)
            {
                $this->status = $instance;
                return;
            }
        }

        if($status instanceof ProductStockStatusInterface)
        {
            $this->status = $status;
            return;
        }

        if($status instanceof self)
        {
            $this->status = $status->getProductStockStatus();
            return;
        }


        /** @var ProductStockStatusInterface $declare */
        foreach(self::getDeclared() as $declare)
        {
            $instance = new self($declare);

            if($instance->getProductStockStatusValue() === $status)
            {
                $this->status = new $declare;
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Not found ProductStockStatus %s', $status));


    }

    public function __toString(): string
    {
        return $this->status ? $this->status->getValue() : '';
    }

    /** Возвращает значение (value) страны String */
    public function getProductStockStatus(): ProductStockStatusInterface
    {
        return $this->status;
    }

    /** Возвращает значение (value) страны String */
    public function getProductStockStatusValue(): ?string
    {
        return $this->status?->getValue();
    }


    public static function cases(): array
    {
        $case = [];

        foreach(self::getDeclared() as $status)
        {
            /** @var ProductStockStatusInterface $status */
            $class = new $status;
            $case[] = new self($class);
        }

        return $case;
    }

    public static function getDeclared(): array
    {
        return array_filter(
            get_declared_classes(),
            static function($className) {
                return in_array(ProductStockStatusInterface::class, class_implements($className), true);
            }
        );
    }


    public function equals(mixed $status): bool
    {
        $status = new self($status);

        return $this->getProductStockStatusValue() === $status->getProductStockStatusValue();
    }

}
