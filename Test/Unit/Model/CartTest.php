<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\BoughtTogether\Test\Unit\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\BoughtTogether\Model\Cart;

/**
 * "Add all to cart": the cart is saved with fresh totals (the mini cart and
 * its count read them), and not at all when nothing was added.
 */
class CartTest extends TestCase
{
    private Quote&MockObject $quote;
    private CartRepositoryInterface&MockObject $cartRepository;
    /** @var string[] */
    private array $log = [];

    protected function setUp(): void
    {
        $this->quote = $this->createPartialMock(Quote::class, ['addProduct', 'collectTotals']);
        $this->quote->setData('totals_collected_flag', true);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
    }

    public function testTotalsAreCollectedBeforeTheCartIsSaved(): void
    {
        $this->quote->method('addProduct')->willReturnCallback(function (Product $product) {
            $this->log[] = 'add ' . $product->getName();
            return $this->quote;
        });
        $this->quote->expects($this->once())->method('collectTotals')->willReturnCallback(function () {
            // Already collected earlier in the request: the flag must be off, or nothing is recounted.
            $this->log[] = 'collect, flag ' . var_export($this->quote->getData('totals_collected_flag'), true);
            return $this->quote;
        });
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote)
            ->willReturnCallback(function () {
                $this->log[] = 'save';
            });

        $result = $this->cart()->add([3, 4, 3]);

        $this->assertSame(['added' => ['Mat', 'Strap'], 'failed' => []], $result);
        $this->assertSame(['add Mat', 'add Strap', 'collect, flag false', 'save'], $this->log);
    }

    public function testNothingAddedSavesNothing(): void
    {
        $this->quote->method('addProduct')->willReturn('This product is out of stock.');
        $this->quote->expects($this->never())->method('collectTotals');
        $this->cartRepository->expects($this->never())->method('save');

        $result = $this->cart()->add([3, 99, 0]);

        $this->assertSame(['added' => [], 'failed' => ['This product is out of stock.']], $result);
    }

    private function cart(): Cart
    {
        $session = $this->createStub(CheckoutSession::class);
        $session->method('getQuote')->willReturn($this->quote);
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willReturnCallback(function (int $id) {
            $names = [3 => 'Mat', 4 => 'Strap'];
            if (!isset($names[$id])) {
                throw new NoSuchEntityException(__('No such product.'));
            }
            $product = (new \ReflectionClass(Product::class))->newInstanceWithoutConstructor();
            $product->setData(['entity_id' => $id, 'name' => $names[$id]]);
            return $product;
        });
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Cart($session, $this->cartRepository, $products, $storeManager);
    }
}
