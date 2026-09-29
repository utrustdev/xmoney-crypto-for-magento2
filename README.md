![xMoney Crypto integrations](https://github.com/user-attachments/assets/66de9ccd-adab-456c-a673-09c20d182c4c)

# xMoney Crypto for Magento 2

Accept Bitcoin, Ethereum, eGLD, UTK Token, and other juicy cryptocurrencies directly on your online store and get settled in the currency of your choice.

With xMoney Crypto Pay, grow your business by allowing your customers to enjoy a vast portfolio of fiat & crypto currencies when purchasing goods and services, with a zero-fee exchange rate.

Find out more at [xMoney.com/crypto-pay](https://xmoney.com/crypto-pay)

xMoney is the world's digital payments network for all things money. Crypto-enabled & Fiat-ready, with a suite of solutions for anyone, anywhere. Powered by [MultiversX](https://multiversx.com/).

## Requirements

- xMoney Crypto Merchant account
- Online store in Magento 2.4.x or 2.3.x

## Install and Update

### Install

You will need FTP and SSH access to install this module:

1. Download our latest release zip file on the [releases page](https://github.com/utrustdev/xmoney-crypto-for-magento2/releases).
2. Unzip the zip file in `app/code/Utrust/Payment`.
3. Enable the module by running `bin/magento module:enable Utrust_Payment`
4. Apply database updates by running `bin/magento setup:upgrade`
5. Flush the cache by running `bin/magento cache:flush`
6. Go to your Magento admin dashboard (it should be something like https://<your-store.com>/admin).
7. If you go to _Stores -> Sales -> Payment Methods_ and _xMoney Crypto_ is there it's successfully installed!

** Note: In production please use the `--keep-generated` option **

### Update

You can always check our [releases page](https://github.com/utrustdev/xmoney-crypto-for-magento2/releases) for a new version. You can update by following the same instructions as installing.

### Uninstall (without Composer)

If [removing with Composer](https://devdocs.magento.com/guides/v2.3/install-gde/install/cli/install-cli-uninstall-mods.html) doesn't work (which is recommended), you can try to remove it manually:

```
bin/magento module:disable Utrust_Payment
bin/magento setup:upgrade
rm -rf app/code/Utrust
bin/magento cache:clean
bin/magento setup:static-content:deploy -f
```

## Setup

### On the xMoney side

1. Go to [xMoney Crypto merchant dashboard](https://merchants.crypto.xmoney.com/).
2. Log in or sign up if you didn't yet.
3. On the left sidebar choose _Integrations_.
4. Select _Magento 2_ and click the button _Generate Credentials_.
5. You will see now your `Api Key` and `Webhook Secret`, save them somewhere safe temporarily.

   :warning: You will only be able to see the `Webhook Secret` once, after refreshing or changing page it will be no longer available to copy; if needed, you can always generate new credentials.

   :no_entry_sign: Don't share your credentials with anyone. They can use it to place orders **on your behalf**.

### On the Magento side

1. Go to your Magento admin dashboard.
2. Navigate to _Stores -> Configuration -> Sales -> Payment Methods -> xMoney Crypto_.
3. Add your `Api Key` and `Webhook Secret` and click "Save Config" button on top.
4. Done!

## Features

:sparkles: These are the features already implemented and planned for the xMoney Crypto for Magento 2 plugin:

- [x] Creates Order and redirects to the xMoney payment page
- [x] Receives and handles webhook payment received
- [x] Receives and handles webhook payment cancelled
- [ ] Starts a refund on xMoney when a credit memo is created in Magento. Not in this release: xMoney refunds are proposals the buyer must accept, so a Magento credit memo does not refund the crypto payment.

## Support

Feel free to reach [by opening an issue on GitHub](https://github.com/utrustdev/xmoney-crypto-for-magento2/issues/new) if you need any help with the xMoney Crypto for Magento 2 plugin.

If you're having specific problems with your account, then please contact [support@xmoney.com](https://mailto:support@xmoney.com/).

In both cases, our team will be happy to help :purple_heart:.

## Contribute

This plugin was initially written by a third-party contractor (Moisés Sequeira from [CloudInfo](https://cloudinfo.pt/)), and is now maintained by the xMoney development team.

We have now opened it to the world so that the community using this plugin may have the chance of shaping its development.

You can contribute by simply letting us know your suggestions or any problems that you find [by opening an issue on GitHub](https://github.com/utrustdev/xmoney-crypto-for-magento2/issues/new).

You can also fork the repository on GitHub and open a pull request for the `master` branch with your missing features and/or bug fixes.
Please make sure the new code follows the same style and conventions as already written code.
Our team is eager to welcome new contributors into the mix :blush:.

### Development

If you want to get your hands dirty and make your own changes to the xMoney Crypto for Magento plugin, we recommend you to install it in a local Magento store (either directly on your computer or using a virtual host) so you can make the changes in a controlled environment.
Alternatively, you can also do it in a Magento online store that you have for testing/staging.

Once the plugin is installed in your store, the source code should be in `app/code/Utrust/Payment/`. All the changes there should be reflected live in the store (if it doesn't, go to _System -> Cache Management_ and flush the cache).
If something goes wrong with the module, logs can be found in `var/log/utrust.log`.

## Publishing

For now only members of the xMoney development team can publish new versions of the xMoney Crypto for Magento 2 plugin.

To publish a new version, simply follow [these instructions](https://github.com/utrustdev/xmoney-crypto-for-magento2/wiki/Publishing).

## License

The Xmoney Crypto for Magento 2 plugin is maintained with :purple_heart: by the xMoney development team, and is available to the public under the GNU GPLv3 license. Please see [LICENSE](https://github.com/utrustdev/xmoney-crypto-for-magento2/blob/master/LICENSE) for further details.

&copy; Utrust 2024
