/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

document.querySelectorAll('button.moving').forEach(function(movingButton)
{
    movingButton.addEventListener('click', async function(event)
    {
        const formData = new FormData();
        formData.append('moving_product_stock_form[preProduct]', event.currentTarget.dataset.product);
        formData.append('moving_product_stock_form[preOffer]', event.currentTarget.dataset.offer);
        formData.append('moving_product_stock_form[preVariation]', event.currentTarget.dataset.variation);
        formData.append('moving_product_stock_form[preModification]', event.currentTarget.dataset.modification);
        formData.append('moving_product_stock_form[targetWarehouse]', event.currentTarget.dataset.target);
        formData.append('moving_product_stock_form[destinationWarehouse]', event.currentTarget.dataset.profile);

        await fetch(event.currentTarget.dataset.href, {
            method: 'POST', // *GET, POST, PUT, DELETE, etc.
            cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
            credentials: 'same-origin', // include, *same-origin, omit
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            redirect: 'follow', // manual, *follow, error
            referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
            body: formData // body data type must match "Content-Type" header
        })
            .then((response) =>
            {
                if(response.status !== 200)
                {
                    return false;
                }

                return response.text();
            })
            .then((data) =>
            {
                if(data)
                {
                    const modal = document.getElementById('modal');
                    modal.innerHTML = data;

                    modal.querySelectorAll("[data-select=\"select2\"]").forEach(function(item)
                    {
                        new NiceSelect(item, {searchable : true});
                    });
                }
            });
    });
})

