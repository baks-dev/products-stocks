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

namespace BaksDev\Products\Stocks\Forms\WarehouseFilter\Admin;

use BaksDev\Contacts\Region\Repository\WarehouseChoice\WarehouseChoiceInterface;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductsStocksFilterForm extends AbstractType
{
    private RequestStack $request;

    private WarehouseChoiceInterface $warehouseChoice;

    public function __construct(
        WarehouseChoiceInterface $warehouseChoice,
        RequestStack $request,
    )
    {
        $this->request = $request;
        $this->warehouseChoice = $warehouseChoice;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('warehouse', ChoiceType::class, [
            'choices' => $this->warehouseChoice->fetchAllWarehouse(),
            'choice_value' => function(?ContactsRegionCallConst $warehouse) {
                return $warehouse?->getValue();
            },
            'choice_label' => function(ContactsRegionCallConst $warehouse) {
                return $warehouse->getAttr();
            },
            'label' => false,
        ]);


        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {
                /** @var ProductsStocksFilterDTO $data */
                $data = $event->getData();

                $this->request->getSession()->set(ProductsStocksFilterDTO::warehouse, $data->getWarehouse());

            }
        );
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => ProductsStocksFilterDTO::class,
                'method' => 'POST',
            ]
        );
    }
}
