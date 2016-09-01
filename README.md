# Magento.Integracao

## Magento Connect ##
https://www.magentocommerce.com/magento-connect/mundipagg-payment-gateway.html

## System requirements ##
PHP 5.5

## Installation with modman ##

modman: https://github.com/sitewards/modman-php

```
cd $PROJECT
modman init
modman clone https://github.com/mundipagg/Magento.Integracao
```
## Simulator rules by amount ##

### Authorization ###
Authorized: <= $ 1.050,00

Timeout: >= $ 1.050,01 && < $ 1.051,71

Not Authorized: >= $ 1.500,00

## Documentation ##

http://docs.mundipagg.com
