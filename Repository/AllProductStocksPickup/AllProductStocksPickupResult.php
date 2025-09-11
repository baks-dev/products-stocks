<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\Repository\AllProductStocksPickup;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Users\Profile\UserProfile\Type\Event\UserProfileEventUid;
use DateTimeImmutable;

final readonly class AllProductStocksPickupResult
{
    public function __construct(
        private string $number,
        private string $stock_id,
        private string $stock_event,
        private ?string $comment,
        private string $status,
        private ?string $mod_date,
        private string $order_id,
        private ?string $client_profile_event,
        private string $delivery_date,
        private string $delivery_name,
    ) {}

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getStockId(): ProductStockUid
    {
        return new ProductStockUid($this->stock_id);
    }

    public function getStockEvent(): ProductStockEventUid
    {
        return new ProductStockEventUid($this->stock_event);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): ProductStockStatus
    {
        return new ProductStockStatus($this->status);
    }

    public function getModDate(): ?DateTimeImmutable
    {
        return false === empty($this->mod_date) ? new DateTimeImmutable($this->mod_date) : null;
    }

    public function getOrderId(): ?OrderUid
    {
        return false === empty($this->order_id) ? new OrderUid($this->order_id) : null;
    }

    public function getClientProfileEvent(): ?UserProfileEventUid
    {
        return false === empty($this->client_profile_event) ? new UserProfileEventUid($this->client_profile_event) : null;
    }

    public function getDeliveryDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->delivery_date);
    }

    public function getDeliveryName(): ?string
    {
        return $this->delivery_name;
    }
}