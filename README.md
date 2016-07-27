# Magento.Integracao

## Magento Connect ##
https://www.magentocommerce.com/magento-connect/mundipagg-payment-gateway.html

## Installation with modman ##

modman: https://github.com/sitewards/modman-php

```
cd $PROJECT
modman init
modman clone https://github.com/mundipagg/Magento.Integracao
```
## Simulator rules by amount ##

### Authorization ###
<= $ 1.050,00 -> Authorized
>= $ 1.050,01 && < $ 1.051,71 -> Timeout
>= $ 1.500,00 -> Not Authorized

### Capture ###
<= $ 1.050,00 -> Captured
>= $ 1.050,01 -> Not Captured

## Documentation ##

http://docs.mundipagg.com
