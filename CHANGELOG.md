# Changelog

## 1.6.0

* Send create-order to the xMoney API, and use the xMoney payment title, admin labels, and checkout logo. The module name `Utrust_Payment`, the payment code `utrust`, and the `utrust/payment/*` routes are unchanged, so existing configuration, orders, and webhook URLs keep working.
* Return the shopper from xMoney without the 50 second wait, and keep the quote and payment id in the checkout session instead of a public cookie. A return with no order goes back to the cart.
* Verify webhooks with the xMoney signature and reject a bad signature with HTTP 400 `Authentication error.` The response does not include either MAC.
* Invoice a received payment once when xMoney retries the webhook. In the alternative flow, create the order when the payment is detected, and cancel the order that belongs to that quote.
* Require PHP 7.4 and the Magento modules this package uses, license it as GPL-3.0-only, and declare properties so checkout loads on PHP 8.2.
* A Magento credit memo still does not start an xMoney refund. An xMoney refund is a proposal the buyer has to accept.

## 1.5.0

* Fix to Create order on Magento when payment detected from Utrust as Pending

## 1.4.1-rc1

* Fix for alternative flow - checkout and success redirect

## 1.4.0

* Make compatible with Magento 2.4
* Add alternative payment flow (the order only gets created on Magento when the payment is confirmed)
* Update README

## 1.3.0

* Added more countries
* Added more currencies

## 1.2.0

* Fix arrayFlatter logic
* Add Error messages and statuses to webhooks responses

## 1.1.0

* Added Magento 2 dependencies to the require section
* Removed curl_init() and json_encode() PHP function usages in favor of Magento\Framework

## 1.0.0

* Stable release
