<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\BoughtTogether\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adds the picked products, one of each, to the cart.
 */
class Cart
{
    public const MAX = 10;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int[] $ids
     * @return array{added: string[], failed: string[]} product names added; reasons for the rest
     */
    public function add(array $ids): array
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $added = [];
        $failed = [];
        foreach (array_slice(array_unique(array_map('intval', $ids)), 0, self::MAX) as $id) {
            if ($id <= 0) {
                continue;
            }
            try {
                $product = $this->productRepository->getById($id, false, $storeId);
                $result = $quote->addProduct($product, new DataObject(['qty' => 1]));
                if (is_string($result)) {
                    $failed[] = $result;
                    continue;
                }
                $added[] = (string) $product->getName();
            } catch (NoSuchEntityException) {
                continue;
            } catch (LocalizedException $e) {
                $failed[] = $e->getMessage();
            }
        }
        if ($added) {
            // The mini cart and its count read the totals: collect them before saving.
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->cartRepository->save($quote);
        }

        return ['added' => $added, 'failed' => $failed];
    }
}
