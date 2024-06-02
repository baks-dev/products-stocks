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

namespace BaksDev\Products\Stocks\Forms\PickupFilter\Admin;

use BaksDev\Delivery\Type\Id\DeliveryUid;

use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Products\Stocks\Forms\PickupFilter\ProductStockPickupFilterInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

final class ProductStockPickupFilterDTO implements ProductStockPickupFilterInterface
{
    public const date = 'zbJIvDNPeN';

    public const delivery = 'zFFtvNTQsC';


    private Request $request;

    private ?DeliveryUid $delivery = null;


    /**
     * Дата
     */
    private ?DateTimeImmutable $date = null;


    /**
     * Номер тел клиента
     */
    private mixed $phone = null;



    public function __construct(Request $request)
    {
        $this->request = $request;
    }


    /**
     * Date.
     */
    public function getDate(): ?DateTimeImmutable
    {
        $session = $this->request->getSession();

        $sessionDate = $session->get(self::date) ?: null;

        if(time() - $session->getMetadataBag()->getLastUsed() > 300)
        {
            $session->remove(self::date);
            $session->remove(self::delivery);

            $this->date = null;
            $this->delivery = null;
        }

        return $this->date ?: $sessionDate ;
    }

    public function setDate(?DateTimeImmutable $date): void
    {
        if ($date === null)
        {
            $this->request->getSession()->remove(self::date);
        }
        else
        {
            $this->request->getSession()->set(self::date, $date);
        }

        $this->date = $date;
    }

    /**
     * Delivery
     */
    public function getDelivery(): ?DeliveryUid
    {
        return $this->delivery ?: $this->request->getSession()->get(self::delivery);
    }

    public function setDelivery(?DeliveryUid $delivery): self
    {
        if($delivery === null)
        {
            $this->request->getSession()->remove(self::delivery);
        }

        $this->delivery = $delivery;

        return $this;
    }

    /**
     * Phone
     */
    public function getPhone(): mixed
    {
        if($this->phone)
        {
            dd($this->phone);
        }

        return $this->phone;
    }

    public function setPhone(mixed $phone): self
    {
        $this->phone = $phone;
        return $this;
    }



}
