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

executeFunc(function productsStatusFilter()
{
    if(typeof formDebounce !== 'function')
    {
        return false;
    }

    const form = document.forms.product_stock_status_filter_form;


    if(typeof form === 'undefined')
    {
        return false;
    }

    form.addEventListener('click', () =>
    {
        if(idFormDebounce == lastFormDebounce)
        {
            /* Сбрасываем отправку формы, если выбран выпадающий список */
            clearTimeout(lastFormDebounce);
        }

        lastFormDebounce = idFormDebounce;
    });

    const inputFields = form.querySelectorAll('input, select, textarea');

    // Добавляем обработчик изменения для каждого поля ввода
    inputFields.forEach(field =>
    {
        if(field.id === 'product_stock_status_filter_form_status')
        {
            field.addEventListener('change', () =>
            {

                setTimeout(() => { form.submit(); }, 300);

            });

            return;
        }

        field.addEventListener('change', formDebounce(() => { form.submit(); }, 1500));

    });

    return true;
});