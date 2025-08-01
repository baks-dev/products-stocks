<?php

namespace BaksDev\Products\Stocks\Forms\StatusFilter;

use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;

interface ProductStockStatusFilterInterface
{
    public function getStatus(): ?ProductStockStatus;
}