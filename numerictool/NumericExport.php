<?php
/**
 * NumericExport.php
 * 2014-12-03
 *
 * Developed by fucan <fucan@playcrab.com>
 * Copyright (c) 2014 Playcrab Corp.
 */

$res = array();
$numericRoot = '/data/work/configcheck/数值开发';
$rootDir = opendir($numericRoot);

//读取所有的目录文件
while($file = readdir($rootDir)){
	$filePath = "{$numericRoot}/{$file}";
	if(is_file($filePath) && end(explode('.', $filePath)) == 'xlsx'){
		$cmd = "php NumericTool.php -t 1 -f {$filePath} -v 100 -s ~/success.log -e ~/error.log 2>&1";
		print_r($cmd);
        if(!empty($output)){
            unset($output);
        }
		exec($cmd, $output, $res);		
        foreach($output as $line){
            print_r("$line\n");
        }
        break;
	}
}
return $res;




