<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\StockSettings;

use BaksDev\Products\Stocks\Entity\StocksSettings\Event\ProductStockSettingsEventInterface;
use BaksDev\Products\Stocks\Type\Settings\Event\ProductStockSettingsEventUid;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\Profile\ProductStockSettingsProfileDTO;
use BaksDev\Products\Stocks\UseCase\Admin\StockSettings\Threshold\ProductStockSettingsThresholdDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockSettingsEvent */
final class ProductStockSettingsDTO implements ProductStockSettingsEventInterface
{

    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ProductStockSettingsEventUid $id = null;

    /** Профиль пользователя/магазина  */
    #[Assert\Valid]
    #[Assert\NotBlank]
    private ?ProductStockSettingsProfileDTO $profile;

    #[Assert\Valid]
    #[Assert\NotBlank]
    private ?ProductStockSettingsThresholdDTO $threshold;

    public function __construct(UserProfileUid $profile)
    {
        $this->profile = new ProductStockSettingsProfileDTO($profile);

        $this->threshold = new ProductStockSettingsThresholdDTO();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ProductStockSettingsEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockSettingsEventUid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getProfile(): ?ProductStockSettingsProfileDTO
    {
        return $this->profile;
    }

    public function setProfile(?ProductStockSettingsProfileDTO $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function getThreshold(): ?ProductStockSettingsThresholdDTO
    {
        return $this->threshold;
    }

    public function setThreshold(?ProductStockSettingsThresholdDTO $threshold): self
    {
        $this->threshold = $threshold;
        return $this;
    }

}