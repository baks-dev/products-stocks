<?php

namespace BaksDev\Products\Stocks\Repository\AllProductStocks;

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;

interface AllProductStocksInterface
{
    /** Метод возвращает полное состояние складских остатков продукции */
    public function fetchAllProductStocksAssociative(
        SearchDTO $search,
        UserProfileUid $profile
    ): PaginatorInterface;
}
