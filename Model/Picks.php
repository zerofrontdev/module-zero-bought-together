<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\BoughtTogether\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * The products shown with one: the ones orders pair it with, filled up
 * with its related products when allowed; enabled, visible, in stock,
 * in this order.
 */
class Picks
{
    public function __construct(
        private readonly Pairs $pairs,
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly Visibility $visibility
    ) {
    }

    /**
     * @return Product[]
     */
    public function for(Product $product): array
    {
        $count = $this->config->count();
        $ids = self::order(
            $this->pairs->with((int) $product->getId(), $count * 3),
            $this->config->fillWithRelated() ? $this->relatedIds($product) : [],
            (int) $product->getId()
        );
        if (!$ids) {
            return [];
        }
        $products = $this->load($ids);
        $picks = [];
        foreach ($ids as $id) {
            $pick = $products[$id] ?? null;
            if ($pick && $pick->isSalable()) {
                $picks[] = $pick;
            }
            if (count($picks) === $count) {
                break;
            }
        }

        return $picks;
    }

    /**
     * @param int[] $ids
     * @return Product[] by id
     */
    /**
     * The related products' ids in their position order, from the link table
     * alone: core's getRelatedProductIds() loads them as full products first.
     *
     * @return int[]
     */
    private function relatedIds(Product $product): array
    {
        $links = [];
        foreach ($product->getRelatedLinkCollection() as $link) {
            $links[] = ['id' => (int) $link->getLinkedProductId(), 'position' => (int) $link->getPosition()];
        }
        usort($links, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return array_column($links, 'id');
    }

    public function load(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create()
            ->addStoreFilter()
            ->setVisibility($this->visibility->getVisibleInSiteIds())
            ->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'price', 'special_price', 'special_from_date', 'special_to_date', 'required_options', 'tax_class_id'])
            ->addIdFilter($ids)
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();
        $products = [];
        foreach ($collection as $product) {
            $products[(int) $product->getId()] = $product;
        }

        return $products;
    }

    /**
     * Ids in the order to show: the paired ones first, then the related ones; no repeats, not the product itself.
     *
     * @param int[] $paired
     * @param int[] $related
     * @return int[]
     */
    public static function order(array $paired, array $related, int $productId): array
    {
        $ids = [];
        foreach ([...$paired, ...$related] as $id) {
            if ($id > 0 && $id !== $productId && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
