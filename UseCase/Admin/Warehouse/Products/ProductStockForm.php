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

namespace BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Products;

use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductStockForm extends AbstractType
{
    private ProductDetailByConstInterface $productDetailByConst;

    public function __construct(ProductDetailByConstInterface $productDetailByConst)
    {
        $this->productDetailByConst = $productDetailByConst;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            /** @var ProductStockDTO $product */
            $product = $event->getData();

            if($product)
            {
                $product->detail = $this->productDetailByConst->fetchProductDetailByConstAssociative
                (
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                ) ?: null;
            }
        });

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {
                /** @var ProductStockDTO $data */
                $data = $event->getData();
                $builder = $event->getForm();

                if($data)
                {
                    // Количество
                    $builder->add('total',
                        IntegerType::class, ['attr' => ['min' => 1, 'max' => $data->getTotal()]]
                    );
                }
            }
        );


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductStockDTO::class,
        ]);
    }
}
