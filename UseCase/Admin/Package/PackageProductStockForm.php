<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Package;

use BaksDev\Contacts\Region\Repository\WarehouseChoice\WarehouseChoiceInterface;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PackageProductStockForm extends AbstractType
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
        //        $warehouses = $this->warehouseChoice->fetchAllWarehouse();
        //
        //        /** @var ContactsRegionCallUid $currentWarehouse */
        //        $currentWarehouse = (count($warehouses) === 1) ? current($warehouses) : null;
        //
        //        if ($currentWarehouse)
        //        {
        //            $builder->addEventListener(
        //                FormEvents::PRE_SET_DATA,
        //                function (FormEvent $event) use ($currentWarehouse): void {
        //
        //                    /** @var PackageProductStockDTO $data */
        //                    $data = $event->getData();
        //                    $data->setWarehouse($currentWarehouse);
        //                },
        //            );
        //        }
        //
        //        /* Склад назначения */
        //        $builder->add(
        //            'warehouse',
        //            ChoiceType::class,
        //            [
        //                'choices' => $warehouses,
        //                'choice_value' => function (?ContactsRegionCallConst $warehouse) {
        //                    return $warehouse?->getValue();
        //                },
        //                'choice_label' => function (ContactsRegionCallConst $warehouse) {
        //                    return $warehouse->getAttr();
        //                },
        //
        //                'label' => false,
        //                'required' => false,
        //            ]
        //        );


        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {
                /** @var WarehouseProductStockDTO $data */
                $data = $event->getData();
                $form = $event->getForm();

                /** Все профили пользователя */
                $profiles = $this->userProfileChoice->getActiveUserProfile($data->getUsr());

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


        // Сохранить
        $builder->add(
            'package',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']],
        );

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => PackageProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
                'csrf_protection' => false
            ],
        );
    }
}
