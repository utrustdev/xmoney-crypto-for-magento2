# Store setup guide

This guide is for two readers: a merchant who already has a Magento store, and someone who still needs to install Magento. It covers installing **xMoney Crypto for Magento 2**, connecting the xMoney merchant dashboard, and what shoppers and orders look like afterward.

The [README](../README.md) is the short overview. People changing the extension source should use the [developer guide](developer_guide.md).

## If you do not have a Magento store yet

Magento is the store application. Shoppers browse the catalog and check out there. You manage products and orders in the Magento admin. This repository only adds a crypto payment method. Install Magento, confirm you can place a normal test order, then come back to [Install the extension](#install-the-extension).

The local sandbox is Magento Open Source **2.4.7-p10** on **PHP 8.2**. Check the [system requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements) for the database and search versions that match that release. The package constraint is PHP 7.4 or newer and `magento/framework` 102 or newer. Use the PHP version your Magento release requires.

Install with Composer, from Adobe’s [on-premises installation guide](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/composer). For the Docker sandbox at `~/Sites/magento`, follow [Install Magento with Docker](developer_guide.md#install-magento-with-docker). That section uses [Mark Shust's Docker setup](https://github.com/markshust/docker-magento#setup), the patched release `2.4.7-p10`, and a local Composer `auth.json`.

1. Create [authentication keys](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/prerequisites/authentication-keys) so Composer can download Magento from `https://repo.magento.com/`.
2. From the directory that should contain the store, create the project.

Magento Open Source:

```bash
composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition .
```

Adobe Commerce:

```bash
composer create-project --repository-url=https://repo.magento.com/ magento/project-enterprise-edition .
```

3. Finish the Magento setup wizard or `bin/magento setup:install`, using the same guide. Run Magento commands as the file system owner of the Magento files.
4. Sign in to the admin. The path is chosen during install. A common URL is `https://<your-store>/admin`.

You need SSH access to the server for the commands in this guide. FTP can upload the extension files. The enable and upgrade commands still run over SSH.

## What you need for xMoney

- The Magento store from the previous section, or a store you already operate on 2.3 or 2.4.
- An xMoney Crypto merchant account.
  - Testing: [sandbox merchant dashboard](https://merchants.sandbox.crypto.xmoney.com/)
  - Live payments: [live merchant dashboard](https://merchants.crypto.xmoney.com/)
- The latest extension zip from the [releases page](https://github.com/utrustdev/xmoney-crypto-for-magento2/releases).

Product background is on [xMoney Crypto Pay](https://xmoney.com/crypto-pay).

## Install the extension

Run these commands from the Magento project root, the directory that contains `bin/magento`.

1. Download the latest release zip from the [releases page](https://github.com/utrustdev/xmoney-crypto-for-magento2/releases).
2. Unzip it so the module files land in `app/code/Utrust/Payment`. That folder should contain `registration.php` and `etc/module.xml`.
3. Enable the module and update the database.

```bash
bin/magento module:enable Utrust_Payment
bin/magento setup:upgrade
bin/magento cache:flush
```

Adobe’s reference for these commands is [Enable or disable modules](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/tutorials/manage-modules).

On a store in **production** mode, keep already generated code and publish the checkout assets this extension adds (the payment template, the checkout script, and the logo):

```bash
bin/magento module:enable Utrust_Payment
bin/magento setup:upgrade --keep-generated
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

4. Open **Stores → Configuration → Sales → Payment Methods**. **xMoney Crypto** in that list means the module is installed. It stays hidden from shoppers until you enable it in the next section.

## Create credentials in xMoney

Use the dashboard that matches how you want to test:

| Goal | Dashboard | Magento Test mode |
| --- | --- | --- |
| Test payments | [Sandbox](https://merchants.sandbox.crypto.xmoney.com/) | Yes |
| Live payments | [Live](https://merchants.crypto.xmoney.com/) | No |

1. Log in, or create an account.
2. In the left sidebar, open **Integrations**.
3. Select **Magento 2** and click **Generate Credentials**.
4. Copy the **Api Key** and the **Webhook Secret** before you leave the page.

The Webhook Secret is shown once. After a refresh it cannot be copied again. Generate new credentials if you need a new secret. Treat both values as private. Someone who has them can create payment orders for your merchant account.

Sandbox keys only work with **Test mode** Yes. Live keys only work with **Test mode** No. The extension sends test orders to `https://merchants.api.sandbox.crypto.xmoney.com/api` and live orders to `https://merchants.api.crypto.xmoney.com/api`.

## Configure the payment method

In the Magento admin, open **Stores → Configuration → Sales → Payment Methods → xMoney Crypto**.

1. Set **Enabled** to **Yes**.
2. Under **Checkout Flow**, choose when Magento creates the order:
   - **Alternative: No.** Magento creates the order when the shopper places it, then redirects to xMoney. If the shopper cancels on the payment page, Magento cancels that order and puts the items back in the cart.
   - **Alternative: Yes.** Magento waits until xMoney reports that the payment was detected, and creates the order then. The shopper still pays on the xMoney page. Use this when you want an order only after payment has started.
3. Under **Credentials**, set **Test mode**, then paste the **Api Key** and **Webhook Secret** from the matching dashboard.
4. Under **Options**, choose the status Magento gives a new order. The default is Pending. A received crypto payment later moves the order to Processing and creates an invoice. You can also limit the method to all allowed countries or to specific countries.
5. Under **Frontend**, set the **Title** and **Instructions** shoppers see at checkout. The defaults say they will be redirected to the xMoney payment page and can pay with Bitcoin, Ethereum, Tether, or another supported cryptocurrency.
6. Click **Save Config**.

If the method does not show on the storefront after a refresh, flush the cache:

```bash
bin/magento cache:flush
```

### When the method appears at checkout

The method is offered when the store **base currency** is one of the currencies below. A store whose base currency is outside this list hides xMoney Crypto even when the method is enabled.

<details>
<summary>Supported store currencies</summary>

USD, EUR, GBP, ARS, AUD, BRL, CAD, CLP, CNY, CZK, DKK, DOP, HKD, HUF, INR, IDR, ILS, JPY, KRW, MYR, MXN, NZD, NOK, PKR, PHP, PLN, RON, RUB, SGD, ZAR, SEK, CHF, TWD, THB, AED.

</details>

Under **Options → Payment from Applicable Countries** you can offer the method for every allowed country or for a list you select. Some countries are left off that list. If xMoney rejects the billing country while starting the payment, the shopper returns to the cart with a message to use a different billing address or another payment method.

## What the shopper does

1. Add a product to the cart and open checkout. The storefront clip in the [README](../README.md) shows that first step on the Luma sample theme.
2. Choose **xMoney Crypto – Pay with crypto** and place the order.
3. Complete payment on the xMoney payment page.
4. Return to the store. A paid order ends on the Magento success page.

The drawing in the [README](../README.md) walks through checkout, this extension, the payment page, and the merchant dashboard together.

If the shopper cancels on the xMoney page:

- **Alternative: No.** Magento cancels the order and restores the cart.
- **Alternative: Yes.** The shopper returns to the cart. Magento has no order to cancel unless a payment notification already created one.

With **Alternative: Yes**, the success page waits a few seconds for the payment notification to create the order. If that notification has not arrived yet, the shopper sees a notice that the payment is still confirming and the order will appear when it does.

## What you see after a payment

- In Magento admin, under **Sales → Orders**, a new order starts in the status you configured (Pending by default).
- When xMoney reports the payment received, the extension creates an invoice and sets the order to **Processing**. The order comment includes the currency and amount from xMoney.
- When xMoney reports the payment cancelled, the extension cancels the order. The comment records that xMoney canceled the payment.
- The same payment is visible in the xMoney merchant dashboard you used for the credentials.

A credit memo in Magento does not refund the crypto payment in this release. On xMoney, a refund is a proposal the buyer accepts.

## Update

Check the [releases page](https://github.com/utrustdev/xmoney-crypto-for-magento2/releases) for a newer zip. Replace the files in `app/code/Utrust/Payment` with the new release, then run the same upgrade and cache commands you used to install. On production mode, deploy static content again.

## Uninstall

Modules installed with Composer should be removed with Magento’s [uninstall modules](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/tutorials/uninstall-modules) command. A zip install like the one in this guide lives in `app/code` and is removed on the file system:

```bash
bin/magento module:disable Utrust_Payment
bin/magento setup:upgrade
rm -rf app/code/Utrust
bin/magento cache:clean
```

On a production-mode store, deploy static content after that so the old checkout files are cleared:

```bash
bin/magento setup:static-content:deploy -f
```

Disabling the method under **Payment Methods** (set **Enabled** to **No**) leaves the code installed and removes it from checkout. Use that when you want to pause crypto payments.

## If something fails

- The method is missing at checkout. Confirm **Enabled** is Yes, the base currency is supported, and you flushed the cache.
- xMoney rejects the credentials. Confirm **Test mode** and the key came from the same dashboard.
- The Webhook Secret was lost. Generate credentials again in the dashboard and paste the new pair into Magento.
- The shopper is returned to the cart. Magento writes extension messages to `var/log/utrust.log` in the Magento root.

For extension help, open a [GitHub issue](https://github.com/utrustdev/xmoney-crypto-for-magento2/issues/new). For the merchant account, email [support@xmoney.com](mailto:support@xmoney.com).
