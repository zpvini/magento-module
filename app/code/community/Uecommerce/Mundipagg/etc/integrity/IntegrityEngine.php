<?php

class IntegrityEngine
{
    const MODMAN_CHECK = 'modman';
    const INTEGRITY_CHECK = 'integrityCheck';

    protected static $NULL_HASH;

    public function __construct()
    {
        self::$NULL_HASH =  md5('null');
    }

    public function listLogFiles($directories, $logFileConfig) {
        $allLogs = [];
        foreach ($directories as $dir) {
            $allLogs = array_merge($allLogs,$this->listFilesOnDir($dir, false));
        }
        $allLogs = array_keys($allLogs);
        $allLogs = array_filter($allLogs,function($logFile) use ($logFileConfig) {
            $logFileName = explode (DIRECTORY_SEPARATOR,$logFile);
            $logFileName = end($logFileName);

            if (
                substr($logFile, -4) !== '.log' &&
                !in_array($logFileName,$logFileConfig['includes'])
            ) {
                return false;
            }

            return
                in_array($logFileName,$logFileConfig['includes']) ||
                strpos($logFileName, $logFileConfig['moduleFilenamePrefix']) === 0 ; //module log file.
        });

        return $allLogs;
    }

    private function hasPermissions($file)
    {
        if (!file_exists($file)) {
            echo "<pre>File <strong>'$file'</strong> does not exists!</pre>";
            return false;
        }

        if (!is_readable($file)) {
            echo "<pre>File <strong>'$file'</strong> is not readable!</pre>";
            return false;
        }
        return true;
    }

    public function generateCheckSum($dir, $calculateHash = true)
    {
        if (!is_dir($dir)) {
            $hash = false;
            if (file_exists($dir)) {
                $hash = $calculateHash ? md5_file($dir) : self::$NULL_HASH;
            }
            return  [
                $dir => $hash
            ];
        }

        $files = scandir($dir);
        $md5 = [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file = $dir . DIRECTORY_SEPARATOR . $file;
                $md5[$file] = $this->generateCheckSum($file, $calculateHash);
            }
        }
        return $md5;
    }

    public function generateModuleFilesMD5s($modmanFilePath, $integrityCheckFilePath)
    {

        if ($this->hasPermissions($modmanFilePath)) {
            $modmanRawData = file_get_contents($modmanFilePath);
            $rawLines = explode("\n",$modmanRawData);

            return $this->getMD5FromArrayData($rawLines, self::MODMAN_CHECK);
        }

        if ($this->hasPermissions($integrityCheckFilePath)) {
            $integrityCheckRawData = file_get_contents($integrityCheckFilePath);
            $data = json_decode($integrityCheckRawData, true);
            $rawLines = array_keys($data);

            return $this->getMD5FromArrayData($rawLines, self::INTEGRITY_CHECK);
        }

        return [];

    }

    public function isFileOverride($file)
    {
        $local = str_replace("community", "local", $file);

        if (file_exists($local) && strpos($file, "community") !== false) {
            return $local;
        }

        return false;
    }

    public function getMD5FromArrayData($arrayData, $fileOrigin = self::MODMAN_CHECK)
    {
        $md5s = [];
        foreach ($arrayData as $rawLine) {
            if (
                substr($rawLine,0,1) !== '#' &&
                strlen($rawLine) > 0
            ) {

                $line = $rawLine;
                if ($fileOrigin == self::MODMAN_CHECK) {
                    $line = array_values(array_filter(explode(' ',$rawLine)));
                    $line = './' . $line[1];
                }

                if (strpos($line, "./.modman/") !== 0) { //ignore .modman/*
                    $md5s = array_merge($md5s,$this->filterFileCheckSum(
                        $this->generateCheckSum($line)
                    ));
                }
            }
        }

        return $md5s;

    }

    public function listFilesOnDir($dir, $calculateHash = true) {
        if(!$this->hasPermissions($dir)) {
            return [];
        }

        $rawLines = [$dir];

        $md5s = [];
        foreach ($rawLines as $line) {
            $md5s = array_merge($md5s,$this->filterFileCheckSum(
                $this->generateCheckSum('./' . $line, $calculateHash)
            ));
        }

        return $md5s;
    }

    public function filterFileCheckSum($checkSumArray)
    {
        if (count($checkSumArray) === 1) {
            return $checkSumArray;
        }
        $data = serialize($checkSumArray);
        $data = explode('";s:32:"',$data);
        $currentFile = null;
        $currentMd5 = null;
        $files = [];
        foreach ($data as $line) {
            $raw = explode('"',$line);
            if( $currentFile ) {
                $files[$currentFile] = $raw[0];
                $currentFile = end($raw);
                continue;
            }
            $currentFile = end($raw);
        }
        return $files;
    }

    public function verifyIntegrity($modmanFilePath, $integrityCheckFilePath, $ignoreList = [])
    {
        $integrityData = json_decode(file_get_contents($integrityCheckFilePath),true);

        if(!$this->hasPermissions($integrityCheckFilePath)) {
            $integrityData = [];
        }

        $newFiles = [];
        $unreadableFiles = [];
        $alteredFiles = [];
        $overrideFiles = [];

        $files = $this->generateModuleFilesMD5s($modmanFilePath, $integrityCheckFilePath);

        foreach ($ignoreList as $filePath) {
            unset($files[$filePath]);
        }

        //validating files
        foreach ($files as $fileName => $md5) {
            if (substr($fileName, -strlen('integrityCheck')) == 'integrityCheck') {
                //skip validation of integrityCheck file
                continue;
            }

            $overrideFiles[] = $this->isFileOverride($fileName);

            if ($md5 === false) {
                $unreadableFiles[] = $fileName;
                continue;
            }
            if(isset($integrityData[$fileName])) {
                if ($md5 != $integrityData[$fileName]) {
                    $alteredFiles[] = $fileName;
                }
                continue;
            }
            $newFiles[$fileName] = $md5;
        }

        return [
            'files' => $files,
            'newFiles' => $newFiles,
            'unreadableFiles' => $unreadableFiles,
            'alteredFiles' => $alteredFiles,
            'overrideFiles' => array_values(array_filter($overrideFiles))
        ];
    }
}
