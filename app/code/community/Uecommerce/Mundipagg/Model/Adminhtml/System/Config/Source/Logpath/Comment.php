<?php

class Uecommerce_Mundipagg_Model_Adminhtml_System_Config_Source_Logpath_Comment extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        if (Mage::getStoreConfig('payment/mundipagg_standard/logNonDefaultLogPath') == '1') {
            $logPath = $this->getValue();

            $checkLogFile = $logPath . DS . 'mundipagg_checklogfile';

            $havePermissions = true;
            if (!is_dir($logPath)) {
                if (!mkdir($logPath)) {
                    $havePermissions = false;
                }
            }

            if (file_put_contents($checkLogFile, '') === false) {
                $havePermissions = false;
            }
            unlink($checkLogFile);

            if (!$havePermissions) {
                Mage::throwException(
                    "Não foi possível configurar '$logPath' como diretório de escrita de logs: " .
                    "Não é possível escrever no diretório."
                );
            }
        }

        return parent::save();
    }

    public function getCommentText($element,  $currentValue)
    {
        $comment = 'Diretório onde os arquivos de log do módulo serão salvos.';

        if (Mage::getStoreConfig('payment/mundipagg_standard/logNonDefaultLogPath') == '1') {
            $logPath = Mage::getStoreConfig('payment/mundipagg_standard/logPath');

            $checkLogFile = $logPath . DS . 'mundipagg_checklogfile';

            $havePermissions = true;
            if (!is_dir($logPath)) {
                if (!mkdir($logPath)) {
                    $havePermissions = false;
                }
            }

            if (file_put_contents($checkLogFile, '') === false) {
                $havePermissions = false;
            }
            unlink($checkLogFile);

            if (!$havePermissions) {
                $comment .= "<br /><br /><span style='color:red;'>Atenção! o diretório <strong>'".$logPath."'";
                $comment .= "</strong> não possui permissão de escrita para o usuário do servidor! <br />";
                $comment .= "Para que os arquivos de log sejam gravados corretamente, por favor defina estas permissões.</span>";
            }
        }

        return $comment;
    }
}
