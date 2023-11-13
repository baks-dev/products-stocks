<?php

namespace BaksDev\Products\Stocks\Entity\Move;

use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

interface ProductStockMoveInterface
{
    public function getDestination(): ?UserProfileUid;
}
