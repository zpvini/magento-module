<?php

class IntegrityEngine
{
    public function generateCheckSum($dir)
    {
        if (!is_dir($dir)) {
            return  [
                $dir => file_exists($dir) ? md5_file($dir) : false
            ];
        }

        $files = scandir($dir);
        $md5 = [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file = $dir . DIRECTORY_SEPARATOR . $file;
                $md5[$file] = $this->generateCheckSum($file);
            }
        }
        return $md5;
    }

    public function generateModuleFilesMD5s($modmanFilePath)
    {
        $modmanRawData = file_get_contents($modmanFilePath);
        $rawLines = explode("\n",$modmanRawData);

        $md5s = [];
        foreach ($rawLines as $rawLine) {
            if (
                substr($rawLine,0,1) !== '#' &&
                strlen($rawLine) > 0
            ) {
                $line = array_values(array_filter(explode(' ',$rawLine)));
                if (strpos($line[1], ".modman/") !== 0) { //ignore .modman/*
                    $md5s = array_merge($md5s,$this->filterFileCheckSum(
                        $this->generateCheckSum('./' . $line[1])
                    ));
                }
            }
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

        $newFiles = [];
        $unreadableFiles = [];
        $alteredFiles = [];

        $files = $this->generateModuleFilesMD5s($modmanFilePath);

        foreach ($ignoreList as $filePath) {
            unset($files[$filePath]);
        }

        //validating files
        foreach ($files as $fileName => $md5) {
            if (substr($fileName, -strlen('integrityCheck')) == 'integrityCheck') {
                //skip validation of integrityCheck file
                continue;
            }
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
            'alteredFiles' => $alteredFiles
        ];
    }
}