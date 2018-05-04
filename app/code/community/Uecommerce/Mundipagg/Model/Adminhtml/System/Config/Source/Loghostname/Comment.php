<?php

class Uecommerce_Mundipagg_Model_Adminhtml_System_Config_Source_Loghostname_Comment extends Mage_Core_Model_Config_Data
{
    public function getCommentText($element,  $currentValue)
    {
        $comment = 'Se marcado, o nome do host é adicionado ao arquivo de log. Sendo o nome do host <strong>'. gethostname();
        $comment .= '</strong>, o arquivo será salvo com o seguinte nome:<br /><br /><span id="log-file-ex-dirname"></span>/';

        $filename = "Mundipagg_PaymentModule_";
        $filename .= (new DateTime)->format('Y-m-d');
        $filename .= "_<strong>".gethostname()."</strong>.log";

        $nonDefaultLogDir = Mage::getStoreConfig('payment/mundipagg_standard/logNonDefaultLogPath') == '1';
        $nonDefaultLogDir = $nonDefaultLogDir ? 'true' : 'false';

        $script = "
            <script>   
                document.querySelector('#log-file-ex-dirname').innerHTML = 'var/log';             
                if (".$nonDefaultLogDir.") {
                    document.querySelector('#log-file-ex-dirname').innerHTML = 
                        document.querySelector('#payment_mundipagg_standard_logPath').value;                    
                }  
                document.querySelector('#payment_mundipagg_standard_logNonDefaultLogPath').onchange = function () {
                    document.querySelector('#payment_mundipagg_standard_logPath').dispatchEvent(new Event('input'));
                };
                document.querySelector('#payment_mundipagg_standard_logPath').oninput = function() {
                        document.querySelector('#log-file-ex-dirname').innerHTML = 'var/log';
                        var nonDefaultLogDir = 
                            document.querySelector('#payment_mundipagg_standard_logNonDefaultLogPath').value == '1';                        
                        if (nonDefaultLogDir) {
                            document.querySelector('#log-file-ex-dirname').innerHTML = this.value;
                        }
                    };
            </script>
        ";

        $comment .= $filename . $script;

        return $comment;
    }
}
