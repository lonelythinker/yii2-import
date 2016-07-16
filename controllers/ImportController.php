<?php

namespace lonelythinker\yii2\import\controllers;

use backend\controllers\BaseController;
/**
 * Import Controller
 *
 * @author : lonelythinker
 * @email : 710366112@qq.com
 * @homepage : www.lonelythinker.cn
 * @date：2016年4月25日
 *
 */
class ImportController extends BaseController
{
    public function actionImport()
    {
    	$basePath = dirname(Yii::$app->basePath).'/uploads/tmp/';
    	$fileName = 'ccc.csv';
    	$this->generateData($basePath.$fileName);
    	$importFiledsData = $this->generateData($basePath.$fileName);
    	return $this->batchImportToDb('photoshoot', $importFiledsData['importFileds'], $importFiledsData['importData']);
//     	$model = new ImportForm();
    	
//     	if (Yii::$app->request->isPost) {
//     		$model->importFile = UploadedFile::getInstance($model, 'imageFile');
//     		if ($model->upload()) {
//     			// 文件上传成功
//     			return;
//     		}
//     	}
    	
    }
   
    /**
     * 从上传的文件中读取数据
     * @param string $filePath
     * @param string $fileType
     */
    private function generateData($filePath, $fileType = 'CSV'){
    	switch (strtoupper($fileType)){
    		case 'CSV':
    			return $this->generateCSVData($filePath);
    			break;
    		default:
    			break;
    	}
    }
    
    /**
     * 从上传的CSV文件中读取数据
     * @param string $filePath
     */
    private function generateCSVData($filePath){
    	$reader = \PHPExcel_IOFactory::createReader('CSV')
    	->setDelimiter(',')
    	->setEnclosure('"')
    	->setSheetIndex(0);
    	$objCSV = $reader->load($filePath);
    	//选择标签页
    	$sheet = $objCSV->getSheet(0);
    	//获取行数与列数
    	$rowNum = $sheet->getHighestRow();
    	$columnNum = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());
    	
    	//有用列数数组
    	$columns = [];
    	//取得表格中的第一行
        	$fields = [];
    	for($i=0; $i<$columnNum; $i++){
    		$cellName = \PHPExcel_Cell::stringFromColumnIndex($i).'1';
    		$cellVal = trim($sheet->getCell($cellName)->getValue());//取得列内容
    		if("#" == $cellVal || "" == $cellVal){
    			continue;
    		}
    		$columns[] = $i;
    		$fields[]= 1 == count($fkCells = explode('-', $cellVal))? \yii\helpers\Inflector::camel2id($cellVal, '_') : lcfirst($fkCells[0]) . '-' . \yii\helpers\Inflector::camel2id($fkCells[1], '_');
    	}
    	
    	//从第二行开始取出数据并存入数组
    	$data = [];
    	for($i=2; $i<=$rowNum; $i++){
    		$row = [];
    		$columnIsNull = true;//该列是否是空列
    		foreach ($columns as $j){
    			$cellName = \PHPExcel_Cell::stringFromColumnIndex($j).$i;
    			$cellVal = trim($sheet->getCell($cellName)->getValue());
	    		if($cellVal){
	    			$columnIsNull = false;
	    		}
    			$row[] = $cellVal;
    		}
    		if($columnIsNull){//空列停止
    			break;
    		}
    		$data[]= $row;
    	}
    	
    	return ['importFileds' => $fields, 'importData' => $data];
    }
    
    /**
     *
     * @param string $tableName
     * @param array $importFileds
     * @param array $importData
     */
    private function batchImportToDb($tableName , $importFileds, $importData){
    	$affectedRowCount = 0;
    	if(!empty($importFileds) && !empty($importData)){
    		$configParams = Yii::$app->params[$tableName];
    		$language = null;//导入数据的语言
    		$baseTmpPath = dirname(Yii::$app->basePath).'/uploads/tmp/';
    		$baseUploadPath = dirname(Yii::$app->basePath).'/uploads/';
	    	$insertOrUpdate = 'insert';
	    	$db = Yii::$app->get('db', false);
	    	$table = $db->getTableSchema($tableName);
	    	foreach ($importFileds as $key => $field){
	    		$pos = strpos($field, '-');
	    		if(false === $pos && $table->columns[$field]->isPrimaryKey){
	    			$insertOrUpdate = 'update';
	    		}elseif(false !== $pos){
	    			$fkColumns[] = $key;//外键关联列
	    			unset($importFileds[$key]);
	    		}elseif($table->columns[$field]->type === 'smallint'){
	    			$dicColumns[] = $key;//字典列
	    		}elseif($table->columns[$field]->type === 'string' && (\yii\helpers\StringHelper::endsWith($table->columns[$field]->name, '_mult_img') || \yii\helpers\StringHelper::endsWith($table->columns[$field]->name, '_img') || \yii\helpers\StringHelper::endsWith($table->columns[$field]->name, '_mult_file') || \yii\helpers\StringHelper::endsWith($table->columns[$field]->name, '_file'))){
	    			$fileColumns[] = $key;//文件列
	    		}
	    	}
	    	if('insert' == $insertOrUpdate){
	    		if(isset($table->columns['created_at']) && !in_array('created_at', array_values($importFileds))){
		    		$importFileds[] = 'created_at';
	    		}
	    		if(isset($table->columns['updated_at']) && !in_array('updated_at', array_values($importFileds))){
		    		$importFileds[] = 'updated_at';
	    		}
	    	}elseif(isset($table->columns['updated_at']) && !in_array('updated_at', array_values($importFileds))){
	    		$importFileds[] = 'updated_at';
	    	}
	    	
	    	foreach ($importData as $key => $importRowData){
	    		foreach ($importRowData as $rowKey => $item){
	    			if(isset($fkColumns) && in_array($rowKey, $fkColumns)){
	    				unset($importRowData[$rowKey]);
	    			}elseif(isset($dicColumns) && in_array($rowKey, $dicColumns)){//字典列
	    				if(!empty($language)){
	    					if(false !== ($dicNum = array_search($importRowData[$rowKey], $configParams[$importFileds[$rowKey]][$language]))){
	    						$importRowData[$rowKey] = $dicNum;
	    					}
	    				}else{
	    					foreach (array_keys($configParams[$importFileds[$rowKey]]) as $langValue){
	    						if(false !== ($dicNum = array_search($importRowData[$rowKey], $configParams[$importFileds[$rowKey]][$langValue]))){
	    							$language = $langValue;
	    							$importRowData[$rowKey] = $dicNum;
	    							break;
	    						}
	    					}
	    				}
	    			}elseif(isset($fileColumns) && in_array($rowKey, $fileColumns)){//文件列
	    				if(false !== strpos($importRowData[$rowKey], ',')){
	    					$fileNames =  explode(',', $importRowData[$rowKey]);
	    				}else{
	    					$fileNames = [$importRowData[$rowKey]];
	    				}
	    				foreach ($fileNames as $fileKey => $fileName){
	    					if(!file_exists($baseUploadPath.$fileName)){
	    						if(file_exists($baseTmpPath.$fileName)){
	    							$newFileName = uniqid().strrchr($fileName, '.');
	    							if(@copy($baseTmpPath.$fileName, $baseUploadPath.$newFileName)){
	    								$fileNames[$fileKey] = $newFileName;
// 	    								unlink($baseTmpPath.$fileName);//删除旧文件
	    							}else{
	    								unset($fileNames[$fileKey]);
	    							}
	    						}else{
	    							unset($fileNames[$fileKey]);
	    						}
	    					}
	    				}
	    				$importRowData[$rowKey] = implode($fileNames, ',');
	    			}
	    		}
	    		if('insert' == $insertOrUpdate){
	    			if(isset($table->columns['created_at']) && in_array('created_at', array_values($importFileds))){
	    				$importRowData[] = time();
	    			}
	    			if(isset($table->columns['updated_at']) && in_array('updated_at', array_values($importFileds))){
	    				$importRowData[] = time();
	    			}
	    		}elseif(isset($table->columns['updated_at']) && in_array('updated_at', array_values($importFileds))){
	    			$importRowData[] = time();
	    		}
	    		$batchImportData[] = $importRowData;
	    		if(($key>0 && $key/2000 == 0) || $key == count($importData)-1){//每次处理2000条
	    			if('insert' == $insertOrUpdate){
	    				$affectedRowCount += Yii::$app->db->createCommand()->batchInsert($tableName, $importFileds, $batchImportData)->execute();
	    			}else{
    					$rows = Yii::$app->db->createCommand()->batchUpdate($tableName, $importfields, $batchImportData)->execute();
	    			}
	    			$batchImportData = [];
	    		}
	    	}
    	}
    	 
    	return ['affectedRowCount' => $affectedRowCount];
    }
}
