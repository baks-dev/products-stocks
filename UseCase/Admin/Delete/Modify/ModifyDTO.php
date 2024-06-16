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

namespace BaksDev\Products\Stocks\UseCase\Admin\Delete\Modify;


use BaksDev\Core\Type\Modify\Modify\ModifyActionDelete;
use BaksDev\Core\Type\Modify\Modify\ModifyActionNew;
use BaksDev\Core\Type\Modify\Modify\ModifyActionUpdate;
use BaksDev\Core\Type\Modify\ModifyAction;
use BaksDev\Products\Stocks\Entity\Modify\ProductStockModify;
use BaksDev\Products\Stocks\Entity\Modify\ProductStockModifyInterface;
use BaksDev\Wildberries\Products\Entity\Barcode\Modify\WbBarcodeModifyInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockModify */
final class ModifyDTO implements ProductStockModifyInterface
{
    /**
     * Модификатор
     */
    #[Assert\NotBlank]
    private readonly ModifyAction $action;

    public function __construct()
    {
        $this->action = new ModifyAction(ModifyActionDelete::class);
    }

    public function getAction(): ModifyAction
    {
        return $this->action;
    }
}

