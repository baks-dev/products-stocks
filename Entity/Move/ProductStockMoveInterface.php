<?php

namespace BaksDev\Products\Stocks\Entity\Move;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;

interface ProductStockMoveInterface
{
    public function getDestination(): ?ContactsRegionCallConst;
}
