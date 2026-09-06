# VMS Commerce Discounts 0.2.12 Test Plan

## Goal

Confirm Commerce Discounts activates without WooCommerce Square while preserving the existing Square bridge when WooCommerce Square is available.

## Missing-Square activation

1. In an isolated WordPress environment, activate WooCommerce and Backstage Venue Manager.
2. Leave WooCommerce Square unavailable.
3. Activate VMS Commerce Discounts.
4. Confirm activation completes without a PHP fatal.
5. Confirm the Commerce settings page and non-Square callbacks remain registered.
6. Confirm the Square bridge classes and Square gateway filters are not registered.
7. Confirm administrators see that WooCommerce Square integration is unavailable while other Commerce Discounts features continue.

## Square-present preservation

1. Activate WooCommerce Square before Commerce Discounts.
2. Confirm both Commerce Square bridge classes load.
3. Confirm the credit-card and Cash App `get_order` filters each retain one Commerce callback.
4. Confirm no missing-Square notice appears.

## Dependency preservation

1. With WooCommerce unavailable, confirm the existing WooCommerce dependency notice still appears without a fatal.
2. With Backstage Venue Manager unavailable, confirm Commerce continues to follow its existing WooCommerce-only dependency contract.

## Safety boundary

Do not dispatch a payment, Square API request, order mutation, email, webhook, or production synchronization during this compatibility test.
