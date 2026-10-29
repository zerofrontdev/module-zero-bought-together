<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\BoughtTogether\Block;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Zero\BoughtTogether\Model\Cart;
use Zero\BoughtTogether\Model\Picks;

/**
 * The total of the picked products and the button; rendered in the page
 * and again by Controller\Total\Index when a checkbox changes.
 */
class Summary extends Template
{
    private ?array $products = null;

    public function __construct(
        Context $context,
        private readonly Picks $picks,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function count(): int
    {
        return count($this->products());
    }

    public function total(): string
    {
        $sum = 0.0;
        foreach ($this->products() as $product) {
            // The list's final price (the price index, with catalog rules) when the product came
            // from a list; getFinalPrice() works it out again, a catalog rule query a product.
            $price = $product->getData('final_price');
            $sum += $price !== null ? (float) $price : (float) $product->getFinalPrice();
        }

        return $this->priceCurrency->convertAndFormat($sum, false);
    }

    /**
     * @return \Magento\Catalog\Model\Product[] the picked ids that are products for sale here
     */
    private function products(): array
    {
        if ($this->products === null) {
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $this->getData('ids'))))), 0, Cart::MAX);
            // On the product page the parent block hands its products over; the totals request loads them.
            $given = $this->getData('products');
            $products = is_array($given) ? array_intersect_key($given, array_flip($ids)) : $this->picks->load($ids);
            $this->products = array_filter($products, static fn ($product) => $product->isSalable());
        }

        return $this->products;
    }
}
