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

namespace BaksDev\Products\Stocks\Forms\PackageFilter\Admin;

use BaksDev\Delivery\Forms\Delivery\DeliveryForm;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Application\Forms\ManufactureApplicationFilter\Admin\ManufactureApplicationFilterDTO;
use BaksDev\Manufacture\Part\Application\Type\Status\ManufactureApplicationStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductStockPackageFilterForm extends AbstractType
{
    private string $sessionKey;

    private SessionInterface|false $session = false;

    public function __construct(private readonly RequestStack $request)
    {
        $this->sessionKey = md5(self::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add('delivery', DeliveryForm::class, ['required' => false]);

        $builder->add('date', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
            'required' => false,
            'format' => 'dd.MM.yyyy',
            'input' => 'datetime_immutable',
        ]);

        $builder->add('print', ChoiceType::class, [
            'label' => false,
            'choices' => [
                'Без печати' => false,
                'Печать выполнена' => true,
            ],
            'expanded' => false,
            'required' => true,
        ]);


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            $sessionArray = false;

            /** @var ProductStockPackageFilterDTO $data */
            $data = $event->getData();

            $Request = $this->request->getMainRequest();

            if($Request && 'POST' === $Request->getMethod())
            {
                $sessionArray = current($Request->request->all());
            }
            else
            {
                if($this->session === false)
                {
                    $this->session = $this->request->getSession();
                }

                if($this->session && $this->session->get('statusCode') === 307)
                {
                    $this->session->remove($this->sessionKey);
                    $this->session = false;
                }

                if($this->session && (time() - $this->session->getMetadataBag()->getLastUsed()) > 300)
                {
                    $this->session->remove($this->sessionKey);
                    $this->session = false;
                }

                if($this->session)
                {
                    $sessionData = $this->request->getSession()->get($this->sessionKey);
                    $sessionJson = $sessionData ? base64_decode($sessionData) : false;
                    $sessionArray = $sessionJson !== false && json_validate($sessionJson) ? json_decode($sessionJson, true, 512, JSON_THROW_ON_ERROR) : false;
                }
            }

            if($sessionArray !== false)
            {
                isset($sessionArray['delivery']) ? $data->setDelivery(new DeliveryUid($sessionArray['delivery'], $sessionArray['category_name'] ?? null)) : false;
                isset($sessionArray['date']) ? $data->setDate(new DateTimeImmutable($sessionArray['date'])) : false;
                isset($sessionArray['print']) ? $data->setPrint($sessionArray['print']) : false;

                if($Request && 'POST' === $Request->getMethod())
                {
                    $sessionJson = json_encode($sessionArray, JSON_THROW_ON_ERROR);
                    $sessionData = base64_encode($sessionJson);
                    $this->request->getSession()->set($this->sessionKey, $sessionData);
                }

            }

        });


        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {

                if($this->session === false)
                {
                    $this->session = $this->request->getSession();
                }

                if($this->session)
                {
                    /** @var ProductStockPackageFilterDTO $data */
                    $data = $event->getData();

                    $sessionArray = [];

                    $data->getDelivery() ? $sessionArray['delivery'] = (string) $data->getDelivery() : false;
                    $data->getDate() ? $sessionArray['date'] = $data->getDate()->format(DateTimeInterface::W3C) : false;
                    $sessionArray['print'] = $data->getPrint();

                    if($sessionArray)
                    {
                        $sessionJson = json_encode($sessionArray, JSON_THROW_ON_ERROR);
                        $sessionData = base64_encode($sessionJson);
                        $this->request->getSession()->set($this->sessionKey, $sessionData);
                        return;
                    }

                    $this->session->remove($this->sessionKey);
                }
            },
        );


        //        $builder->addEventListener(
        //            FormEvents::POST_SUBMIT,
        //            function(FormEvent $event): void {
        //                /** @var ProductStockPackageFilterDTO $data */
        //                $data = $event->getData();
        //
        //                $this->request->getSession()->set(ProductStockPackageFilterDTO::date, $data->getDate());
        //                $this->request->getSession()->set(ProductStockPackageFilterDTO::delivery, $data->getDelivery());
        //            },
        //        );


        $builder->add(
            'back',
            SubmitType::class,
            ['label' => 'Back', 'label_html' => true, 'attr' => ['class' => 'btn-light']],
        );


        $builder->add(
            'next',
            SubmitType::class,
            ['label' => 'next', 'label_html' => true, 'attr' => ['class' => 'btn-light']],
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => ProductStockPackageFilterDTO::class,
                'method' => 'POST',
            ],
        );
    }
}
