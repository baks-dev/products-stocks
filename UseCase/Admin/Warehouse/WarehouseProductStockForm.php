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

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse;

use BaksDev\Contacts\Region\Repository\WarehouseChoice\WarehouseChoiceInterface;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WarehouseProductStockForm extends AbstractType
{
    //    private WarehouseChoiceInterface $warehouseChoice;
    //
    //    public function __construct(
    //        WarehouseChoiceInterface $warehouseChoice,
    //    ) {
    //        $this->warehouseChoice = $warehouseChoice;
    //    }

    private UserProfileChoiceInterface $userProfileChoice;

    public function __construct(UserProfileChoiceInterface $userProfileChoice)
    {
        $this->userProfileChoice = $userProfileChoice;
    }


    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Склад

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {
                /** @var WarehouseProductStockDTO $data */
                $data = $event->getData();
                $form = $event->getForm();


                //                /dd($data);

                //                if($data->getDestination())
                //                {
                //                    //$form->add('warehouse', HiddenType::class);
                //                    return;
                //                }


                /** Все профили пользователя */

                $profiles = $this->userProfileChoice->getActiveUserProfile($data->getUsr());


                /** Список всех активных складов */
                //$warehouses = $this->warehouseChoice->fetchAllWarehouse();

                if(count($profiles) === 1)
                {
                    $data->setProfile(current($profiles));
                }

                // Склад
                $form
                    ->add('profile', ChoiceType::class, [
                        'choices' => $profiles,
                        'choice_value' => function(?UserProfileUid $profile) {
                            return $profile?->getValue();
                        },
                        'choice_label' => function(UserProfileUid $profile) {
                            return $profile->getAttr();
                        },

                        'label' => false,
                        'required' => true,
                    ]);
            }
        );

        // Section Collection
        $builder->add('product', CollectionType::class, [
            'entry_type' => Products\ProductStockForm::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
            'allow_delete' => true,
            'allow_add' => true,
            'prototype_name' => '__product__',
        ]);

        $builder->add('comment', TextareaType::class, ['required' => false]);

        // Сохранить
        $builder->add(
            'send',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => WarehouseProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
                'csrf_protection' => false
            ]
        );
    }
}
