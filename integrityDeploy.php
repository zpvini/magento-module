<?php
//creating moduleFiles md5;

$integrityEngineClassFilePath = './app/code/community/Uecommerce/Mundipagg/etc/integrity/IntegrityEngine.php';
$integrityFilePath = './app/code/community/Uecommerce/Mundipagg/etc/integrity/integrityCheck';
$modmanFilePath = 'modman';


require_once $integrityEngineClassFilePath;
$integrityEngine = new IntegrityEngine();
$integrityData = $integrityEngine->generateModuleFilesMD5s($modmanFilePath);
file_put_contents($integrityFilePath,json_encode($integrityData));
echo 'integrityFile generated.';