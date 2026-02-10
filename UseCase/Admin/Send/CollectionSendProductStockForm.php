<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Send;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Repository\ProductChoice\ProductChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductModificationChoice\ProductModificationChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductOfferChoice\ProductOfferChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductVariationChoice\ProductVariationChoiceWarehouseInterface;
use BaksDev\Products\Stocks\Repository\ProductWarehouseChoice\ProductWarehouseChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentAllUserProfiles\CurrentAllUserProfilesByUserInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
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

final class CollectionSendProductStockForm extends AbstractType
{
    private UserUid $user;

    public function __construct(
        private readonly ProductChoiceWarehouseInterface $productChoiceWarehouseRepository,
        private readonly ProductOfferChoiceWarehouseInterface $productOfferChoiceWarehouseRepository,
        private readonly ProductVariationChoiceWarehouseInterface $productVariationChoiceWarehouseRepository,
        private readonly ProductModificationChoiceWarehouseInterface $productModificationChoiceWarehouseRepository,
        private readonly ProductWarehouseChoiceInterface $productWarehouseChoiceRepository,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
        private readonly CurrentAllUserProfilesByUserInterface $CurrentAllUserProfilesByUserRepository,
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /**
         * Подукция
         *
         * @var ProductUid $product
         */
        $builder->add('preProduct', TextType::class, ['attr' => ['disabled' => true]]);

        /** Список имеющейся продукции только у текущего профиля */
        $productChoiceWarehouse = $this->productChoiceWarehouseRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->getProductsExistWarehouse();

        if($productChoiceWarehouse->valid())
        {
            $builder->add(
                'preProduct',
                ChoiceType::class,
                [
                    'choices' => $productChoiceWarehouse,
                    'choice_value' => function(?ProductUid $product) {
                        return $product?->getValue();
                    },
                    'choice_label' => function(ProductUid $product) {
                        return $product->getAttr();
                    },
                    'choice_attr' => function(?ProductUid $product) {

                        if(!$product)
                        {
                            return [];
                        }

                        if($product->getAttr())
                        {
                            $attr['data-name'] = $product->getAttr();
                        }

                        if($product->getOption())
                        {
                            $attr['data-filter'] = '('.$product->getOption().')';
                            $attr['data-max'] = $product->getOption();
                        }

                        return $attr;
                    },

                    'label' => false,
                ],
            );
        }

        /**
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
                function($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                },
            ),
        );

        /**
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
                function($variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function($variation) {
                    return $variation ? new ProductVariationConst($variation) : null;
                },
            ),
        );

        /**
         * Модификация множественного варианта торгового предложения
         *
         * @var ProductModificationConst $modification
         */
        $builder->add(
            'preModification',
            HiddenType::class,
        );

        $builder->get('preModification')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new ProductModificationConst($modification) : null;
                },
            ),
        );


        //        /* Целевой склад */
        //        $builder->add(
        //            'targetWarehouse',
        //            ChoiceType::class,
        //            [
        //                'choices' => [],
        //                'label' => false,
        //                'required' => false,
        //            ],
        //        );


        $builder->get('preModification')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {
                $parent = $event->getForm()->getParent();

                if(!$parent)
                {
                    return;
                }

                $product = $parent->get('preProduct')->getData();
                $offer = $parent->get('preOffer')->getData();
                $variation = $parent->get('preVariation')->getData();
                $modification = $parent->get('preModification')->getData();

                /** Присваиваем значения для заполнения формы выбранными значениями */
                $MovingProductStockDTO = $parent->getData();

                $MovingProductStockDTO
                    ->setPreProduct($product)
                    ->setPreOffer($offer)
                    ->setPreVariation($variation)
                    ->setPreModification($modification);


                if($product)
                {
                    $this->formOfferModifier($event->getForm()->getParent(), $product);
                }

                if($product && $offer)
                {
                    $this->formVariationModifier($event->getForm()->getParent(), $product, $offer);
                }

                if($product && $offer && $variation)
                {
                    $this->formModificationModifier($event->getForm()->getParent(), $product, $offer, $variation);
                }

                //                if($product && $offer && $variation && $modification)
                //                {
                //                    $this->formTargetWarehouseModifier(
                //                        $event->getForm()->getParent(),
                //                        $product,
                //                        $offer,
                //                        $variation,
                //                        $modification);
                //                }
            },
        );


        /* Склад отгрузки (грузоотправитель) */
        $builder->add('targetWarehouse', HiddenType::class);
        $builder->get('targetWarehouse')->addModelTransformer(
            new CallbackTransformer(
                function($warehouse) {
                    return $warehouse instanceof UserProfileUid ? $warehouse->getValue() : $warehouse;
                },
                function($warehouse) {

                    return new UserProfileUid($warehouse);
                },
            ),
        );


        // Количество
        $builder->add('preTotal', IntegerType::class, ['required' => false]);


        /** Коллекция добавленной продукции  */

        $builder->add(
            'move',
            CollectionType::class,
            [
                'entry_type' => SendProductStockForm::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'by_reference' => false,
                'allow_delete' => true,
                'allow_add' => true,
                'prototype_name' => '__product__',
            ],
        );

        $builder->add('comment', TextareaType::class, ['required' => false]);

        // Сохранить
        $builder->add(
            'send',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']],
        );


        /** Целевой склад (грузополучатель) */

        $result = $this->CurrentAllUserProfilesByUserRepository
            ->forUser($this->UserProfileTokenStorage->getUser())
            ->findAll();

        if(false === $result || false === $result->valid())
        {
            return;
        }

        /** Список профилей пользователя, кроме текущего */
        $warehouses = array_filter(iterator_to_array($result), function(UserProfileUid $userProfileUid) {
            return $userProfileUid->equals($this->UserProfileTokenStorage->getProfile()) === false;
        });

        $builder->add(
            'destinationWarehouse',
            ChoiceType::class,
            [
                'choices' => $warehouses,
                'choice_value' => function(?UserProfileUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function(UserProfileUid $product) {
                    return $product->getParams()->username;
                },

                'choice_attr' => function(?UserProfileUid $warehouse) {

                    if(!$warehouse)
                    {
                        return [];
                    }


                    if($warehouse->getParams())
                    {
                        $attr['data-name'] = $warehouse->getParams()->username;
                    }

                    //                    if($warehouse->getProperty())
                    //                    {
                    //                        $attr['data-max'] = $warehouse->getProperty();
                    //                        $attr['data-filter'] = '('.$warehouse->getProperty().')';
                    //                    }

                    return $attr;
                },

                // name


                'label' => false,
            ],
        );
    }


    private function formOfferModifier(FormInterface $form, ProductUid $ProductUid): void
    {
        $offer = $this->productOfferChoiceWarehouseRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->forProduct($ProductUid)
            ->getProductsOfferExistWarehouse();


        // Если у продукта нет ТП
        if(false === $offer->valid())
        {
            $form->add('preOffer', HiddenType::class, ['data' => null]);
            return;
        }

        $currentOffer = $offer->current();
        $label = $currentOffer->getOption();
        $domain = null;

        if($currentOffer->getReference())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentOffer->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preOffer',
                ChoiceType::class,
                [
                    'choices' => $offer,
                    'choice_value' => function(?ProductOfferConst $offer) {
                        return $offer?->getValue();
                    },
                    'choice_label' => function(ProductOfferConst $offer) {
                        return $offer->getAttr();
                    },
                    'choice_attr' => function(?ProductOfferConst $offer) {

                        if(!$offer)
                        {
                            return [];
                        }

                        if($offer->getAttr())
                        {
                            $attr['data-name'] = $offer->getAttr();
                        }

                        if($offer->getProperty())
                        {
                            $attr['data-filter'] = trim($offer->getCharacteristic().' ('.$offer->getProperty().')');
                            $attr['data-max'] = $offer->getProperty();
                        }

                        return $attr;

                    },
                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    private function formVariationModifier(FormInterface $form, ProductUid $product, ProductOfferConst $offer): void
    {
        /** Только текущего профиля */
        $variations = $this->productVariationChoiceWarehouseRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->product($product)
            ->offerConst($offer)
            ->getProductsVariationExistWarehouse();

        // Если у продукта нет множественных вариантов
        if(false === $variations->valid())
        {
            $form->add('preVariation', HiddenType::class, ['data' => null]);
            return;
        }

        $currentVariation = $variations->current();
        $label = $currentVariation->getOption();
        $domain = null;

        if($currentVariation->getReference())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentVariation->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preVariation',
                ChoiceType::class,
                [
                    'choices' => $variations,
                    'choice_value' => function(?ProductVariationConst $variation) {
                        return $variation?->getValue();
                    },
                    'choice_label' => function(ProductVariationConst $variation) {
                        return $variation->getAttr();
                    },
                    'choice_attr' => function(?ProductVariationConst $variation) {
                        if(!$variation)
                        {
                            return [];
                        }

                        if($variation->getAttr())
                        {
                            $attr['data-name'] = $variation->getAttr();
                        }

                        if($variation->getProperty())
                        {
                            $attr['data-filter'] = trim($variation->getCharacteristic().' ('.$variation->getProperty().')');
                            $attr['data-max'] = $variation->getProperty();
                        }

                        return $attr;
                    },
                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    private function formModificationModifier(
        FormInterface $form,
        ProductUid $product,
        ProductOfferConst $offer,
        ProductVariationConst $variation,
    ): void
    {
        /** Список Modification только по текущему профилю */
        $modifications = $this->productModificationChoiceWarehouseRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->product($product)
            ->offerConst($offer)
            ->variationConst($variation)
            ->getProductsModificationExistWarehouse();


        // Если у продукта нет модификаций множественных вариантов
        if(false === $modifications->valid())
        {
            $form->add('preModification', HiddenType::class);
            //$this->formTargetWarehouseModifier($form, $product, $offer, $variation);

            return;
        }

        $currentModification = $modifications->current();
        $label = $currentModification->getOption();
        $domain = null;

        if($currentModification->getReference())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentModification->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preModification',
                ChoiceType::class,
                [
                    'choices' => $modifications,
                    'choice_value' => function(?ProductModificationConst $modification) {
                        return $modification?->getValue();
                    },
                    'choice_label' => function(ProductModificationConst $modification) {
                        return $modification->getAttr();
                    },

                    'choice_attr' => function(?ProductModificationConst $modification) {

                        if(!$modification)
                        {
                            return [];
                        }

                        if($modification->getAttr())
                        {
                            $attr['data-name'] = $modification->getAttr();
                        }

                        if($modification->getProperty())
                        {
                            $attr['data-filter'] = trim($modification->getCharacteristic().' ('.$modification->getProperty().')');
                            $attr['data-max'] = $modification->getProperty();
                        }

                        return $attr;
                    },
                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => CollectionSendProductStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ],
        );
    }
}
