<?php
/**
 * Created by PhpStorm.
 * User: ian
 * Date: 24/07/18
 * Time: 12:35
 */

class IntegrityEngine
{
    public function dirCheckSum($dir)
    {
        $files = scandir($dir);
        $md5 = [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file = $dir . DIRECTORY_SEPARATOR . $file;

                $checkMethod = 'fileCheckSum';
                if (is_dir($file)) {
                    $checkMethod = 'dirCheckSum';
                }

                $md5[$file] = $this->$checkMethod($file);
            }
        }
        return $md5;
    }

    public function generateModuleFilesMD5s($modmanFilePath) {

        $modmanRawData = file_get_contents($modmanFilePath);

        $rawLines = explode("\n",$modmanRawData);
        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (
                substr($rawLine,0,1) === '#' ||
                strlen($rawLine) === 0
            ) {
                continue;
            }
            $lines[] = array_values(array_filter(explode(' ',$rawLine)));
        }
        foreach ($lines as $index => $line) {
            $elementName = './' . $line[1];
            $checkMethod = 'fileCheckSum';
            if (is_dir($elementName)) {
                $checkMethod = 'dirCheckSum';
            }
            $lines[$index][] = $this->filterFileCheckSum($this->$checkMethod($elementName));
        }
        $files = [];
        foreach($lines as $line) {
            $files = array_merge($files,end($line));
        }

        //removing modman base files from generated hashs.
        foreach($files as $filePath => $md5) {
            if (strpos($filePath,'./.modman/') !== false) {
                unset($files[$filePath]);
            }
        }

        return $files;
    }

    public function filterFileCheckSum($checkSumArray)
    {
        if(count($checkSumArray) === 1) {
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

    public function filterFileMd5($pathArray)
    {
        $files = [];
        foreach ($pathArray as $path => $md5) {
            if (!is_dir($path)) {
                $files[$path] = $md5;
            }
            else {
                $files = array_merge($files,$this->filterFileMd5($path));
            }
        }
        return $files;
    }

    public function fileCheckSum($file)
    {
        return  [
            $file => file_exists($file) ? md5_file($file) : false
        ];
    }
}