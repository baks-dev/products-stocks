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

namespace BaksDev\Products\Stocks\UseCase\Admin\Extradition;

use Doctrine\Common\Collections\ArrayCollection;

final class ExtraditionSelectedProductStockDTO
{
    private ArrayCollection $collection;

    /** Комментарий */
    private ?string $comment = null;

    public function __construct()
    {
        $this->collection = new ArrayCollection();
    }

    public function getCollection(): ArrayCollection
    {
        return $this->collection;
    }

    public function addCollection(ExtraditionProductStockDTO $stockDTO): self
    {
        $this->collection->add($stockDTO);

        return $this;
    }

    public function removeCollection(ExtraditionProductStockDTO $stockDTO): self
    {
        $this->collection->removeElement($stockDTO);

        return $this;
    }
}