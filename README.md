# Zero_BoughtTogether

"Often bought together" on the product page: this product and the ones
orders pair it with, a checkbox on each, the total and one Add to Cart.
Configured in Stores > Configuration > Zero > Bought Together.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-bought-together
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `zerofront/module-zero-core` with it.

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

## Where the pairs come from

`zero_bought_together` holds, per product, the products that orders held
with it and in how many orders. It is rebuilt from the top-level lines of
the orders of the last N days (canceled ones aside) by the nightly cron
`zero_bought_together_build` (03:15) or by hand:

    bin/magento zero:bought-together:build [--days=365] [--min-orders=2]

A pair counts from "Minimum Orders for a Pair" orders on; the 12 most
frequent per product are kept. A product page carries its own tag (`zero_bought_together_<id>`) and its
picks' product tags; a rebuild cleans the pages of the products whose pairs
changed, not every product page at once.

## Storefront

Under the gallery and the info column: this product first, then up to
"Products Shown" picks, the paired ones most frequent first, filled up
with the product's related products when "Fill With Related Products" is
on; only enabled, visible, in-stock products. A pick that goes into the
cart as is (a simple product without required options) has a checkbox,
checked; one with options to choose (a configurable, a bundle) links to
its page instead, and so does a Price on Request product
(Zero_QuoteRequest's `zero_quote_only`, which the cart refuses). This
product itself is included when it is such a product too.

The summary (the total of the checked items at their catalog prices, the
button) is an HTMX fragment: a change on the form refetches it with the
form's values (`boughttogether/total`). The button posts the form to
`boughttogether/cart/add`, which adds one of each checked product, then
goes back to the product page (or to the cart, per Sales > Checkout >
"After Adding a Product Redirect to Shopping Cart"). No JavaScript.

## Tests

- `Test/Unit/Model/PicksTest.php`: the order of the picks.
- `dev/tests/zero-e2e/tests/bought-together.spec.ts`: the section on a
  product page, the total after unchecking a pick, adding all to the cart.
  Skipped where the product has no picks.

What changed in each release: [CHANGELOG.md](CHANGELOG.md).

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
