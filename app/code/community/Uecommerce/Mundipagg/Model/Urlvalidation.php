<?php

class Uecommerce_Mundipagg_Model_Urlvalidation extends Mage_Core_Model_Config_Data
{

    public function save()
    {
        $url = $this->getValue();

        if (is_null($url) || empty($url)) {
            return;
        }

        $parsedUrl = parse_url($url);
        $parsedUrl['path'] = 'Sale';
        $newUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}/{$parsedUrl['path']}/";

        $this->setValue($newUrl);

        return parent::save();
    }
}
