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


/** Добавить перемещение продукции со склада на склад */

var limit_nWgkuzbCRs = 1000;

setTimeout(function init_YFwgubGn()
{

    var object_product = document.getElementById('moving_product_stock_form_preProduct');

    if(object_product)
    {

        let focus = document.getElementById('moving_product_stock_form_preProduct_select2');
        focus ? focus.click() : null;

        //object_product.addEventListener('change', changeObjectProduct, false);
        object_product.addEventListener('change', function(event)
        {
            let forms = this.closest('form');
            changeObjectProduct(forms);
            return false;
        });

        let $addButtonStock = document.getElementById('moving_product_stock_form_addMoving');

        if($addButtonStock)
        {
            $addButtonStock.addEventListener('click', addProductMoving, false);
        }

        return;
    }

    if(limit_nWgkuzbCRs > 1000)
    {
        return;
    }

    limit_nWgkuzbCRs = limit_nWgkuzbCRs * 2;

    setTimeout(init_YFwgubGn, limit_nWgkuzbCRs);

}, 100);


async function changeObjectProduct(forms)
{

    const data = new FormData(forms);
    data.delete(forms.name + '[_token]');


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: data // body data type must match "Content-Type" header
    })

        //.then((response) => response)
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

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preOffer = result.getElementById('preOffer');
                preOffer ? document.getElementById('preOffer').replaceWith(preOffer) : preOffer.innerHTML = '';

                if(preOffer)
                {

                    /** SELECT2 */

                    let replaceOfferId = 'moving_product_stock_form_preOffer';

                    let replacer = document.getElementById(replaceOfferId);

                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});
                    }

                    let focus = document.getElementById('moving_product_stock_form_preOffer_select2');
                    focus ? focus.click() : null;

                } else
                {
                    let targetWarehouse = result.getElementById('targetWarehouse');

                    if(targetWarehouse)
                    {

                        document.getElementById('targetWarehouse').replaceWith(targetWarehouse);

                        /** SELECT2 */
                        let replacerWarehouse = document.getElementById('moving_product_stock_form_targetWarehouse');
                        replacer.addEventListener('change', changeObjectWarehause, false);

                        if(replacerWarehouse && replacerWarehouse.tagName === 'SELECT')
                        {
                            new NiceSelect(replacerWarehouse, {searchable: true});
                        }
                    }
                }


                /** сбрасываем зависимые поля */
                let preVariation = document.getElementById('preVariation');
                preVariation ? preVariation.innerHTML = '' : null;

                let preModification = document.getElementById('preModification');
                preModification ? preModification.innerHTML = '' : null;


                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById('moving_product_stock_form_preOffer');

                if(offerChange)
                {

                    offerChange.addEventListener('change', function(event)
                    {
                        changeObjectOffer(forms);
                        return false;
                    });
                }


                // return;
                //
                //
                // /** Изменияем список целевых складов */
                // let warehouse = result.getElementById('targetWarehouse');
                //
                //
                // document.getElementById('targetWarehouse').replaceWith(warehouse);
                // document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
                //
                // new NiceSelect(document.getElementById('moving_product_stock_form_targetWarehouse'), {
                //     searchable: true,
                //     id: 'select2-' + replaceId
                // });

            }
        });
}


function _changeObjectProduct()
{

    let replaceId = 'moving_product_stock_form_preOffer';


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let MovingForm = document.forms.moving_product_stock_form;
    let formData = new FormData();


    // const varehouse = document.getElementById('moving_product_stock_form_targetWarehouse');
    // formData.append(varehouse.getAttribute('name'), varehouse.value);

    formData.append(this.getAttribute('name'), this.value);


    requestModalName.open(MovingForm.getAttribute('method'), MovingForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {

            let result = requestModalName.response.getElementById('preOffer');


            document.getElementById('preOffer').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(replacer, {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById('moving_product_stock_form_preOffer');

                if(offerChange)
                {
                    offerChange.addEventListener('change', changeObjectOffer, false);
                }
            }


            /** Изменияем список целевых складов */
            let warehouse = requestModalName.response.getElementById('targetWarehouse');
            document.getElementById('targetWarehouse').replaceWith(warehouse);
            document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
            new NiceSelect(document.getElementById('moving_product_stock_form_targetWarehouse'), {
                searchable: true,
                id: 'select2-' + replaceId
            });


        }

        return false;
    });

    requestModalName.send(formData);
}


async function changeObjectOffer(forms)
{


    const data = new FormData(forms);
    data.delete(forms.name + '[_token]');


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: data // body data type must match "Content-Type" header
    })

        //.then((response) => response)
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

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preVariation = result.getElementById('preVariation');

                if(preVariation)
                {

                    document.getElementById('preVariation').replaceWith(preVariation);

                    /** SELECT2 */

                    let replacer = document.getElementById('moving_product_stock_form_preVariation');

                    if(replacer)
                    {

                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});
                        }

                        replacer.addEventListener('change', function(event)
                        {
                            changeObjectVariation(forms);
                            return false;
                        });

                        let focus = document.getElementById('moving_product_stock_form_preVariation_select2');
                        focus ? focus.click() : null;

                    }

                } else
                {

                    let targetWarehouse = result.getElementById('targetWarehouse');

                    if(targetWarehouse)
                    {

                        document.getElementById('targetWarehouse').replaceWith(targetWarehouse);

                        /** SELECT2 */
                        let replacerWarehouse = document.getElementById('moving_product_stock_form_targetWarehouse');
                        replacer.addEventListener('change', changeObjectWarehause, false);

                        if(replacerWarehouse && replacerWarehouse.tagName === 'SELECT')
                        {
                            new NiceSelect(replacerWarehouse, {searchable: true});
                        }
                    }
                }


                let preModification = document.getElementById('preModification');
                preModification ? preModification.innerHTML = '' : null;


            }
        });
}


function _changeObjectOffer()
{


    let replaceId = 'moving_product_stock_form_preVariation';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let MovingForm = document.forms.moving_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    /** Продукт */
    const product = document.getElementById('moving_product_stock_form_preProduct');
    formData.append(product.getAttribute('name'), product.value);

    /** Торговое предложенеи */
    requestModalName.open(MovingForm.getAttribute('method'), MovingForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {

            let result = requestModalName.response.getElementById('preVariation');

            document.getElementById('preVariation').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение множественного варианта предложения */
                let offerVariation = document.getElementById('moving_product_stock_form_preVariation');

                if(offerVariation)
                {
                    offerVariation.addEventListener('change', changeObjectVariation, false);
                }
            }


            /** Изменияем список целевых складов */
            let warehouse = requestModalName.response.getElementById('targetWarehouse');
            document.getElementById('targetWarehouse').replaceWith(warehouse);
            document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
            new NiceSelect(document.getElementById('moving_product_stock_form_targetWarehouse'), {
                searchable: true,
                id: 'select2-' + replaceId
            });


        }

        return false;
    });

    requestModalName.send(formData);
}


async function changeObjectVariation(forms)
{


    const data = new FormData(forms);
    data.delete(forms.name + '[_token]');


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: data // body data type must match "Content-Type" header
    })

        //.then((response) => response)
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

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preModification = result.getElementById('preModification');

                if(preModification)
                {

                    document.getElementById('preModification').replaceWith(preModification);

                    /** SELECT2 */
                    let replacer = document.getElementById('moving_product_stock_form_preModification');

                    /** Событие на изменение модификации */
                    if(replacer)
                    {

                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});
                        }

                        replacer.addEventListener('change', function(event)
                        {
                            changeObjectModification(forms);
                            return false;
                        });

                        let focus = document.getElementById('moving_product_stock_form_preModification_select2');
                        focus ? focus.click() : null;
                    }


                } else
                {

                    let targetWarehouse = result.getElementById('targetWarehouse');

                    if(targetWarehouse)
                    {

                        document.getElementById('targetWarehouse').replaceWith(targetWarehouse);

                        /** SELECT2 */
                        let replacerWarehouse = document.getElementById('moving_product_stock_form_targetWarehouse');
                        replacer.addEventListener('change', changeObjectWarehause, false);

                        if(replacerWarehouse && replacerWarehouse.tagName === 'SELECT')
                        {
                            new NiceSelect(replacerWarehouse, {searchable: true});
                        }

                        // let focus = document.getElementById('moving_product_stock_form_targetWarehouse_select2');
                        // focus ? focus.click() : null;

                    }
                }


                // /** Событие на изменение множественного варианта */
                // let change = document.getElementById('moving_product_stock_form_preVariation');
                //
                // if (change) {
                //
                //     change.addEventListener('change', function (event) {
                //         changeObjectVariation(forms);
                //         return false;
                //     });
                // }


                //  return;


                // /** Изменияем список целевых складов */
                // let warehouse = result.getElementById('targetWarehouse');
                //
                //
                // document.getElementById('targetWarehouse').replaceWith(warehouse);
                // document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
                //
                // new NiceSelect(document.getElementById('moving_product_stock_form_targetWarehouse'), {
                //     searchable: true,
                //     id: 'select2-' + replaceId
                // });

            }
        });
}


function _changeObjectVariation()
{

    let replaceId = 'moving_product_stock_form_preModification';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let MovingForm = document.forms.moving_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    /** Продукт */
    const product = document.getElementById('moving_product_stock_form_preProduct');
    formData.append(product.getAttribute('name'), product.value);

    /** Торговое предложенеи */
    const offer = document.getElementById('moving_product_stock_form_preOffer');
    formData.append(offer.getAttribute('name'), offer.value);

    /** Множественный вариант */
    requestModalName.open(MovingForm.getAttribute('method'), MovingForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {

            let result = requestModalName.response.getElementById('preModification');

            document.getElementById('preModification').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение модификации множественного варианта предложения */
                let offerModification = document.getElementById('moving_product_stock_form_preModification');

                if(offerModification)
                {
                    offerModification.addEventListener('change', changeObjectModification, false);
                }
            }


            /** Изменияем список целевых складов */
            let warehouse = requestModalName.response.getElementById('targetWarehouse');
            document.getElementById('targetWarehouse').replaceWith(warehouse);
            document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
            new NiceSelect(document.getElementById('moving_product_stock_form_targetWarehouse'), {
                searchable: true,
                id: 'select2-' + replaceId
            });


        }

        return false;
    });

    requestModalName.send(formData);

}


async function changeObjectModification(forms)
{


    const data = new FormData(forms);
    data.delete(forms.name + '[_token]');


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: data // body data type must match "Content-Type" header
    })

        //.then((response) => response)
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

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');

                let targetWarehouse = result.getElementById('targetWarehouse');

                targetWarehouse.querySelector('#moving_product_stock_form_targetWarehouse').classList.remove('is-invalid');
                (targetWarehouse.querySelector('.invalid-feedback'))?.remove();

                if(targetWarehouse)
                {

                    document.getElementById('targetWarehouse').replaceWith(targetWarehouse);

                    /** SELECT2 */
                    let replacer = document.getElementById('moving_product_stock_form_targetWarehouse');
                    replacer.addEventListener('change', changeObjectWarehause, false);

                    if(replacer && replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});
                    }

                    let focus = document.getElementById('moving_product_stock_form_targetWarehouse_select2');
                    focus ? focus.click() : null;

                }


            }
        });
}


function _changeObjectModification()
{

    let replaceId = 'moving_product_stock_form_targetWarehouse';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let MovingForm = document.forms.moving_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    /** Продукт */
    const product = document.getElementById('moving_product_stock_form_preProduct');
    formData.append(product.getAttribute('name'), product.value);

    /** Торговое предложенеи */
    const offer = document.getElementById('moving_product_stock_form_preOffer');
    formData.append(offer.getAttribute('name'), offer.value);

    /** Множественный вариант */
    const variation = document.getElementById('moving_product_stock_form_preVariation');
    formData.append(variation.getAttribute('name'), variation.value);

    /** Модификация */
    requestModalName.open(MovingForm.getAttribute('method'), MovingForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {

            let result = requestModalName.response.getElementById('targetWarehouse');

            document.getElementById('targetWarehouse').replaceWith(result);

            let replacer = document.getElementById(replaceId);


            /** Изменить максимально допустимое количество */

            document.getElementById('moving_product_stock_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});
            }


        }

        return false;
    });

    requestModalName.send(formData);
}


function changeObjectWarehause()
{
    let index = this.selectedIndex;
    document.getElementById('moving_product_stock_form_preTotal')
        .setAttribute('max', this.options[index].dataset.max);

    document.getElementById('moving_product_stock_form_destinationWarehouse').addEventListener('change', () =>
    {

        setTimeout(function()
        {
            let focusTotal = document.getElementById('moving_product_stock_form_preTotal');
            focusTotal ? focusTotal.focus() : null;
        }, 100);

    }, false);


    setTimeout(function()
    {
        let focusDestination = document.getElementById('moving_product_stock_form_destinationWarehouse_select2');
        focusDestination ? focusDestination.click() : null;
    }, 100);
}


var collectionStock = new Map();

function addProductMoving()
{

    /* Блок для новой коллекции КАТЕГОРИИ */
    let $blockCollectionStock = document.getElementById('collectionStock');

    /* Добавляем новую коллекцию */
    //$addButtonStock.addEventListener('click', function () {

    let $errorFormHandler = null;

    let header = 'Добавить лист перемещения продукции';


    let $preTotal = document.getElementById('moving_product_stock_form_preTotal');
    let $TOTAL = $preTotal.value * 1;

    let $totalMax = $preTotal.getAttribute('max');

    if($TOTAL === undefined || $TOTAL < 1 || $TOTAL > $totalMax)
    {

        if($TOTAL === undefined)
        {
            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "Ошибка при заполнение количество" }';
        }

        if($TOTAL > $totalMax)
        {
            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "Недостаточное количество на складе" }';
        }

        if($TOTAL < 1)
        {
            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "Не указано количество для перемещения" }';
        }


    }


    let $targetWarehouse = document.getElementById('moving_product_stock_form_targetWarehouse');
    if($targetWarehouse.value.length === 0)
    {

        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"Добавить лист закупки продукции"  , ' +
            '"message" : "' + $targetWarehouse.options[0].textContent + '" }';

    }

    let $destinationWarehouse = document.getElementById('moving_product_stock_form_destinationWarehouse');
    if($destinationWarehouse.value.length === 0)
    {

        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"Добавить лист закупки продукции"  , ' +
            '"message" : "' + $destinationWarehouse.options[0].textContent + '" }';

    }


    let $preProduct = document.getElementById('moving_product_stock_form_preProduct');
    if($preProduct.value.length === 0)
    {

        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"' + header + '"  , ' +
            '"message" : "' + $preProduct.options[0].textContent + '" }';

    }


    let $preOffer = document.getElementById('moving_product_stock_form_preOffer');
    if($preOffer)
    {
        if($preOffer.tagName === 'SELECT' && $preOffer.value.length === 0)
        {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "' + $preOffer.options[0].textContent + '" }';
        }
    }


    let $preVariation = document.getElementById('moving_product_stock_form_preVariation');
    if($preVariation)
    {
        if($preVariation.tagName === 'SELECT' && $preVariation.value.length === 0)
        {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "' + $preVariation.options[0].textContent + '" }';
        }
    }

    let $preModification = document.getElementById('moving_product_stock_form_preModification');
    if($preModification)
    {
        if($preModification.tagName === 'SELECT' && $preModification.value.length === 0)
        {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"' + header + '"  , ' +
                '"message" : "' + $preModification.options[0].textContent + '" }';
        }
    }


    if($targetWarehouse.value === $destinationWarehouse.value)
    {
        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"' + header + '"  , ' +
            '"message" : "Внутренее перемещение! Выберите другой склад назначения либо отгрузки" }';
    }

    if(collectionStock.has($targetWarehouse.value + $destinationWarehouse.value + $preProduct.value + $preOffer.value + $preVariation.value + $preModification.value))
    {
        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"' + header + '"  , ' +
            '"message" : "Продукция уже добавлена в список" }';
    }


    if(collectionStock.size >= 5)
    {
        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"' + header + '"  , ' +
            '"message" : "Количество в заявке временно ограничено до 5 позиций! Сохраните активную и добавьте новую." }';
    }

    /* Выводим сообщение об ошибке заполнения */

    if($errorFormHandler)
    {
        createToast(JSON.parse($errorFormHandler));
        return false;
    }

    /* получаем прототип коллекции  */
    let $addButtonStock = this;

    let newForm = $addButtonStock.dataset.prototype;
    let index = $addButtonStock.dataset.index * 1;

    /* Замена '__name__' в HTML-коде прототипа
     вместо этого будет число, основанное на том, сколько коллекций */
    newForm = newForm.replace(/__product__/g, index);
    //newForm = newForm.replace(/__FIELD__/g, index);


    /* Вставляем новую коллекцию */
    let stockDiv = document.createElement('div');

    stockDiv.classList.add('item-collection-product');
    stockDiv.classList.add('w-100');
    stockDiv.innerHTML = newForm;
    $blockCollectionStock.append(stockDiv);


    let productIndex = $preProduct.selectedIndex;
    let $productName = $preProduct.options[productIndex].dataset.name;


    let targetWarehouseIndex = $targetWarehouse.selectedIndex;
    let $targetWarehouseName = $targetWarehouse.options[targetWarehouseIndex].dataset.name;

    let destinationWarehouseIndex = $destinationWarehouse.selectedIndex;
    let $destinationWarehouseName = $destinationWarehouse.options[destinationWarehouseIndex].textContent;

    let variationIndex = $preVariation.selectedIndex;
    let $variationName = $preVariation.tagName === 'SELECT' ? document.querySelector('label[for="' + $preVariation.id + '"]').textContent + ' ' + $preVariation.options[variationIndex].dataset.name : '';

    let modificationIndex = $preModification.selectedIndex;
    let $modificationName = $preModification.tagName === 'SELECT' ? document.querySelector('label[for="' + $preModification.id + '"]').textContent + ' ' + $preModification.options[modificationIndex].dataset.name : '';

    let offerIndex = $preOffer.selectedIndex;
    let $offerName = $preOffer.tagName === 'SELECT' ? document.querySelector('label[for="' + $preOffer.id + '"]').textContent + ' ' + $preOffer.options[offerIndex].dataset.name : '';


    let $productTextBlock = stockDiv.querySelector('#product-text-' + index);
    $productTextBlock.innerHTML = $targetWarehouseName + '&nbsp; => &nbsp;' + $destinationWarehouseName + ' &nbsp; : &nbsp; ' + $productName + ' ' + $offerName + ' ' + $variationName + ' ' + $modificationName + '&nbsp; : &nbsp;' + $TOTAL + ' шт.';


    /** Заполняем значения скрытых элементо */

    let $warehouse = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_move_warehouse');

    let $destination = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_move_destination');

    let $product = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_product_' + index + '_product');

    let $offer = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_product_' + index + '_offer');

    let $variation = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_product_' + index + '_variation');

    let $modification = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_product_' + index + '_modification');

    let $total = stockDiv.querySelector('#moving_product_stock_form_move_' + index + '_product_' + index + '_total')


    $warehouse.value = $targetWarehouse.value;
    $destination.value = $destinationWarehouse.value;
    $product.value = $preProduct.value;
    $offer.value = $preOffer.value;
    $variation.value = $preVariation.value;
    $modification.value = $preModification.value;
    $total.value = $preTotal.value;

    /* Удаляем при клике колекцию СЕКЦИЙ */
    stockDiv.querySelector('.del-item-product').addEventListener('click', function()
    {
        this.closest('.item-collection-product').remove();
        index = $addButtonStock.dataset.index * 1;
        $addButtonStock.dataset.index = (index - 1).toString();
    });

    /* Увеличиваем data-index на 1 после вставки новой коллекции */
    $addButtonStock.dataset.index = (index + 1).toString();

    /* Обнуляем количество в перформе */
    $preTotal.value = null;

    collectionStock.set($targetWarehouse.value + $destinationWarehouse.value + $preProduct.value + $preOffer.value + $preVariation.value + $preModification.value);

    document.getElementById('moving_product_stock_form_preOffer_select2').remove();
    //document.getElementById('moving_product_stock_form_preOffer').remove();

    document.getElementById('moving_product_stock_form_preVariation_select2').remove();
    //document.getElementById('moving_product_stock_form_preVariation').remove();

    document.getElementById('moving_product_stock_form_preModification_select2').remove();
    //document.getElementById('moving_product_stock_form_preModification').remove();


    setTimeout(() =>
    {
        document.getElementById('moving_product_stock_form_preProduct_select2').click();
    }, 100);


    /* применяем select2 */
    //new NiceSelect(div.querySelector('[data-select="select2"]'), {searchable: true});
    //});
}



