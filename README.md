# Magento.Integracao

## Magento Connect ##
https://www.magentocommerce.com/magento-connect/mundipagg-payment-gateway.html

## System requirements ##
PHP 5.4

## Installation with modgit ##
modgit: https://github.com/jreinke/modgit

cd /path/to/magento
modgit init
modgit add mundipagg_integracao git@github.com:mundipagg/Magento.Integracao.git

## Simulator rules by amount ##

### Authorization ###
Authorized: <= $ 1.050,00

Timeout: >= $ 1.050,01 && < $ 1.051,71

Not Authorized: >= $ 1.500,00

## Documentation ##

http://docs.mundipagg.com
