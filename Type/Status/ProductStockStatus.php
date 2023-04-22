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

namespace BaksDev\Products\Stocks\Type\Status;


use BaksDev\Products\Stocks\Type\Status\Collection\ProductStockStatusInterface;

final class ProductStockStatus
{
	public const TYPE = 'product_stock_status_type';
	
	private ?ProductStockStatusInterface $status = null;
	
	public function __construct(self|string|ProductStockStatusInterface $status)
	{
		if($status instanceof ProductStockStatusInterface)
		{
			$this->status = $status;
		}
		
		if($status instanceof $this)
		{
			$this->status = $status->getOrderStatus();
		}
		
	}
	
	public function __toString() : string
	{
		return $this->status ? $this->status->getValue() :  '';
	}
	
	/** Возвращает значение (value) страны String */
	
	public function getProductStockStatus() : ProductStockStatusInterface
	{
		return $this->status;
	}

	
	/** Возвращает значение (value) страны String */
	
	public function getProductStockStatusValue() : ?string
	{
		return $this->status?->getValue();
	}
	
	
	/** Возвращает код цвета */
	
	public function getColor() : string
	{
		return $this->status::color();
	}
	
}