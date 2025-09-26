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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\UseCase\Admin\Stock;

use BaksDev\Products\Stocks\Repository\ProductStocksTotalByProductChoice\ProductStocksTotalByProductChoiceInterface;
use BaksDev\Products\Stocks\Type\Total\ProductStockTotalUid;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MovingProductToStockForm extends AbstractType
{
    public function __construct(
        private readonly ProductStocksTotalByProductChoiceInterface $ProductStocksTotalByProductChoice,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();

            /** @var MovingProductToStockDTO $data */
            $data = $event->getData();

            /** @var MovingProductToStockDTO $data */
            $productStocksTotalByProduct = $this->ProductStocksTotalByProductChoice
                ->profile($this->userProfileTokenStorage->getProfile())
                ->product($data->getProduct())
                ->offer($data->getOffer())
                ->variation($data->getVariation())
                ->modification($data->getModification())
                ->skipId($data->getFromId())
                ->fetchStocksByProduct();

            $form->add('toId', ChoiceType::class, [
                'choices' => $productStocksTotalByProduct,
                'choice_value' => function(?ProductStockTotalUid $productStockTotal) {
                    return $productStockTotal?->getValue();
                },
                'choice_label' => function(ProductStockTotalUid $productStockTotal) {
                    return $productStockTotal->getAttr()
                        .' ('.$productStockTotal->getOption()
                        .(false === empty($productStockTotal->getProperty()) ? ', '.$productStockTotal->getProperty() : '').')';
                },

                'placeholder' => 'Добавить новое место складирования',
                'required' => false,
            ]);

            $form->add('totalToMove',
                IntegerType::class,
                ['attr' => [
                    'min' => 1,
                    'max' => ($data->getTotal() - $data->getReserve()),
                ]]);

        });

        $builder->add('product_stock_storage_move', SubmitType::class, ['label_html' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => MovingProductToStockDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ],
        );
    }
}