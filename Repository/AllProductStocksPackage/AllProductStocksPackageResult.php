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
 */

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPackage;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Part\ProductStockPartUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use DateTimeImmutable;

final readonly class AllProductStocksPackageResult
{
    public function __construct(
        private string $id,
        private string $event,
        private ?string $number,
        private ?string $comment,
        private string $status,
        private ?string $date_package,
        private ?string $order_id,
        private ?bool $order_danger,
        private ?string $order_comment,
        private string $delivery_date,
        private ?string $delivery_name,
        private ?string $users_profile_username,
        private ?bool $printed,
        private ?string $product_stock_part,

        //private ?bool $products_move,
        //private ?string $users_profile_destination,
        //private ?string $users_profile_move,
    ) {}

    public function getId(): ProductStockUid
    {
        return new ProductStockUid($this->id);
    }

    public function getEvent(): ProductStockEventUid
    {
        return new ProductStockEventUid($this->event);
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): ProductStockStatus
    {
        return new ProductStockStatus($this->status);
    }

    public function getDatePackage(): ?DateTimeImmutable
    {
        return empty($this->date_package) ? null : new DateTimeImmutable($this->date_package);
    }

    public function getOrderId(): ?OrderUid
    {
        return empty($this->order_id) ? null : new OrderUid($this->order_id);
    }

    public function isOrderDanger(): bool
    {
        return $this->order_danger === true;
    }

    public function getOrderComment(): ?string
    {
        return $this->order_comment;
    }

    public function getDeliveryDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->delivery_date);
    }

    public function getDeliveryName(): ?string
    {
        return $this->delivery_name;
    }

    public function getUsersProfileUsername(): ?string
    {
        return $this->users_profile_username;
    }

    public function isPrinted(): bool
    {
        return $this->printed === true;
    }

    public function getProductStockPart(): ProductStockPartUid|false
    {
        return $this->product_stock_part ? new ProductStockPartUid($this->product_stock_part) : false;
    }



    //    public function isProductsMove(): bool
    //    {
    //        return $this->products_move;
    //    }
    //
    //    public function getUsersProfileDestination(): ?string
    //    {
    //        return $this->users_profile_destination;
    //    }
    //
    //    public function getUsersProfileMove(): ?string
    //    {
    //        return $this->users_profile_move;
    //    }
}