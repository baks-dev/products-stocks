<?php

namespace BaksDev\Products\Stocks\Entity\Orders;

use BaksDev\Orders\Order\Type\Id\OrderUid;

interface ProductStockOrderInterface
{
    public function getOrd(): ?OrderUid;
}
