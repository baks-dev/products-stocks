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



executeFunc(function productStocsPurchase()
{
    /* Имя формы */
    ChangePurchaseForm = document.forms.purchase_product_stock_form;

    if(typeof ChangePurchaseForm === 'undefined')
    {
        return false;
    }

    var object_category = document.getElementById(ChangePurchaseForm.name + '_category');

    if(object_category === null)
    {
        return false;
    }

    object_category.addEventListener('change', function()
    {
        changeObjectCategory(ChangePurchaseForm);
    }, false);


    /**
     * Если форма новая - фокусируем на номере
     * при клике Enter  - выделяем категорию
     */
    let numberElement = document.getElementById(ChangePurchaseForm.name + '_invariable_number');
    numberElement.focus();

    numberElement.addEventListener("keydown", function(event)
    {
        if(event.key === "Enter")
        {
            event.preventDefault();

            var categoryElementSelect2 = document.getElementById(ChangePurchaseForm.name + '_category_select2');

            if(categoryElementSelect2)
            {
                categoryElementSelect2.click();
            } else
            {
                var categoryElementSelect = document.getElementById(ChangePurchaseForm.name + '_category');
                categoryElementSelect2.click();
            }
        }
    });

    let $addButtonStock = document.getElementById(ChangePurchaseForm.name + '_addPurchase');
    $addButtonStock.addEventListener('click', addProductPurchase, false);

    document.getElementById(ChangePurchaseForm.name + '_purchase')
        .addEventListener('click', function(event)
        {
            /** Поверяем, что в коллекции имеются элементы продукции */
            if(ChangePurchaseForm.querySelectorAll('.item-collection-product')?.length <= 0)
            {
                let $errorFormHandlerProducts = JSON.stringify({
                    type: 'danger',
                    header: 'Добавить лист закупки продукции',
                    message: 'В списке нет ни одного элемента продукции'
                });

                /* Выводим сообщение об ошибке заполнения */
                createToast(JSON.parse($errorFormHandlerProducts));

                return false;
            }

            let elementTotal = document.getElementById(ChangePurchaseForm.name + '_preTotal');

            /** Поверяем, что в "Количество" не указано число (пользователь не кликнул "Добавить в закупку")  */
            if(elementTotal)
            {
                if(elementTotal.value.trim() !== "")
                {
                    let $errorFormHandlerProducts = JSON.stringify({
                        type: 'danger',
                        header: 'Добавить лист закупки продукции',
                        message: 'Вы не добавили продукцию в общий список кликнув "+ Добавить в закупку"'
                    });

                    /* Выводим сообщение об ошибке заполнения */
                    createToast(JSON.parse($errorFormHandlerProducts));

                    return false;
                }
            }

            if(event.key !== "Enter")
            {
                submitModalForm(ChangePurchaseForm);
            }

            return false;
        });


    return true;
});


executeFunc(function _______________productStocsPurchase()
{


    return true;


    var object_product = document.getElementById('purchase_product_stock_form_preProduct');

    if(!object_product)
    {
        return false;
    }


    object_product.addEventListener('change', changeObjectProduct, false);

    let $addButtonStock = document.getElementById('purchase_product_stock_form_addPurchase');

    $addButtonStock.addEventListener('click', addProductPurchase, false);

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;


    let forms = object_product.closest('form');


    /* событие отправки формы */
    // forms.addEventListener('submit', function (event) {
    //     event.preventDefault();
    //     return false;
    // });

    document.getElementById('purchase_product_stock_form_purchase')
        .addEventListener('click', function(event)
        {
            if(event.key !== "Enter")
            {
                submitModalForm(forms);
            }
            return false;
        });

    document.getElementById("purchase_product_stock_form_preTotal")
        .addEventListener("keydown", function(event)
        {
            if(event.key === "Enter")
            {
                addProductPurchase();
            }
        });


    document.getElementById("purchase_product_stock_form_invariable_number")
        .addEventListener("keydown", function(event)
        {
            if(event.key === "Enter")
            {
                event.preventDefault();

                var input_preProduct = document.getElementById('purchase_product_stock_form_select2');

                if(input_preProduct)
                {
                    document.getElementById('purchase_product_stock_form_select2').click();

                }
            }
        });

    return true;
})


async function changeObjectCategory(forms)
{
    disabledElementsForm(forms);

    document.getElementById('preProduct')?.classList.add('d-none');
    document.getElementById('preOffer')?.classList.add('d-none');
    document.getElementById('preVariation')?.classList.add('d-none');
    document.getElementById('preModification')?.classList.add('d-none');
    document.getElementById('purchase_product_stock_form_addPurchase')?.classList.replace('btn-outline-primary', 'btn-outline-secondary');


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

                let preProduct = result.getElementById('preProduct');


                /** Сбрасываем ошибки валидации */
                if(preProduct)
                {
                    preProduct.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                    preProduct.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });
                }

                document.getElementById('preProduct').replaceWith(preProduct);

                preProduct ?
                    document
                        ?.getElementById('product')
                        ?.replaceWith(preProduct) :
                    preProduct.innerHTML = '';

                /** SELECT2 */
                let replacer = document.getElementById(forms.name + '_preProduct');
                replacer && replacer.type !== 'hidden' ? preProduct.classList.remove('d-none') : null;

                /** Событие на изменение модификации */
                if(replacer)
                {

                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_preProduct_select2');
                        focus ? focus.click() : null;
                    }
                }

                /** сбрасываем зависимые поля */
                let preOffer = document.getElementById('preOffer');
                preOffer ? preOffer.innerHTML = '' : null;

                /** сбрасываем зависимые поля */
                let preVariation = document.getElementById('preVariation');
                preVariation ? preVariation.innerHTML = '' : null;

                let preModification = document.getElementById('preModification');
                preModification ? preModification.innerHTML = '' : null;


                if(replacer)
                {

                    replacer.addEventListener('change', function(event)
                    {
                        changeObjectProduct(forms);
                        return false;
                    });
                }
            }

            enableElementsForm(forms);
        });
}


async function changeObjectProduct(forms)
{
    disabledElementsForm(forms);

    document.getElementById('preOffer')?.classList.add('d-none');
    document.getElementById('preVariation')?.classList.add('d-none');
    document.getElementById('preModification')?.classList.add('d-none');
    document.getElementById('purchase_product_stock_form_addPurchase')?.classList.replace('btn-outline-primary', 'btn-outline-secondary');

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
                    /** Сбрасываем ошибки валидации */
                    preOffer.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                    preOffer.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });

                    /** SELECT2 */

                    let replaceOfferId = forms.name + '_preOffer';

                    let replacer = document.getElementById(replaceOfferId);
                    replacer && replacer.type !== 'hidden' ? preOffer.classList.remove('d-none') : null;

                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_preOffer_select2');
                        focus ? focus.click() : null;

                    } else
                    {
                        // Выделяем элемент Количество
                        selectTotal(forms);
                    }

                }

                /** сбрасываем зависимые поля */
                let preVariation = document.getElementById('preVariation');
                preVariation ? preVariation.innerHTML = '' : null;

                let preModification = document.getElementById('preModification');
                preModification ? preModification.innerHTML = '' : null;


                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById(forms.name + '_preOffer');

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
                // document.getElementById('new_order_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
                //
                // new NiceSelect(document.getElementById('new_order_form_targetWarehouse'), {
                //     searchable: true,
                //     id: 'select2-' + replaceId
                // });

            }

            enableElementsForm(forms);
        });
}


async function changeObjectOffer(forms)
{
    disabledElementsForm(forms);

    document.getElementById('preVariation')?.classList.add('d-none');
    document.getElementById('preModification')?.classList.add('d-none');
    document.getElementById('purchase_product_stock_form_addPurchase')?.classList.replace('btn-outline-primary', 'btn-outline-secondary');

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

                    preVariation.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                    preVariation.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });


                    document.getElementById('preVariation').replaceWith(preVariation);

                    /** SELECT2 */

                    let replacer = document.getElementById(forms.name + '_preVariation');
                    replacer && replacer.type !== 'hidden' ? preVariation.classList.remove('d-none') : null;

                    if(replacer)
                    {

                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_preVariation_select2');
                            focus ? focus.click() : null;

                            replacer.addEventListener('change', function(event)
                            {
                                changeObjectVariation(forms);
                                return false;
                            });
                        } else
                        {
                            // Выделяем элемент Количество
                            selectTotal(forms);
                        }

                    }

                }


                let preModification = document.getElementById('preModification');
                preModification ? preModification.innerHTML = '' : null;


            }

            enableElementsForm(forms);
        });
}


async function changeObjectVariation(forms)
{

    disabledElementsForm(forms);

    document.getElementById('preModification')?.classList.add('d-none');
    document.getElementById('purchase_product_stock_form_addPurchase')?.classList.replace('btn-outline-primary', 'btn-outline-secondary');

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
                    preModification.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                    preModification.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });


                    document.getElementById('preModification').replaceWith(preModification);

                    /** SELECT2 */
                    let replacer = document.getElementById(forms.name + '_preModification');
                    replacer && replacer.type !== 'hidden' ? preModification.classList.remove('d-none') : null;

                    /** Событие на изменение модификации */
                    if(replacer)
                    {
                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_preModification_select2');
                            focus ? focus.click() : null;

                            replacer.addEventListener('change', function(event)
                            {
                                selectTotal(forms)
                                return false;
                            });

                        } else
                        {
                            // Выделяем элемент Количество
                            selectTotal(forms);
                        }
                    }
                }
            }

            enableElementsForm(forms);
        });
}

function selectTotal(forms)
{
    setTimeout(function()
    {
        let focusTotal = document.getElementById(ChangePurchaseForm.name + '_preTotal');

        document.getElementById(ChangePurchaseForm.name + '_addPurchase')?.classList.replace('btn-outline-secondary', 'btn-outline-primary');

        focusTotal.value = '';
        focusTotal ? focusTotal.focus() : null;

        focusTotal.addEventListener("keydown", enterTotalElement);

    }, 100);
}

/** При клике Enter добавляем элемент продукции в коллекцию */
function enterTotalElement(event)
{
    if(event.key === "Enter")
    {
        event.preventDefault();  // Отменяем стандартное действие
        addProductPurchase();     // Вызываем вашу функцию

        // Удаляем обработчик события
        document.removeEventListener('keydown', enterTotalElement);
    }
}


//modal.addEventListener('shown.bs.modal', function () {

/* Change PRODUCT */
//object_product = document.getElementById('purchase_product_stock_form_preProduct');


// if (object_product) {
//     object_product.addEventListener('change', changeObjectProduct, false);
//
//
//     let $addButtonStock = document.getElementById('purchase_product_stock_form_addPurchase');
//
//     if ($addButtonStock) {
//         $addButtonStock.addEventListener('click', addProductPurchase, false);
//     }
//
//
// } else {
//     eventEmitter.addEventListener('complete', function ()
//     {
//         let object_product = document.getElementById('purchase_product_stock_form_preProduct');
//
//         if (object_product) {
//             object_product.addEventListener('change', changeObjectProduct, false);
//
//
//             let $addButtonStock = document.getElementById('purchase_product_stock_form_addPurchase');
//
//             if ($addButtonStock) {
//                 $addButtonStock.addEventListener('click', addProductPurchase, false);
//             }
//         }
//     });
// }

//});


function _____changeObjectProduct()
{


    let replaceId = 'purchase_product_stock_form_preOffer';


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    disabledElementsForm(purchaseForm);

    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

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
                let offerChange = document.getElementById('purchase_product_stock_form_preOffer');

                if(offerChange)
                {
                    offerChange.addEventListener('change', changeObjectOffer, false);

                    let focus = document.getElementById(replaceId + '_select2');
                    focus ? focus.click() : null;

                }
            }


        }

        enableElementsForm(purchaseForm);

        return false;
    });

    requestModalName.send(formData);
}


function _____changeObjectOffer()
{


    let replaceId = 'purchase_product_stock_form_preVariation';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    disabledElementsForm(purchaseForm);

    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

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

            /* Удаляем предыдущий Select2 */
            let select2 = document.getElementById(replaceId + '_select2');

            if(select2)
            {
                select2.remove();
            }

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение множественного варианта предложения */
                let offerVariation = document.getElementById('purchase_product_stock_form_preVariation');

                if(offerVariation)
                {
                    offerVariation.addEventListener('change', changeObjectVariation, false);
                }

                let focus = document.getElementById(replaceId + '_select2');
                focus ? focus.click() : null;
            }

        }

        enableElementsForm(purchaseForm);

        return false;
    });

    requestModalName.send(formData);
}


function _____changeObjectVariation()
{

    let replaceId = 'purchase_product_stock_form_preModification';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    disabledElementsForm(purchaseForm);
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

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
            }

            let focus = document.getElementById(replaceId + '_select2');
            focus ? focus.click() : null;


            /** Событие на изменение множественного варианта предложения */
            let offerModification = document.getElementById('purchase_product_stock_form_preModification');

            if(offerModification)
            {

                offerModification.addEventListener("change", (event) =>
                {
                    setTimeout(function initBootstrap()
                    {
                        document.getElementById('purchase_product_stock_form_preTotal').focus();
                    }, 100);

                });
            }

        }

        enableElementsForm(purchaseForm);

        return false;
    });

    requestModalName.send(formData);

}

var collectionProductPurshase = new Map();

function addProductPurchase()
{

    const dynamicElements = ['preProduct', 'preOffer', 'preVariation', 'preModification'];

    /* Блок для новой коллекции КАТЕГОРИИ */
    let $blockCollectionStock = document.getElementById('collectionStock');

    /* Добавляем новую коллекцию */
    //$addButtonStock.addEventListener('click', function () {


    let $errorFormHandler = null;
    let header = 'Добавить лист закупки продукции';


    let $preTotal = document.getElementById(ChangePurchaseForm.name + '_preTotal');


    /** Проверяем на заполнение количество */
    let $TOTAL = $preTotal.value * 1;

    if($TOTAL === undefined || $TOTAL < 1)
    {
        $errorFormHandler = JSON.stringify({
            type: 'danger',
            header: header,
            message: "Не заполнено количество"
        });
    }

    /** Проверяем что заполнен номер квитанции */
    let $number = document.getElementById(ChangePurchaseForm.name + '_invariable_number');

    if($number.value.length === 0)
    {
        $errorFormHandler = JSON.stringify({
            type: 'danger',
            header: header,
            message: "Не заполнен номер закупки"
        });
    }

    let $preCategory = document.getElementById(ChangePurchaseForm.name + '_category');
    let $preProduct = document.getElementById(ChangePurchaseForm.name + '_preProduct');
    let $preOffer = document.getElementById(ChangePurchaseForm.name + '_preOffer');
    let $preVariation = document.getElementById(ChangePurchaseForm.name + '_preVariation');
    let $preModification = document.getElementById(ChangePurchaseForm.name + '_preModification');

    /** Делаем проверку на выбор продукции */
    if($preCategory.value.length === 0)
    {
        $errorFormHandler = JSON.stringify({
            type: 'danger',
            header: header,
            message: $preCategory.options[0].textContent
        });
    }

    /** Делаем проверку на выбор динамических элементов */
    dynamicElements.forEach(id =>
    {
        const element = document.getElementById(ChangePurchaseForm.name + '_' + id);
        if(element && element.tagName === 'SELECT' && element.value.length === 0)
        {
            const message = element.options[0].textContent;
            $errorFormHandler = JSON.stringify({
                type: 'danger',
                header: header,
                message: message
            });
        }
    });


    /** Добавляем продукцию в карту уникальных элементов */
    const mapKey = [$preProduct, $preOffer, $preVariation, $preModification].map(item => item ? item.value : '').join('');

    if(collectionProductPurshase.has(mapKey))
    {
        $errorFormHandler = JSON.stringify({
            type: 'danger',
            header: header,
            message: 'Продукция уже добавлена в список'
        });
    }

    /* Выводим сообщение об ошибке заполнения */

    if($errorFormHandler)
    {
        createToast(JSON.parse($errorFormHandler));
        return false;
    }


    /** Добавляем продукцию в коллекцию */


    /* получаем прототип коллекции  */
    let $addButtonStock = document.getElementById(ChangePurchaseForm.name + '_addPurchase');

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


    let $total = stockDiv.querySelector('#' + ChangePurchaseForm.name + '_product_' + index + '_total')
    $total.value = $preTotal.value;


    /** Присваиваем элемент продукции */
    let $product = stockDiv.querySelector('#' + ChangePurchaseForm.name + '_product_' + index + '_product');
    $product.value = $preProduct.value;

    let productIndex = $preProduct.selectedIndex;
    let $productName = $preProduct.options[productIndex].textContent;


    /** Присваиваем элемент торгового предложения */
    let $offerName = '';

    if($preOffer)
    {
        let $offer = stockDiv.querySelector('#' + ChangePurchaseForm.name + '_product_' + index + '_offer');
        $offer.value = $preOffer?.value;

        let offerIndex = $preOffer.selectedIndex;
        $offerName = $preOffer.tagName === 'SELECT' ? '&nbsp; <small class="text-muted fw-normal">' + document.querySelector('label[for="' + $preOffer.id + '"]').textContent + '</small> ' + $preOffer.options[offerIndex].textContent : '';
    }

    /** Присваиваем элемент множественного варианта торгового предложения */
    let $variationName = '';

    if($preVariation)
    {
        let $variation = stockDiv.querySelector('#' + ChangePurchaseForm.name + '_product_' + index + '_variation');
        $variation.value = $preVariation.value;

        let variationIndex = $preVariation.selectedIndex;
        $variationName = $preVariation.tagName === 'SELECT' ? '&nbsp; <small class="text-muted fw-normal">' + document.querySelector('label[for="' + $preVariation.id + '"]').textContent + '</small> ' + $preVariation.options[variationIndex].textContent : '';
    }

    /** Присваиваем элемент модификации множественного варианта торгового предложения */
    let $modificationName = '';

    if($preModification)
    {
        let $modification = stockDiv.querySelector('#' + ChangePurchaseForm.name + '_product_' + index + '_modification');
        $modification.value = $preModification.value;

        let modificationIndex = $preModification.selectedIndex;
        $modificationName = $preModification.tagName === 'SELECT' ? '&nbsp; <small class="text-muted fw-normal">' + document.querySelector('label[for="' + $preModification.id + '"]').textContent + '</small> ' + $preModification.options[modificationIndex].textContent : '';

    }


    /** Конкатенируем полученные значения для вставки */
    let $productTextBlock = stockDiv.querySelector('#product-text-' + index);
    $productTextBlock.innerHTML = $productName + $offerName + $variationName + $modificationName + '&nbsp; : &nbsp;' + $total.value + ' шт.';


    /** Сбрасываем количество после добавления */
    $preTotal.value = null;


    /* Удаляем при клике элемент коллекции */
    stockDiv.querySelector('.del-item-product').addEventListener('click', function()
    {
        this.closest('.item-collection-product').remove();
        index = $addButtonStock.dataset.index * 1;
        $addButtonStock.dataset.index = (index - 1).toString();

        collectionProductPurshase.delete(mapKey);
    });

    /* Увеличиваем data-index на 1 после вставки новой коллекции */
    $addButtonStock.dataset.index = (index + 1).toString();


    /* После применения сбрасываем ТП и выделяем продукцию */
    dynamicElements.forEach(id =>
    {
        if(id === 'preProduct')
        {
            return;
        }

        const element = document.getElementById(id);

        if(element)
        {
            element.innerHTML = '';
            element.classList.add('d-none');
        }
    });

    /** Добавляем элемент в карту уникальности */
    collectionProductPurshase.set(mapKey, $total.value);

    var limit_faaJUfW = 1000;

    setTimeout(function init_TQCmNQtx()
    {

        var input_preProduct_select = document.getElementById(ChangePurchaseForm.name + '_preProduct_select2');

        if(input_preProduct_select)
        {
            input_preProduct_select.click();
            return;
        }

        var input_preProduct = document.getElementById(ChangePurchaseForm.name + '_preProduct');

        if(input_preProduct)
        {
            document.getElementById(ChangePurchaseForm.name + '_preProduct_select2');
            return;
        }

        if(limit_faaJUfW > 1000)
        {
            return;
        }

        limit_faaJUfW = limit_faaJUfW * 2;

        setTimeout(init_TQCmNQtx, limit_faaJUfW);

    }, 100);
}

//function enterOnTotal()
//{
//    document.getElementById(ChangePurchaseForm.name + '_preTotal')
//        .addEventListener("keydown", function(event)
//        {
//            if(event.key === "Enter")
//            {
//                addProductPurchase();
//            }
//        });
//}