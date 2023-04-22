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

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Products;

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductOfferVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductOfferVariationModificationConst;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductStockForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Склад

        $builder->add('warehouse', HiddenType::class);

        $builder->get('warehouse')->addModelTransformer(
            new CallbackTransformer(
                function ($warehouse) {
                    return $warehouse instanceof ContactsRegionCallUid ? $warehouse->getValue() : $warehouse;
                },
                function ($warehouse) {
                    return new ContactsRegionCallUid($warehouse);
                }
            )
        );

        // Продукт

        $builder->add('product', HiddenType::class);

        $builder->get('product')->addModelTransformer(
            new CallbackTransformer(
                function ($product) {
                    return $product instanceof ProductUid ? $product->getValue() : $product;
                },
                function ($product) {
                    return new ProductUid($product);
                }
            )
        );

        // Торговое предложение

        $builder->add('offer', HiddenType::class);

        $builder->get('offer')->addModelTransformer(
            new CallbackTransformer(
                function ($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function ($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                }
            )
        );

        // Множественный вариант

        $builder->add('variation', HiddenType::class);

        $builder->get('variation')->addModelTransformer(
            new CallbackTransformer(
                function ($variation) {
                    return $variation instanceof ProductOfferVariationConst ? $variation->getValue() : $variation;
                },
                function ($variation) {
                    return $variation ? new ProductOfferVariationConst($variation) : null;
                }
            )
        );

        // Модификация множественного варианта

        $builder->add('modification', HiddenType::class);

        $builder->get('modification')->addModelTransformer(
            new CallbackTransformer(
                function ($modification) {
                    return $modification instanceof ProductOfferVariationModificationConst ? $modification->getValue() : $modification;
                },
                function ($modification) {

                    return $modification ? new ProductOfferVariationModificationConst($modification) : null;
                }
            )
        );

        // Количество

        $builder->add('total', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductStockDTO::class,
        ]);
    }
}
