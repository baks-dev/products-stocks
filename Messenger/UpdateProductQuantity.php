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

declare(strict_types=1);

namespace BaksDev\Products\Stocks\Messenger;

use BaksDev\Products\Product\Repository\ProductQuantity\ProductModificationQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductOfferQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityInterface;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductVariationQuantityInterface;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class UpdateProductQuantity
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private ProductModificationQuantityInterface $modificationQuantity;
    private ProductVariationQuantityInterface $variationQuantity;
    private ProductOfferQuantityInterface $offerQuantity;
    private ProductQuantityInterface $productQuantity;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        ProductModificationQuantityInterface $modificationQuantity,
        ProductVariationQuantityInterface $variationQuantity,
        ProductOfferQuantityInterface $offerQuantity,
        ProductQuantityInterface $productQuantity,
        EntityManagerInterface $entityManager
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->modificationQuantity = $modificationQuantity;
        $this->variationQuantity = $variationQuantity;
        $this->offerQuantity = $offerQuantity;
        $this->productQuantity = $productQuantity;
    }

    public function __invoke(ProductStockMessage $message)
    {
        // Получаем всю продукцию в ордере со статусом Incoming
        $products = $this->productStocks->getProductsIncomingStocks($message->getId());

        if ($products) {
            $this->entityManager->clear();

            /** @var ProductStockProduct $product */
            foreach ($products as $product)
            {
                $ProductUpdateQuantity = null;

                // Количественный учет модификации множественного варианта торгового предложения
                if (null === $ProductUpdateQuantity && $product->getModification()) {
                    $this->entityManager->clear();

                    $ProductUpdateQuantity = $this->modificationQuantity->getProductModificationQuantity(
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation(),
                        $product->getModification()
                    );
                }

                // Количественный учет множественного варианта торгового предложения
                if (null === $ProductUpdateQuantity && $product->getVariation()) {
                    $this->entityManager->clear();

                    $ProductUpdateQuantity = $this->variationQuantity->getProductVariationQuantity(
                        $product->getProduct(),
                        $product->getOffer(),
                        $product->getVariation()
                    );
                }

                // Количественный учет торгового предложения
                if (null === $ProductUpdateQuantity && $product->getOffer()) {
                    $this->entityManager->clear();

                    $ProductUpdateQuantity = $this->offerQuantity->getProductOfferQuantity(
                        $product->getProduct(),
                        $product->getOffer()
                    );
                }

                // Количественный учет продукта
                if (null === $ProductUpdateQuantity && $product->getOffer()) {
                    $this->entityManager->clear();

                    $ProductUpdateQuantity = $this->productQuantity->getProductQuantity(
                        $product->getProduct()
                    );
                }

                if ($ProductUpdateQuantity) {
                    $ProductUpdateQuantity->addQuantity($product->getTotal());
                    $this->entityManager->flush();
                }
            }
        }
    }
}
