<?php

/*
 * Copyright (c) 2023.  Baks.dev <admin@baks.dev>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Tests;

use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Incoming\IncomingProductStockForm;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Form\Test\TypeTestCase;

/** @group products-stocks */
#[When(env: 'test')]
final class IncomingProductStockFormTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        /* DATA */
        $UserProfileUid = new UserProfileUid();
        $comment = 'comment';

        $formDataDTO = new IncomingProductStockDTO($UserProfileUid);
        $formDataDTO->setComment($comment);

        $newDTO = new IncomingProductStockDTO($UserProfileUid);

        /* FORM */
        $form = $this->factory->create(IncomingProductStockForm::class, $newDTO);

        $formData = [
            'comment' => $comment,
        	'incoming' => true, // btn
        ];

        $form->submit($formData);
        self::assertTrue($form->isSynchronized());

        /* OBJECT */
        $expected = new IncomingProductStockDTO($UserProfileUid);
        $expected->setComment($comment);

        self::assertEquals($expected, $newDTO);

        /* VIEW */
        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key)
        {
            self::assertArrayHasKey($key, $children);
        }
    }
}
