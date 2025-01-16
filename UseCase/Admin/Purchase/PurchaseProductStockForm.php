<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Stocks\UseCase\Admin\Purchase;

use BaksDev\Products\Category\Repository\CategoryChoice\CategoryChoiceInterface;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Repository\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Product\Repository\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Product\Repository\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Product\Repository\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
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
    public function __construct(
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
        private readonly CategoryChoiceInterface $categoryChoice,
        private readonly ProductChoiceInterface $productChoice,
        private readonly ProductOfferChoiceInterface $productOfferChoice,
        private readonly ProductVariationChoiceInterface $productVariationChoice,
        private readonly ProductModificationChoiceInterface $modificationChoice,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Номер заявки
        $builder->add('number', TextType::class);


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {
            /** @var PurchaseProductStockDTO $PurchaseProductStockDTO */
            $PurchaseProductStockDTO = $event->getData();
            $PurchaseProductStockDTO->setProfile($this->userProfileTokenStorage->getProfile());
        });

        $builder->add('category', ChoiceType::class, [
            'choices' => $this->categoryChoice->findAll(),
            'choice_value' => function(?CategoryProductUid $category) {
                return $category?->getValue();
            },
            'choice_label' => function(CategoryProductUid $category) {
                return (is_int($category->getAttr()) ? str_repeat(' - ', $category->getAttr() - 1) : '').$category->getOptions();
            },
            'label' => false,
            'required' => false,
        ]);


        /**
         * Продукция категории
         */

        $builder->add(
            'preProduct',
            HiddenType::class,
        );

        $builder
            ->get('preProduct')->addModelTransformer(
                new CallbackTransformer(
                    function($product) {
                        return $product instanceof ProductUid ? $product->getValue() : $product;
                    },
                    function($product) {
                        return $product ? new ProductUid($product) : null;
                    }
                ),
            );


        /**
         * Торговые предложения
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
                }
            ),
        );

        /**
         * Множественный вариант торгового предложения
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
                }
            ),
        );

        /**
         * Модификация множественного варианта торгового предложения
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
                }
            ),
        );


        /**
         * Событие на изменение
         */

        $builder->get('preVariation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {

                $parent = $event->getForm()->getParent();

                if(!$parent)
                {
                    return;
                }

                $category = $parent->get('category')->getData();
                $product = $parent->get('preProduct')->getData();
                $offer = $parent->get('preOffer')->getData();
                $variation = $parent->get('preVariation')->getData();

                if($category)
                {
                    $this->formProductModifier($event->getForm()->getParent(), $category);
                }

                if($product)
                {
                    $this->formOfferModifier($event->getForm()->getParent(), $product);
                }

                if($offer)
                {
                    $this->formVariationModifier($event->getForm()->getParent(), $offer);
                }

                if($variation)
                {
                    $this->formModificationModifier($event->getForm()->getParent(), $variation);
                }
            },
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


    private function formProductModifier(FormInterface $form, ?CategoryProductUid $category): void
    {

        /** Получаем список доступной продукции */
        $productChoice = $this->productChoice->fetchAllProduct($category ?: false);


        $form->add(
            'preProduct',
            ChoiceType::class,
            [
                'choices' => $productChoice,
                'choice_value' => function(?ProductUid $product) {
                    return $product?->getValue();
                },

                'choice_label' => function(ProductUid $product) {
                    return $product->getAttr();
                },
                'choice_attr' => function(?ProductUid $product) {
                    return $product ? [
                        'data-filter' => ' ['.$product->getOption().']',
                        'data-max' => $product->getOption(),
                        'data-name' => $product->getAttr(),
                    ] : [];
                },
                'label' => false,
            ]
        );
    }


    private function formOfferModifier(FormInterface $form, ?ProductUid $product): void
    {

        if(null === $product)
        {
            return;
        }

        $offer = $this->productOfferChoice->findByProduct($product);

        // Если у продукта нет ТП
        if(!$offer->valid())
        {
            $form->add('preOffer', HiddenType::class);
            $form->add('preVariation', HiddenType::class);
            $form->add('preModification', HiddenType::class);
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
    }

    private function formVariationModifier(FormInterface $form, ?ProductOfferConst $offer): void
    {

        if(null === $offer)
        {
            return;
        }

        $variations = $this->productVariationChoice->fetchProductVariationByOfferConst($offer);

        // Если у продукта нет множественных вариантов
        if(!$variations->valid())
        {
            $form->add('preVariation', HiddenType::class);
            $form->add('preModification', HiddenType::class);

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
                    return $variation?->getCharacteristic() ? ['data-filter' => ' ('.$variation?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }

    private function formModificationModifier(FormInterface $form, ?ProductVariationConst $variation): void
    {
        if(null === $variation)
        {
            return;
        }

        $modifications = $this->modificationChoice->fetchProductModificationConstByVariationConst($variation);

        // Если у продукта нет модификаций множественных вариантов
        if(!$modifications->valid())
        {
            $form->add('preModification', HiddenType::class);
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
                    return $modification?->getCharacteristic() ? ['data-filter' => ' ('.$modification?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
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
