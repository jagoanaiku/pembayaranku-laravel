# Changelog

## 1.0.2

- Prepare a dedicated Packagist package with standalone Composer metadata, Laravel auto-discovery, license, and environment example.
- Align the dependency with published PHP SDK 1.1.0 and remove monorepo-only repository metadata.

## 1.0.1

- Accept the canonical `payment.paid` event while preserving signature checks, environment scope, and idempotent event processing.

## 1.0.0

- Initial Laravel package for Merchant API V1 invoice operations and Hosted Checkout redirects.
- Added signed webhook route, timestamp validation, event-ID storage, retry responses, and a synchronous handler contract.
- Webhook business handling remains the merchant application's responsibility; only invoice status `paid` should mark its order paid.
