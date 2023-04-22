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

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming;

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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IncomingProductStockForm extends AbstractType
{
    private WarehouseChoiceInterface $warehouseChoice;
    private ProductChoiceInterface $productChoice;
    private ProductOfferChoiceInterface $productOfferChoice;
    private ProductVariationChoiceInterface $productVariationChoice;
    private ProductModificationChoiceInterface $modificationChoice;

    public function __construct(
        WarehouseChoiceInterface $warehouseChoice,
        ProductChoiceInterface $productChoice,
        ProductOfferChoiceInterface $productOfferChoice,
        ProductVariationChoiceInterface $productVariationChoice,
        ProductModificationChoiceInterface $modificationChoice
    ) {
        $this->warehouseChoice = $warehouseChoice;
        $this->productChoice = $productChoice;
        $this->productOfferChoice = $productOfferChoice;
        $this->productVariationChoice = $productVariationChoice;
        $this->modificationChoice = $modificationChoice;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Продукт

        $builder
            ->add('preProduct', ChoiceType::class, [
                'choices' => $this->productChoice->fetchAllProduct(),
                'choice_value' => function (?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function (ProductUid $product) {
                    return $product->getAttr();
                },
                'label' => false,
            ])
        ;

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
                function ($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function ($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                }
            )
        );

        $formOfferModifier = function (FormInterface $form, ProductUid $product = null) {
            if (null === $product) {
                return;
            }

            $offer = $this->productOfferChoice->fetchProductOfferByProduct($product);

            // Если у продукта нет ТП
            if (empty($offer)) {
                $form->add(
                    'preOffer',
                    HiddenType::class
                );

                return;
            }

            $label = current($offer)->getOption();

            $form
                ->add('preOffer', ChoiceType::class, [
                    'choices' => $offer,
                    'choice_value' => function (?ProductOfferConst $offer) {
                        return $offer?->getValue();
                    },
                    'choice_label' => function (ProductOfferConst $offer) {
                        return $offer->getAttr();
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ])
            ;
        };

        $builder->get('preProduct')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formOfferModifier) {
                $product = $event->getForm()->getData();
                $formOfferModifier($event->getForm()->getParent(), $product);
            }
        );

        /*
         * Множественный вариант торгового предложения
         * @var ProductOfferVariationConst $variation
         */

        $builder->add(
            'preVariation',
            HiddenType::class
        );

        $builder->get('preVariation')->addModelTransformer(
            new CallbackTransformer(
                function ($variation) {
                    return $variation instanceof ProductOfferVariationConst ? $variation->getValue() : $variation;
                },
                function ($variation) {
                    return $variation ? new ProductOfferVariationConst($variation) : null;
                }
            )
        );

        $formVariationModifier = function (FormInterface $form, ProductOfferConst $offer = null) {
            if (null === $offer) {
                return;
            }

            $variations = $this->productVariationChoice->fetchProductVariationByOffer($offer);

            // Если у продукта нет множественных вариантов
            if (empty($variations)) {
                $form->add(
                    'preVariation',
                    HiddenType::class
                );

                return;
            }

            $label = current($variations)->getOption();

            $form
                ->add('preVariation', ChoiceType::class, [
                    'choices' => $variations,
                    'choice_value' => function (?ProductOfferVariationConst $variation) {
                        return $variation?->getValue();
                    },
                    'choice_label' => function (ProductOfferVariationConst $variation) {
                        return $variation->getAttr();
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ])
            ;
        };

        $builder->get('preOffer')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formVariationModifier) {
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
                function ($modification) {
                    return $modification instanceof ProductOfferVariationModificationConst ? $modification->getValue() : $modification;
                },
                function ($modification) {
                    return $modification ? new ProductOfferVariationModificationConst($modification) : null;
                }
            )
        );

        $formModificationModifier = function (FormInterface $form, ProductOfferVariationConst $variation = null) {
            if (null === $variation) {
                return;
            }

            $modifications = $this->modificationChoice->fetchProductModificationByVariation($variation);

            // Если у продукта нет множественных вариантов
            if (empty($modifications)) {
                $form->add(
                    'preModification',
                    HiddenType::class
                );

                return;
            }

            $label = current($modifications)->getOption();

            $form
                ->add('preModification', ChoiceType::class, [
                    'choices' => $modifications,
                    'choice_value' => function (?ProductOfferVariationModificationConst $modification) {
                        return $modification?->getValue();
                    },
                    'choice_label' => function (ProductOfferVariationModificationConst $modification) {
                        return $modification->getAttr();
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ])
            ;
        };

        $builder->get('preVariation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModificationModifier) {
                $variation = $event->getForm()->getData();
                $formModificationModifier($event->getForm()->getParent(), $variation);
            }
        );

//        $builder->addEventListener(
//          FormEvents::SUBMIT,
//          function (FormEvent $event) use ($formModifier)
//          {
//              $data = $event->getData();
//              $form = $event->getForm();
//
//              if($data->getPreOffer())
//              {
//                  $formModifier($form, $data->getPreMaterial(), $data->getPreOffer());
//              }
//          }
//        );

//        $builder->get('preMaterial')->addEventListener(
//          FormEvents::POST_SUBMIT,
//          function (FormEvent $event) use ($formModifier)
//          {
//              $material = $event->getForm()->getData();
//              $formModifier($event->getForm()->getParent(), $material);
//          }
//        );

        // Склад

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $data = $event->getData();
                $form = $event->getForm();

                $warehouses = $this->warehouseChoice->fetchWarehouseByProfile($data->getProfile());

                if (1 === count($warehouses)) {
                    $data->setPreWarehouse(current($warehouses));
                }

                // Склад
                $form
                    ->add('preWarehouse', ChoiceType::class, [
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
            'incoming',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

//    public function addWarehouse(FormInterface $form, MaterialUid $material, MaterialOfferUid $offerUid): void
//    {
//        $warehouse = $this->warehouseChoice->get($material, $offerUid);
//
//        if (empty($warehouse)) {
//            $form->add(
//                'preWarehouse',
//                ChoiceType::class,
//                ['disabled' => true, 'placeholder' => 'Сырье отсутствует на складе']);
//
//            return;
//        }
//
//        $form->add('preWarehouse', ChoiceType::class, [
//            'choices' => $this->warehouseChoice->get($material, $offerUid),
//            'choice_value' => function (?MaterialWarehouseUid $warehouse) {
//                return $warehouse?->getValue();
//            },
//            'choice_label' => function (MaterialWarehouseUid $warehouse) {
//                return $warehouse->getName() . ' (' . $warehouse->getTotal() . ' шт.)';
//            },
//            'choice_attr' => function ($choice) {
//                return ['data-total' => $choice->getTotal()];
//            },
//            'label' => false,
//            'expanded' => false,
//            'multiple' => false,
//            'required' => true,
//            'placeholder' => 'Выберите склад из списка...'
//        ]);
//    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => IncomingProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ]
        );
    }
}
