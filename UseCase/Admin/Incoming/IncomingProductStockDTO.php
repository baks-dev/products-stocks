<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming;

use BaksDev\Products\Stocks\Entity\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class IncomingProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductStockEventUid $id;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserProfileUid $profile;

    /** Статус заявки - ПРИХОД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Комментарий */
    private ?string $comment = null;


    //public function __construct(UserProfileUid $profile)
    public function __construct()
    {
        //$this->profile = $profile;
        $this->status = new ProductStockStatus(ProductStockStatusIncoming::class);
        $this->product = new ArrayCollection();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }


    /** Комментарий */

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    /** Ответственное лицо (Профиль пользователя) */

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /** Статус заявки - ПРИХОД */

    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(Products\ProductStockDTO $product): void
    {
        $this->product->add($product);
    }

}
