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

namespace BaksDev\Products\Stocks\UseCase\Admin\Moving;

use BaksDev\Contacts\Region\Repository\WarehouseChoice\WarehouseChoiceInterface;
use BaksDev\Contacts\Region\Type\Call\Const\ContactsRegionCallConst;
use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Repository\ProductChoice\ProductChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductModificationChoice\ProductModificationChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductOfferChoice\ProductOfferChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductVariationChoice\ProductVariationChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseChoice\ProductWarehouseChoiceInterface;
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

final class MovingProductStockForm extends AbstractType
{
    private WarehouseChoiceInterface $warehouseChoice;

    private ProductChoiceWarehouseInterface $productChoiceWarehouse;

    private ProductVariationChoiceWarehouseInterface $productVariationChoiceWarehouse;

    private ProductOfferChoiceWarehouseInterface $productOfferChoiceWarehouse;

    private ProductModificationChoiceWarehouseInterface $productModificationChoiceWarehouse;

    //    private ProductOfferChoiceInterface $productOfferChoice;
    //    private ProductVariationChoiceInterface $productVariationChoice;
    //    private ProductModificationChoiceInterface $modificationChoice;
    private ProductWarehouseChoiceInterface $productWarehouseChoice;

    public function __construct(
        WarehouseChoiceInterface $warehouseChoice,
        ProductChoiceWarehouseInterface $productChoiceWarehouse,
        ProductOfferChoiceWarehouseInterface $productOfferChoiceWarehouse,
        ProductVariationChoiceWarehouseInterface $productVariationChoiceWarehouse,
        ProductModificationChoiceWarehouseInterface $productModificationChoiceWarehouse,
        ProductWarehouseChoiceInterface $productWarehouseChoice,

        //        ProductOfferChoiceInterface $productOfferChoice,
        //        ProductVariationChoiceInterface $productVariationChoice,
        //        ProductModificationChoiceInterface $modificationChoice,
    ) {
        $this->warehouseChoice = $warehouseChoice;
        $this->productChoiceWarehouse = $productChoiceWarehouse;
        $this->productOfferChoiceWarehouse = $productOfferChoiceWarehouse;
        $this->productVariationChoiceWarehouse = $productVariationChoiceWarehouse;
        $this->productModificationChoiceWarehouse = $productModificationChoiceWarehouse;
        $this->productWarehouseChoice = $productWarehouseChoice;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Номер заявки
        // $builder->add('number', TextType::class);

        //        $builder->get('number')->addModelTransformer(
        //            new CallbackTransformer(
        //                function ($offer) {
        //                    return (string) $offer;
        //                },
        //                function ($offer) {
        //                    return  (string) $offer;
        //                }
        //            )
        //        );

        // Продукт *************************************************************************************************

        /*
         * Подукция
         *
         * @var ProductUid $product
         */

        $builder->add(
            'preProduct',
            ChoiceType::class,
            [
                'choices' => $this->productChoiceWarehouse->getProductsExistWarehouse(),
                'choice_value' => function (?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function (ProductUid $product) {
                    return $product->getAttr().' ('.$product->getOption().')';
                },
                'choice_attr' => function (?ProductUid $product) {
                    return $product ? ['data-name' => $product->getAttr()] : [];
                },
                'label' => false,
            ]
        );

        /*
         * Торговые предложения
         *
         * @var ProductOfferConst $offer
         */

        $builder->add(
            'preOffer',
            HiddenType::class,
        );

        $builder->get('preOffer')->addModelTransformer(
            new CallbackTransformer(
                function ($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function ($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                }
            ),
        );

        /*
         * Множественный вариант торгового предложения
         *
         * @var ProductVariationConst $variation
         */

        $builder->add(
            'preVariation',
            HiddenType::class,
        );

        $builder->get('preVariation')->addModelTransformer(
            new CallbackTransformer(
                function ($variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function ($variation) {
                    return $variation ? new ProductVariationConst($variation) : null;
                }
            ),
        );

        /*
         * Модификация множественного варианта торгового предложения
         *
         * @var ProductOfferVariationModificationConst $modification
         */

        $builder->add(
            'preModification',
            HiddenType::class,
        );

        $builder->get('preModification')->addModelTransformer(
            new CallbackTransformer(
                function ($modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function ($modification) {
                    return $modification ? new ProductModificationConst($modification) : null;
                }
            ),
        );

        /* Целевой склад */
        $builder->add(
            'targetWarehouse',
            ChoiceType::class,
            [
                'choices' => [],
                'label' => false,
                'required' => false,
            ]
        );

        $builder->get('preModification')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event): void {
                $parent = $event->getForm()->getParent();

                if (!$parent)
                {
                    return;
                }

                $product = $parent->get('preProduct')->getData();
                $offer = $parent->get('preOffer')->getData();
                $variation = $parent->get('preVariation')->getData();
                $modification = $parent->get('preModification')->getData();

                if ($product)
                {
                    $this->formOfferModifier($event->getForm()->getParent(), $product);
                }

                if ($product && $offer)
                {
                    $this->formVariationModifier($event->getForm()->getParent(), $product, $offer);
                }

                if ($product && $offer && $variation)
                {
                    $this->formModificationModifier($event->getForm()->getParent(), $product, $offer, $variation);
                }

                if ($product && $offer && $variation && $modification)
                {
                    $this->formTargetWarehouseModifier($event->getForm()
                        ->getParent(), $product, $offer, $variation, $modification);
                }
            },
        );

        $warehouses = $this->warehouseChoice->fetchAllWarehouse();

        /** @var ?ContactsRegionCallUid $currentWarehouse */
        $currentWarehouse = (count($warehouses) === 1) ? current($warehouses) : null;

        if ($currentWarehouse)
        {
            $builder->addEventListener(
                FormEvents::PRE_SET_DATA,
                function (FormEvent $event) use ($currentWarehouse): void {
                    /** @var MovingProductStockDTO $data */
                    $data = $event->getData();

                    $data->setTargetWarehouse($currentWarehouse);
                    $data->setDestinationWarehouse($currentWarehouse);
                },
            );
        }

        /* Склад назначения */
        $builder->add(
            'destinationWarehouse',
            ChoiceType::class,
            [
                'choices' => $warehouses,
                'choice_value' => function (?ContactsRegionCallConst $warehouse) {
                    return $warehouse?->getValue();
                },
                'choice_label' => function (ContactsRegionCallConst $warehouse) {
                    return $warehouse->getAttr();
                },

                'label' => false,
                'required' => false,
            ]
        );

        // Количество
        $builder->add('preTotal', IntegerType::class, ['required' => false]);

        // Section Collection
//        $builder->add(
//            'product',
//            CollectionType::class,
//            [
//                'entry_type' => Products\ProductStockForm::class,
//                'entry_options' => ['label' => false],
//                'label' => false,
//                'by_reference' => false,
//                'allow_delete' => true,
//                'allow_add' => true,
//                'prototype_name' => '__product__',
//            ]
//        );


        // Section Collection
        $builder->add(
            'move',
            CollectionType::class,
            [
                'entry_type' => ProductStockForm::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'by_reference' => false,
                'allow_delete' => true,
                'allow_add' => true,
                'prototype_name' => '__product__',
            ]
        );

        

        $builder->add('comment', TextareaType::class, ['required' => false]);

        // Сохранить
        $builder->add(
            'moving',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']],
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => MovingProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ],
        );
    }

    private function formOfferModifier(FormInterface $form, ProductUid $product): void
    {
        $offer = $this->productOfferChoiceWarehouse->getProductsOfferExistWarehouse($product);

        // Если у продукта нет ТП
        if (empty($offer))
        {
            $form->add(
                'preOffer',
                HiddenType::class,
            );

            $this->formTargetWarehouseModifier($form, $product);

            return;
        }

        $label = current($offer)->getOption();

        $form
            ->add(
                'preOffer',
                ChoiceType::class,
                [
                    'choices' => $offer,
                    'choice_value' => function (?ProductOfferConst $offer) {
                        return $offer?->getValue();
                    },
                    'choice_label' => function (ProductOfferConst $offer) {
                        return $offer->getAttr().' ('.$offer->getCounter().')';
                    },
                    'choice_attr' => function (?ProductOfferConst $offer) {
                        return $offer ? ['data-name' => $offer->getAttr()] : [];
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]
            );
    }

    private function formVariationModifier(FormInterface $form, ProductUid $product, ProductOfferConst $offer): void
    {
        $variations = $this->productVariationChoiceWarehouse->getProductsVariationExistWarehouse($product, $offer);

        // Если у продукта нет множественных вариантов
        if (empty($variations))
        {
            $form->add('preVariation', HiddenType::class, );
            $this->formTargetWarehouseModifier($form, $product, $offer);

            return;
        }

        $label = current($variations)->getOption();

        $form
            ->add(
                'preVariation',
                ChoiceType::class,
                [
                    'choices' => $variations,
                    'choice_value' => function (?ProductVariationConst $variation) {
                        return $variation?->getValue();
                    },
                    'choice_label' => function (ProductVariationConst $variation) {
                        return $variation->getAttr().' ('.$variation->getCounter().')';
                    },
                    'choice_attr' => function (?ProductVariationConst $variation) {
                        return $variation ? ['data-name' => $variation->getAttr()] : [];
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    private function formModificationModifier(
        FormInterface         $form,
        ProductUid            $product,
        ProductOfferConst     $offer,
        ProductVariationConst $variation,
    ): void {
        $modifications = $this->productModificationChoiceWarehouse
            ->getProductsModificationExistWarehouse($product, $offer, $variation);

        // Если у продукта нет множественных вариантов
        if (empty($modifications))
        {
            $form->add('preModification', HiddenType::class, );
            $this->formTargetWarehouseModifier($form, $product, $offer, $variation);

            return;
        }

        $label = current($modifications)->getOption();

        $form
            ->add(
                'preModification',
                ChoiceType::class,
                [
                    'choices' => $modifications,
                    'choice_value' => function (?ProductModificationConst $modification) {
                        return $modification?->getValue();
                    },
                    'choice_label' => function (ProductModificationConst $modification) {
                        return $modification->getAttr().' ('.$modification->getCounter().')';
                    },
                    'choice_attr' => function (?ProductModificationConst $modification) {
                        return $modification ? ['data-name' => $modification->getAttr()] : [];
                    },
                    'label' => $label,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]
            );
    }

    private function formTargetWarehouseModifier(
        FormInterface             $form,
        ProductUid                $product,
        ?ProductOfferConst        $offer = null,
        ?ProductVariationConst    $variation = null,
        ?ProductModificationConst $modification = null,
    ): void {
        $warehouses = $this->productWarehouseChoice->fetchWarehouseByProduct($product, $offer, $variation, $modification);

        if (empty($warehouses))
        {
            $form->add(
                'targetWarehouse',
                ChoiceType::class,
                [
                    'choices' => [],
                    'label' => false,
                    'required' => false,
                ]
            );

            return;
        }

        $form->add(
            'targetWarehouse',
            ChoiceType::class,
            [
                'choices' => $warehouses,
                'choice_value' => function (?ContactsRegionCallConst $warehouse) {
                    return $warehouse?->getValue();
                },
                'choice_label' => function (ContactsRegionCallConst $warehouse) {
                    return $warehouse->getAttr().' ('.$warehouse->getCounter().')';
                },
                'choice_attr' => function (?ContactsRegionCallConst $warehouse) {
                    return $warehouse ? [
                        'data-name' => $warehouse->getAttr(),
                        'data-max' => $warehouse->getCounter(),
                    ] : [];
                },
                'label' => false,
                'required' => false,
            ]
        );
    }
}
