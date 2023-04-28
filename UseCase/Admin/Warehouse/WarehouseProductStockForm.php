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
use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Product\Repository\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Product\Repository\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Product\Repository\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Product\Repository\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductOfferVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductOfferVariationModificationConst;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WarehouseProductStockForm extends AbstractType
{
    private WarehouseChoiceInterface $warehouseChoice;

    public function __construct(
        WarehouseChoiceInterface $warehouseChoice,
    )
    {
        $this->warehouseChoice = $warehouseChoice;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Склад

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                /** @var WarehouseProductStockDTO $data */

                $data = $event->getData();
                $form = $event->getForm();

                $warehouses = $this->warehouseChoice->fetchWarehouseByProfile($data->getProfile());

                if (1 === count($warehouses)) {
                    $data->setWarehouse(current($warehouses));
                }

                // Склад
                $form
                    ->add('warehouse', ChoiceType::class, [
                        'choices' => $warehouses,
                        'choice_value' => function (?ContactsRegionCallUid $warehouse) {
                            return $warehouse?->getValue();
                        },
                        'choice_label' => function (ContactsRegionCallUid $warehouse) {
                            return $warehouse->getAttr();
                        },

                        'label' => false,
                        'required' => true,
                    ])
                ;
            }
        );

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
            ]
        );
    }
}
