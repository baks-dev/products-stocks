/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */


setTimeout(function initPurchase() {

    object_product = document.getElementById('purchase_product_stock_form_preProduct');

    if (object_product) {

        object_product.addEventListener('change', changeObjectProduct, false);


        let $addButtonStock = document.getElementById('purchase_product_stock_form_addPurchase');

        //if ($addButtonStock) {
            $addButtonStock.addEventListener('click', addProductPurchase, false);
       // }

        return;
    }

    setTimeout(initPurchase, 100);

}, 100);



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


function changeObjectProduct() {

    let replaceId = 'purchase_product_stock_form_preOffer';


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {

            let result = requestModalName.response.getElementById('preOffer');


            document.getElementById('preOffer').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(replacer, {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById('purchase_product_stock_form_preOffer');

                if (offerChange) {
                    offerChange.addEventListener('change', changeObjectOffer, false);
                }
            }


        }

        return false;
    });

    requestModalName.send(formData);
}


function changeObjectOffer() {


    let replaceId = 'purchase_product_stock_form_preVariation';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {



            let result = requestModalName.response.getElementById('preVariation');

            document.getElementById('preVariation').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            /* Удаляем предыдущий Select2 */
            let select2 = document.getElementById(replaceId + '_select2');

            if (select2) {
                select2.remove();
            }

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение множественного варианта предложения */
                let offerVariation = document.getElementById('purchase_product_stock_form_preVariation');

                if (offerVariation) {
                    offerVariation.addEventListener('change', changeObjectVariation, false);
                }
            }

        }

        return false;
    });

    requestModalName.send(formData);
}


function changeObjectVariation()
{

    let replaceId = 'purchase_product_stock_form_preModification';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.purchase_product_stock_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {

            let result = requestModalName.response.getElementById('preModification');

            document.getElementById('preModification').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});
            }

        }

        return false;
    });

    requestModalName.send(formData);

}


function addProductPurchase()
{

    /* Блок для новой коллекции КАТЕГОРИИ */
    let $blockCollectionStock = document.getElementById('collectionStock');

    /* Добавляем новую коллекцию */
    //$addButtonStock.addEventListener('click', function () {


    let $errorFormHandler = null;

    let header = 'Добавить лист закупки продукции';


    let $preTotal = document.getElementById('purchase_product_stock_form_preTotal');
    let $TOTAL = $preTotal.value * 1;

    if ($TOTAL === undefined || $TOTAL < 1) {

        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"'+header+'"  , ' +
            '"message" : "Не заполнено количество" }';

    }

    let $number = document.getElementById('purchase_product_stock_form_number');

    if ($number.value.length === 0) {
        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"'+header+'"  , ' +
            '"message" : "Не заполнен номер закупки" }';
    }

    // let $preWarehouse = document.getElementById('purchase_product_stock_form_preWarehouse');
    // if ($preWarehouse.value.length === 0) {
    //
    //     $errorFormHandler = '{ "type":"danger" , ' +
    //         '"header":"Добавить лист закупки продукции"  , ' +
    //         '"message" : "' + $preWarehouse.options[0].textContent + '" }';
    //
    // }


    let $preProduct = document.getElementById('purchase_product_stock_form_preProduct');
    if ($preProduct.value.length === 0) {

        $errorFormHandler = '{ "type":"danger" , ' +
            '"header":"'+header+'"  , ' +
            '"message" : "' + $preProduct.options[0].textContent + '" }';

    }


    let $preOffer = document.getElementById('purchase_product_stock_form_preOffer');
    if ($preOffer) {
        if ($preOffer.tagName === 'SELECT' && $preOffer.value.length === 0) {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"'+header+'"  , ' +
                '"message" : "' + $preOffer.options[0].textContent + '" }';
        }
    }


    let $preVariation = document.getElementById('purchase_product_stock_form_preVariation');
    if ($preVariation) {
        if ($preVariation.tagName === 'SELECT' && $preVariation.value.length === 0) {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"'+header+'"  , ' +
                '"message" : "' + $preVariation.options[0].textContent + '" }';
        }
    }

    let $preModification = document.getElementById('purchase_product_stock_form_preModification');
    if ($preModification) {
        if ($preModification.tagName === 'SELECT' && $preModification.value.length === 0) {

            $errorFormHandler = '{ "type":"danger" , ' +
                '"header":"'+header+'"  , ' +
                '"message" : "' + $preModification.options[0].textContent + '" }';
        }
    }


    /* Выводим сообщение об ошибке заполнения */

    if ($errorFormHandler) {
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

    // let $warehouse = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_warehouse');
    // $warehouse.value = $preWarehouse.value;

    let $product = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_product');
    $product.value = $preProduct.value;

    let $offer = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_offer');
    $offer.value = $preOffer.value;

    let $variation = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_variation');
    $variation.value = $preVariation.value;

    let $modification = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_modification');
    $modification.value = $preModification.value;

    let $total = stockDiv.querySelector('#purchase_product_stock_form_product_' + index + '_total')
    $total.value = $preTotal.value;


    let productIndex = $preProduct.selectedIndex;
    let $productName = $preProduct.options[productIndex].textContent;

    let offerIndex = $preOffer.selectedIndex;
    let $offerName = $preOffer.tagName === 'SELECT' ? document.querySelector('label[for="'+$preOffer.id+'"]').textContent +' '+$preOffer.options[offerIndex].textContent : '';

    let variationIndex = $preVariation.selectedIndex;
    let $variationName = $preVariation.tagName === 'SELECT' ? document.querySelector('label[for="'+$preVariation.id+'"]').textContent +' '+$preVariation.options[variationIndex].textContent : '';

    let modificationIndex = $preModification.selectedIndex;
    let $modificationName = $preModification.tagName === 'SELECT' ? document.querySelector('label[for="'+$preModification.id+'"]').textContent +' '+$preModification.options[modificationIndex].textContent : '';


    let $productTextBlock = stockDiv.querySelector('#product-text-' + index);
    $productTextBlock.innerHTML = $productName + ' ' + $offerName+ ' ' + $variationName+ ' ' + $modificationName + '&nbsp; : &nbsp;' + $total.value + ' шт.';

    $preTotal.value = null;


    /* Удаляем при клике колекцию СЕКЦИЙ */
    stockDiv.querySelector('.del-item-product').addEventListener('click', function () {
        this.closest('.item-collection-product').remove();
        index = $addButtonStock.dataset.index * 1;
        $addButtonStock.dataset.index = (index - 1).toString();
    });

    /* Увеличиваем data-index на 1 после вставки новой коллекции */
    $addButtonStock.dataset.index = (index + 1).toString();

    /* применяем select2 */
    //new NiceSelect(div.querySelector('[data-select="select2"]'), {searchable: true});


    //});
}



