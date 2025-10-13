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

namespace BaksDev\Products\Stocks\Forms\MoveFilter\Admin;

use BaksDev\Delivery\Forms\Delivery\DeliveryForm;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Application\Forms\ManufactureApplicationFilter\Admin\ManufactureApplicationFilterDTO;
use BaksDev\Manufacture\Part\Application\Type\Status\ManufactureApplicationStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentAllUserProfiles\CurrentAllUserProfilesByUserInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductStockMoveFilterForm extends AbstractType
{
    private string $sessionKey;

    private SessionInterface|false $session = false;

    public function __construct(
        private readonly RequestStack $request,
        private readonly CurrentAllUserProfilesByUserInterface $CurrentAllUserProfilesByUserRepository
    )
    {
        $this->sessionKey = md5(self::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /* TextType */
        $builder->add('profile', ChoiceType::class, [
            'choices' => $this->CurrentAllUserProfilesByUserRepository->findAll(),
            'choice_value' => function(mixed $profile) {
                return $profile;
            },
            'choice_label' => function(UserProfileUid $profile) {
                return $profile->getParams()->username;
            },
            'label' => false,
            'expanded' => false,
            'multiple' => false,
            'required' => false,
            'attr' => ['data-select' => 'select2',],
        ]);


        $builder->get('profile')->addModelTransformer(
            new CallbackTransformer(
                function($profile) {
                    return $profile instanceof UserProfileUid ? $profile->getValue() : $profile;
                },
                function($profile) {
                    return $profile ? new UserProfileUid($profile) : null;
                },
            ),
        );


        $builder->add('date', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
            'required' => false,
            'format' => 'dd.MM.yyyy',
            'input' => 'datetime_immutable',
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            $sessionArray = false;

            /** @var ProductStockMoveFilterDTO $data */
            $data = $event->getData();

            $Request = $this->request->getMainRequest();

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

            if($sessionArray !== false)
            {
                isset($sessionArray['profile']) ? $data->setProfile(new UserProfileUid($sessionArray['profile']) ?? null) : false;
                isset($sessionArray['date']) ? $data->setDate(new DateTimeImmutable($sessionArray['date'])) : false;

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
                    /** @var ProductStockMoveFilterDTO $data */
                    $data = $event->getData();

                    $sessionArray = [];

                    $data->getProfile() ? $sessionArray['profile'] = (string) $data->getProfile() : false;
                    $data->getDate() ? $sessionArray['date'] = $data->getDate()->format(DateTimeInterface::W3C) : false;

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


        /*$builder->add(
            'back',
            SubmitType::class,
            ['label' => 'Back', 'label_html' => true, 'attr' => ['class' => 'btn-light']],
        );*/


        /*$builder->add(
            'next',
            SubmitType::class,
            ['label' => 'next', 'label_html' => true, 'attr' => ['class' => 'btn-light']],
        );*/
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => ProductStockMoveFilterDTO::class,
                'method' => 'POST',
            ],
        );
    }
}
