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

namespace BaksDev\Products\Stocks\UseCase\Admin\Incoming\Products;

use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductStockForm extends AbstractType
{
    public function __construct(
        private readonly ProductDetailByConstInterface $productDetailByConst,
        private readonly Security $security
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ROLE_PRODUCT_STOCK_EDIT


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            /** @var ProductStockDTO $product */
            $product = $event->getData();

            if($product)
            {
                $product->detail = $this->productDetailByConst
                    ->product($product->getProduct())
                    ->offerConst($product->getOffer())
                    ->variationConst($product->getVariation())
                    ->modificationConst($product->getModification())
                    ->find() ?: null;
            }

        });

        // Количество

        $builder->add('total', IntegerType::class, [
            'disabled' => false === (
                    $this->security->isGranted('ROLE_ADMIN')
                    || $this->security->isGranted('ROLE_PRODUCT_STOCK_EDIT')
                ),
        ]);

        $builder->add('storage', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductStockDTO::class,
        ]);
    }
}
