<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\BoughtTogether\Block;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Zero\BoughtTogether\Model\Config;
use Zero\BoughtTogether\Model\Pairs;
use Zero\BoughtTogether\Model\Picks;

/**
 * "Often bought together" on the product page: the page's product with the
 * ones picked for it, a checkbox each, a total and one Add to Cart.
 * AbstractProduct for the pictures and prices, as the related list.
 */
class BoughtTogether extends AbstractProduct implements IdentityInterface
{
    /** @var Product[]|null */
    private ?array $picks = null;

    public function __construct(
        Context $context,
        private readonly Picks $picksModel,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Product[]
     */
    public function picks(): array
    {
        if ($this->picks === null) {
            $product = $this->getProduct();
            $this->picks = $this->config->isEnabled() && $product ? $this->picksModel->for($product) : [];
        }

        return $this->picks;
    }

    /**
     * Whether one of a product goes into the cart with no choice to make (else it links to its page).
     */
    public function canPick(Product $product): bool
    {
        return $product->isSalable() && !$product->isComposite() && !$product->getRequiredOptions();
    }

    /**
     * @return int[] the ids the form adds as it first shows: the page's product (when pickable) and every pick
     */
    /**
     * The picked products themselves (the product and the picks that go into the
     * cart as they are), for the summary: it needs no second load of them.
     *
     * @return array<int, Product> id => product
     */
    public function pickedProducts(): array
    {
        $products = [];
        $product = $this->getProduct();
        if ($product && $this->canPick($product)) {
            $products[(int) $product->getId()] = $product;
        }
        foreach ($this->picks() as $pick) {
            if ($this->canPick($pick)) {
                $products[(int) $pick->getId()] = $pick;
            }
        }

        return $products;
    }

    public function pickedIds(): array
    {
        $ids = [];
        $product = $this->getProduct();
        if ($product && $this->canPick($product)) {
            $ids[] = (int) $product->getId();
        }
        foreach ($this->picks() as $pick) {
            if ($this->canPick($pick)) {
                $ids[] = (int) $pick->getId();
            }
        }

        return $ids;
    }

    /**
     * @return array{lead: string, accent: string} the heading, its last word set apart
     */
    public function heading(): array
    {
        $title = (string) __($this->config->title());
        $words = preg_split('/\s+/', trim($title)) ?: [];
        if (count($words) < 2) {
            return ['lead' => $title, 'accent' => ''];
        }
        $accent = array_pop($words);

        return ['lead' => implode(' ', $words), 'accent' => $accent];
    }

    public function addUrl(): string
    {
        return $this->getUrl('boughttogether/cart/add');
    }

    public function totalUrl(): string
    {
        return $this->getUrl('boughttogether/total');
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        $product = $this->getProduct();
        $tags = $product ? [Pairs::tagOf((int) $product->getId())] : [];
        // The picks' own tags: their price or name changing refreshes this page. Their own,
        // not getIdentities(): that asks for each simple's configurable parents, a query each.
        foreach ($this->picks() as $pick) {
            $tags[] = Product::CACHE_TAG . '_' . (int) $pick->getId();
        }

        return $tags;
    }
}
