<?php
/**
 * NumericTool.php
 * 2014-12-01
 *
 * Developed by fucan <fucan@playcrab.com>
 * Copyright (c) 2014 Playcrab Corp.
 */

require_once __DIR__ . "/Classes/PHPExcel.php"; 

//导出数据的抽象接口
interface ExcelExport{
	//添加数据
	public function addCell($sheetIndex, $rowIndex, $config, $value);
	
	//导出
	public function export($dir, $settingName, $version);
}

class DefaultExcelExport implements ExcelExport{
	protected $data = array();
	private $currentIndex = NULL;
	private $rowIndex = -1;

	public function addCell($sheetIndex, $rowIndex, $config, $value){
        $type = $config['type'];
        $field = $config['field'];
		$value = $this->getTypeByValue($type, $value); 

		//增加一行新行
		if($this->currentIndex != "{$sheetIndex}-{$rowIndex}"){
			$this->rowIndex++;
            
			//print_r("{$sheetIndex}-{$rowIndex}\n");
			//print_r("rowIndex:{$this->rowIndex}\n");
			$this->currentIndex = "{$sheetIndex}-{$rowIndex}";
			$this->data[] = array();
		}
		$this->data[$this->rowIndex][$field] = $value;
	}

	/**
	 * 根据cell的类型获取value
	 * @param type
	 * @param value
	 */
	public function getTypeByValue($type, $value){
        /* 当前版本允许整型为空，那么就顺其自然以来php的xxval给出默认值吧
		if($type != 'string' && $value === ""){
			throw new Exception("Illegal type:{$type}, value:{$value}");
        }*/

		switch($type){
		case 'int':
            $value = (empty($value)) ? 0 : $value;
			return intval($value);
		case 'string':
			return strval($value);
		case 'double':
            $value = (empty($value)) ? 0 : $value;
			return floatval($value);
		case 'int[]':
		case 'double[]':
            if(empty($value)){
                return array();
            }
			return json_decode($value);
		case 'string[]':
            if(empty($value) || $value == "[]"){
                return array();
            }
			return $this->resolveStringArr($value);
        default:
			return strval($value);
		}
	} 

	//解析string数组
	private function resolveStringArr($value){
		$res = json_decode($value, TRUE);		
		if(empty($res) && !is_array($res)){
			//改变解析方式，匹配[a,b,c]这种策划配错的格式
			$res =  explode(',', substr($value, 1, strlen($value) - 2));
		}
        $finalRes = array();
        foreach($res as $ele){
            if(is_array($ele)){
                $ele = json_encode($ele);
            }
            $finalRes[] = strval($ele);
        }
        return $finalRes;
	}

	public function export($dir, $settingName, $version){
		printf("%s\n", json_encode($this->data));
	}
}

//导出php
class PhpExcelExport extends DefaultExcelExport implements ExcelExport{
    const EXPORT_DIR = "/data/work/numericexport/php";
    const EXPORT_ITEM = "/data/work/numericexport/item";
	public function addCell($sheetIndex, $rowIndex, $config, $value){
        $cs = $config['CS'];
		if($cs == 'S' || $cs == 'CS'){
			parent::addCell($sheetIndex, $rowIndex, $config, $value);
		}
	}

	public function export($dir, $settingName, $version){
		//php方式存储导出数据,这里缺乏配置可能灵活性有点低
        $exportDir = self::EXPORT_DIR;
        $itemDir = self::EXPORT_ITEM;
        $output = var_export($this->data, TRUE);
        $output = "<?php\nreturn {$output}\n?>\n";
        global $exportAll;
        if($exportAll){
            file_put_contents("{$itemDir}/{$settingName}.php", $output);
        }
        else{
            exec("mkdir -p {$exportDir}/{$dir}/{$version}");
            file_put_contents("{$exportDir}/{$dir}/{$version}/{$settingName}.php", $output);
        }
	}
}

//导出lua
class LuaExcelExport extends DefaultExcelExport implements ExcelExport{
    const EXPORT_DIR = "/data/work/numericexport/lua";
    private $handle = NULL; //打开的文件句柄
    private $id = NULL;
    private $currentIndex = NULL;
	public function addCell($sheetIndex, $rowIndex, $config, $value){
        $cs = $config['CS'];
		if($cs == 'C' || $cs == 'CS'){
            //根据外键改变指定的string
            $foreign = "";
            $constrantArr = explode(':', $config['constrant']);
            if(count($constrantArr) == 2 && $constrantArr[0] == 'FOREIGN'){
                $foreign = "\$_link__\${$constrantArr[1]}:";
            }

            $type = (isset($config['realType'])) ? $config['realType'] : $config['type'];
            $field = $config['field'];

            $value = $this->getTypeByValue($type, $value, $foreign); 

            //增加一行新行，且以id作为key而不是数组自增下标
            if($this->currentIndex != "{$sheetIndex}-{$rowIndex}"){
                $this->id = $value;
                //print_r("{$sheetIndex}-{$rowIndex}\n");
                //print_r("rowIndex:{$this->rowIndex}\n");
                $this->currentIndex = "{$sheetIndex}-{$rowIndex}";
                $this->data[$this->id] = array();
            }
            $this->data[$this->id][$field] = $value;
        }
	}

    public function getTypeByValue($type, $value, $foreign){
        if($type == 'string'){
            //尝试用array去解析
            if(substr($value, 0, 1) == '[' && substr($value, -1, 1) == ']'){
                return $this->getTypeByValue('string[]', $value, $foreign);
            }

            $jsonVar = json_decode($value, TRUE);
            if(!empty($jsonVar) && is_array($jsonVar)){
                return $jsonVar;
            }
            else{
                //替换掉tid#
                if(substr($value, 0, 4) == 'tid#'){
                    $value = substr($value, 4); 
                }
                if(!empty($value) && strtolower($value) != 'empty'){
                    $value = "{$foreign}{$value}";
                }
            }
        }    
        else if($type == 'string[]'){
            //空字符串返回默认空数组
            if(empty($value)){
                return array();
            }

            //还有这种格式
            $jsonVar = json_decode($value, TRUE);
            if(is_array($jsonVar)){
                $resArr = array();
                foreach($jsonVar as $ele){
                    if(!is_array($ele) && !empty($ele) && strtolower($ele) != 'empty'){
                        $ele = "{$foreign}{$ele}";
                    }
                    $resArr[] = $ele;
                }
                return $resArr;
            }

            //改变解析方式，匹配[a:1,b:1,c:1]这种策划配错的格式
            $explodeArr = explode(',', substr($value, 1, strlen($value) - 2));
            $resArr = array();
            $resArr1 = array();
            $resArr2 = array();

            //确认解析方式，按同一种方式来进行解析
            foreach($explodeArr as $ele){
                $jsonVar = json_decode($ele, TRUE);
                if(!empty($jsonVar) && is_array($jsonVar)){
                    $resArr[] = $jsonVar;
                }
                else{
                    //替换掉tid#
                    if(substr($ele, 0, 4) == 'tid#'){
                        $ele = substr($ele, 4); 
                    }

                    $subExplodeArr = explode(':', $ele);
                    if(count($subExplodeArr) == 2){
                        $resArr[$subExplodeArr[0]] = $subExplodeArr[1];
                    }
                    else{
                        if(!empty($ele) && strtolower($ele) != 'empty'){
                            $ele =  "{$foreign}{$ele}";
                        }
                        $resArr[] = $ele;
                    }
                }
            }

            return $resArr;
        }
        else if(!empty($foreign)){
            if($type == 'int' || $type == 'double'){
                return "{$foreign}{$value}";
            }
            else{
                $sourceArr = json_decode($value);
                $sourceArr = (empty($sourceArr)) ? array() : $sourceArr;
                $resArr = array();
                foreach($sourceArr as $ele){
                    $resArr[] = "{$ele}{$value}"; 
                }
                return $resArr;
            }
        }

        return parent::getTypeByValue($type, $value);
    }

	public function export($dir, $settingName, $version){
        //print_r($this->data);
        $exportDir = self::EXPORT_DIR;
        exec("mkdir -p {$exportDir}/{$dir}/{$version}");
        $exportPath = "{$exportDir}/{$dir}/{$version}/{$settingName}.lua";
        if(is_file($exportPath)){
            unlink($exportPath);
        }
        $this->handle = fopen($exportPath, "a+");             
        fwrite($this->handle, "local data = ");
        $count = count($this->data);
        print_r("\ncount:$count\n");
        $this->exportLuaValue($this->data, 1); 
        fwrite($this->handle, "\nreturn data");
        fclose($this->handle);
	}

    private function isAssoc($array) {
        if(is_array($array)) {
            $count = count($array);
            for($i = 0; $i < $count; $i++){
                if(!isset($array[$i])){
                    return TRUE;
                } 
            }
            return FALSE;
        }
        return FALSE;
    }

    //递归把php数组输出成lua字符串
    private function exportLuaValue($arr, $index){
        //输出hash
        if($this->isAssoc($arr) || (is_array($arr) && $index == 1)){
            fwrite($this->handle, "{");
            fwrite($this->handle, "\n");
            foreach($arr as $k => $v){
                $this->exportIndentation($index);
                //$k = str_replace("\"", "\\\"", $k); 

                //数字开头的key要写成[""]的形式
                if(substr($k, 0, 1) >= '0' && substr($k, 0, 1) <= '9'){
                    $k = "[\"{$k}\"]";
                }
                //替换掉tid#
                if(substr($k, 0, 4) == 'tid#'){
                    $k = substr($k, 4); 
                }

                fwrite($this->handle, "{$k}=");
                $this->exportLuaValue($v, $index + 1);
                fwrite($this->handle, ",");
                fwrite($this->handle, "\n");
            }
            $this->exportIndentation($index - 1);
            fwrite($this->handle, "}");
        }
        //输出数组
        else if(is_array($arr)){
            fwrite($this->handle, "{");
            fwrite($this->handle, "\n");
            foreach($arr as $k => $v){
                $this->exportIndentation($index);
                $this->exportLuaValue($v, $index + 1);
                fwrite($this->handle, ",");
            }
            $this->exportIndentation($index - 1);
            fwrite($this->handle, "}");
        }
        //输出简单类型
        else if(gettype($arr) == 'string'){
            $arr = str_replace("\"", "\\\"", $arr);
            $arr = str_replace("'", "\\'", $arr);
            $arr = str_replace("\n", "\\n", $arr);
            $arr = str_replace("\t", "\\t", $arr);
            $arr = str_replace("\r", "\\r", $arr);
            fwrite($this->handle, "\"$arr\"");
        }
        else{
            fwrite($this->handle, $arr);
        }
    }
    
    //输出缩进
    private function exportIndentation($index){
        return; //不支持缩进了
        for($i = 0; $i < $index; $i++){
            fwrite($this->handle, "\t");
        }
    }

}

//获取export实例
class ExportFactory{

}	

/**
 * 数值导出工具
 */
class NumericTool{
	const CS_ROW = 3;
	const TYPE_ROW = 4; 
	const FIELD_ROW = 5;
	const CONSTRANT_ROW = 6;
	const DATA_START_ROW = 7; 
    const CONFIGVALUE_TYPE_COL = 3;

	private $fileList = array();
	private $exportList = array();
	private $exportAll = FALSE;
	
	/**
	 * @param exportList export列表
	 */
	public function __construct($exportList){
		foreach($exportList as $type){
			$className = "{$type}ExcelExport";
			$this->exportList[] = new $className();
            global $exportAll;
            $this->exportAll = $exportAll;
		}
	}

	//excel表的字母列转为
	private function alpha2Index($str){
		$alphaArr = str_split($str, 1);	
        $colCount = 0;
        foreach($alphaArr as $alpha){
            $colCount * 26;
            $colCount += ord($alpha - 65);
		}
		return $colCount - 1;
	}

	//读取一个excel，返回到phpExcel中
	private function getPHPExcel($filePath){
		$PHPReader = new PHPExcel_Reader_Excel2007(); 
		if(!$PHPReader->canRead($filePath)){ 
            throw new exception("File {$filePath} does not exitst or not xlsx"); 
		} 
        $PHPReader->setReadDataOnly(true); 
		return $PHPReader->load($filePath);
	}

	//读取一个单元数据
	public function addCell($sheetIndex, $rowIndex, $config, $value){
		foreach($this->exportList as $export){
			$export->addCell($sheetIndex, $rowIndex, $config, $value);
		}
	}

	//输出
	private function export($filePath, $version){
		$explodeArr = explode('/', $filePath);
		$dir = $explodeArr[count($explodeArr) - 3];
		$settingName = $explodeArr[count($explodeArr) - 1];
        $explodeArr = explode('.', $settingName);
		$settingName = $explodeArr[0];
		foreach($this->exportList as $export){
			$export->export($dir, $settingName, $version);
		}
	}

	public function resolveOneExcel($filePath, $version){
        global $settingName;
		$phpExcel = self::getPHPExcel($filePath);

		$fieldConfigMap = array();

		for($sheetIndex = 0; $sheetIndex < $phpExcel->getSheetCount(); $sheetIndex++){
			$workSheet = $phpExcel->getSheet($sheetIndex);		
			$title = $workSheet->getTitle();

			//过滤掉页签起始标志为$的
			if(substr($title, 0, 1) == '$'){
				continue;
			}

			$rowCount = $workSheet->getHighestRow();
			$colCount = $this->alpha2Index($workSheet->getHighestColumn());

            print_r("rowCount:$rowCount\n");
            print_r("colCount:$colCount\n");
			//获取字段名称、类型、输出(CS)、约束
			if(empty($fieldConfigMap)){
				for($col = 1; $col <= $colCount; $col++){
					$field = trim($workSheet->getCellByColumnAndRow($col, self::FIELD_ROW)->getValue());
					$type = trim($workSheet->getCellByColumnAndRow($col, self::TYPE_ROW)->getValue());
					$CS = trim($workSheet->getCellByColumnAndRow($col, self::CS_ROW)->getValue());
                    $CS = ($this->exportAll) ? 'CS' : $CS;
                    $constrant =  trim($workSheet->getCellByColumnAndRow($col, self::CONSTRANT_ROW)->getValue());
					if(empty($field) || substr($field, 0, 1) == '$'){
						continue;			
					}
					$fieldConfigMap[$col] = array('CS'=>$CS, 'type'=>$type, 'field'=>$field, 'constrant'=>$constrant);
				}
			}

			//逐行读取数据
			for($row = self::DATA_START_ROW; $row <= $rowCount; $row++){
				$startValue = $workSheet->getCellByColumnAndRow(0, $row)->getValue();  
				
				foreach($fieldConfigMap as $colIndex => $fieldConfig){
                    //configvalue对于客户端要专门处理
                    if($settingName == 'ConfigValue' && $fieldConfig['field'] == 'content'){
                        $fieldConfig['realType'] =  $workSheet->getCellByColumnAndRow(self::CONFIGVALUE_TYPE_COL, $row)->getValue();
                    }
                    //还是客户端专门处理
                    if($fieldConfig['field'] == 'Id'){
                        $fieldConfig['realType'] =  $fieldConfig['type'];
                        $fieldConfig['type'] =  'string';
                    }
					$cell = $workSheet->getCellByColumnAndRow($colIndex, $row)->getValue();
					$this->addCell($sheetIndex, $row, $fieldConfig,  $cell); 
				}

				//已经到了最后一行
				if(strtolower($startValue) == 'end'){
					break;
				}
			}
		}
		$this->export($filePath, $version);
	}
}

$args = getopt('t:f:v:s:e:a:');
if(!isset($args['t']) || !isset($args['f']) || !isset($args['v'])){
	echo "usage:\n"; 
	echo "-t (taskId)\n";
	echo "-f (filePath)\n";
	echo "-v (version)\n";
	echo "-s (successLog)\n";
	echo "-e (errorLog)\n";
	exit(1);
}

$taskId = $args['t'];
$filePath = $args['f'];
$version = $args['v'];
$exportAll = $args['a'];
$explodeArr = explode('/', $filePath);
$dir = $explodeArr[count($explodeArr) - 3];
$settingName = $explodeArr[count($explodeArr) - 1];
$explodeArr = explode('.', $settingName);
$settingName = $explodeArr[0];


//当exportAll导出所有字段时，为gm后台导出ItemConfig和Tranlate，不用导出lua
$excelExportList = ($exportAll) ? array('Php') : array('Lua', 'Php');
$numericTool = new NumericTool(array('Lua', 'Php'), $version);
$startTime = time();

try{
	$numericTool->resolveOneExcel($filePath, $version);
}
catch(Exception $exception){
	$endTime = time();
	$consoleLog = "{$taskId} {$filePath} {$version} {$startTime} {$endTime}"; 
	//记录错误日志
	$exception_class = get_class($exception);
	$msg = $exception->getMessage();
	$file = $exception->getFile();
	$line = $exception->getLine();
	$trace = $exception->getTraceAsString();
	$errorLog =  "{$consoleLog}\n {$exception_class} [$msg]\n## $file($line): $exception_class\n$trace\n";
	if(isset($args['e'])){
		file_put_contents($args['e'], $errorLog, FILE_APPEND | LOCK_EX );
	}
	else{
		print_r($errorLog);
	}
    exit(1);
}


//记录成功日志
$endTime = time();
$successLog = "{$taskId} {$filePath} {$version} {$startTime} {$endTime}\n"; 
if(isset($args['s'])){
	file_put_contents($args['s'], $successLog, FILE_APPEND | LOCK_EX );
}
else{
	print_r($successLog);
}
