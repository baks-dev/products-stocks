<?php

namespace BaksDev\Products\Stocks\Repository\AllProductStocks;

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Products\Stocks\Forms\WarehouseFilter\ProductsStocksFilterInterface;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;

interface AllProductStocksInterface
{
    /** Метод возвращает полное состояние складских остатков продукции */
    public function fetchAllProductStocksAssociative(
        User|UserUid $user,
        UserProfileUid $profile
    ): PaginatorInterface;

    public function search(SearchDTO $search): static;

    public function filter(ProductFilterDTO $filter): static;
}
