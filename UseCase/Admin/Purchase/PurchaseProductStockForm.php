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

namespace BaksDev\Products\Stocks\UseCase\Admin\Purchase;

use BaksDev\Products\Product\Repository\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Product\Repository\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Product\Repository\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Product\Repository\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
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

final class PurchaseProductStockForm extends AbstractType
{
    // private WarehouseChoiceInterface $warehouseChoice;
    private ProductChoiceInterface $productChoice;
    private ProductOfferChoiceInterface $productOfferChoice;
    private ProductVariationChoiceInterface $productVariationChoice;
    private ProductModificationChoiceInterface $modificationChoice;
    private iterable $reference;

    public function __construct(
        // WarehouseChoiceInterface $warehouseChoice,
        ProductChoiceInterface $productChoice,
        ProductOfferChoiceInterface $productOfferChoice,
        ProductVariationChoiceInterface $productVariationChoice,
        ProductModificationChoiceInterface $modificationChoice,
        #[TaggedIterator('baks.reference.choice')] iterable $reference,
    )
    {
        // $this->warehouseChoice = $warehouseChoice;
        $this->productChoice = $productChoice;
        $this->productOfferChoice = $productOfferChoice;
        $this->productVariationChoice = $productVariationChoice;
        $this->modificationChoice = $modificationChoice;
        $this->reference = $reference;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Номер заявки
        $builder->add('number', TextType::class);

        // Продукт
        $builder
            ->add('preProduct', ChoiceType::class, [
                'choices' => $this->productChoice->fetchAllProduct(),
                'choice_value' => function(?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function(ProductUid $product) {
                    return $product->getAttr();
                },


                'label' => false,
            ]);


        /*
         * Торговые предложения
         * @var ProductOfferConst $offer
         */

        $builder->add(
            'preOffer',
            HiddenType::class
        );

        $builder->get('preOffer')->addModelTransformer(
            new CallbackTransformer(
                function($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                }
            )
        );

        $formOfferModifier = function(FormInterface $form, ProductUid $product = null) {
            if(null === $product)
            {
                return;
            }

            $offer = $this->productOfferChoice->fetchProductOfferByProduct($product);

            // Если у продукта нет ТП
            if(!$offer->valid())
            {
                $form->add(
                    'preOffer',
                    HiddenType::class
                );

                return;
            }

            $currenOffer = $offer->current();
            $label = $currenOffer->getOption();
            $domain = null;

            if($currenOffer->getProperty())
            {
                /** Если торговое предложение Справочник - ищем домен переводов */
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenOffer->getProperty())
                    {
                        $domain = $reference->domain();
                    }
                }
            }


            $form
                ->add('preOffer', ChoiceType::class, [
                    'choices' => $offer,
                    'choice_value' => function(?ProductOfferConst $offer) {
                        return $offer?->getValue();
                    },
                    'choice_label' => function(ProductOfferConst $offer) {
                        return $offer->getAttr();
                    },

                    'choice_attr' => function(?ProductOfferConst $offer) {
                        return $offer?->getCharacteristic() ? ['data-filter' => $offer?->getCharacteristic()] : [];
                    },

                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };


        $builder->get('preProduct')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formOfferModifier) {
                $product = $event->getForm()->getData();
                $formOfferModifier($event->getForm()->getParent(), $product);
            }
        );

        /*
         * Множественный вариант торгового предложения
         * @var ProductVariationConst $variation
         */

        $builder->add(
            'preVariation',
            HiddenType::class
        );

        $builder->get('preVariation')->addModelTransformer(
            new CallbackTransformer(
                function($variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function($variation) {
                    return $variation ? new ProductVariationConst($variation) : null;
                }
            )
        );

        $formVariationModifier = function(FormInterface $form, ProductOfferConst $offer = null) {

            if(null === $offer)
            {
                return;
            }

            $variations = $this->productVariationChoice->fetchProductVariationByOfferConst($offer);

            // Если у продукта нет множественных вариантов
            if(!$variations->valid())
            {
                $form->add(
                    'preVariation',
                    HiddenType::class
                );

                return;
            }


            $currenVariation = $variations->current();
            $label = $currenVariation->getOption();
            $domain = null;

            /** Если множественный вариант Справочник - ищем домен переводов */
            if($currenVariation->getProperty())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenVariation->getProperty())
                    {
                        $domain = $reference->domain();
                    }
                }
            }

            $form
                ->add('preVariation', ChoiceType::class, [
                    'choices' => $variations,
                    'choice_value' => function(?ProductVariationConst $variation) {
                        return $variation?->getValue();
                    },
                    'choice_label' => function(ProductVariationConst $variation) {
                        return $variation->getAttr();
                    },
                    'choice_attr' => function(?ProductVariationConst $variation) {
                        return $variation?->getCharacteristic() ? ['data-filter' => $variation?->getCharacteristic()] : [];
                    },
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };

        $builder->get('preOffer')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formVariationModifier) {
                $offer = $event->getForm()->getData();
                $formVariationModifier($event->getForm()->getParent(), $offer);
            }
        );

        /*
         * Модификация множественного варианта торгового предложения
         * @var ProductOfferVariationModificationConst $modification
         */

        $builder->add(
            'preModification',
            HiddenType::class
        );

        $builder->get('preModification')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new ProductModificationConst($modification) : null;
                }
            )
        );

        $formModificationModifier = function(FormInterface $form, ProductVariationConst $variation = null) {
            if(null === $variation)
            {
                return;
            }

            $modifications = $this->modificationChoice->fetchProductModificationConstByVariationConst($variation);

            // Если у продукта нет модификаций множественных вариантов
            if(!$modifications->valid())
            {
                $form->add(
                    'preModification',
                    HiddenType::class
                );

                return;
            }

            $currenModifications = $modifications->current();
            $label = $currenModifications->getOption();
            $domain = null;

            /** Если модификация Справочник - ищем домен переводов */
            if($currenModifications->getProperty())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenModifications->getProperty())
                    {
                        $domain = $reference->domain();
                    }
                }
            }

            $form
                ->add('preModification', ChoiceType::class, [
                    'choices' => $modifications,
                    'choice_value' => function(?ProductModificationConst $modification) {
                        return $modification?->getValue();
                    },
                    'choice_label' => function(ProductModificationConst $modification) {
                        return $modification->getAttr();
                    },
                    'choice_attr' => function(?ProductModificationConst $modification) {
                        return $modification?->getCharacteristic() ? ['data-filter' => $modification?->getCharacteristic()] : [];
                    },
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };

        $builder->get('preVariation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formModificationModifier) {
                $variation = $event->getForm()->getData();
                $formModificationModifier($event->getForm()->getParent(), $variation);
            }
        );


        // Количество
        $builder->add('preTotal', IntegerType::class, ['required' => false]);

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
            'purchase',
            ButtonType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => PurchaseProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ]
        );
    }
}
