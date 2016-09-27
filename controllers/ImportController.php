<?php

namespace backend\controllers;

use Yii;
use backend\controllers\BaseController;
use backend\models\forms\ImportForm;
use yii\web\UploadedFile;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

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
	public $language = null;//导入数据的语言
	
    public function actionImport()
    {
    	$request = Yii::$app->request;
    	if ($request->isPost && ($tableName = $request->post('tableName'))) {
	    	$model = new ImportForm();
	    	$model->importFolderZip = UploadedFile::getInstanceByName('importFolderZip');
    		$model->importFile = UploadedFile::getInstanceByName('importFile');
    		if ($model->upload()) {
    			// 文件上传成功
    			$basePath = dirname(Yii::$app->basePath).'/uploads/tmp/';
    			if($model->importFolderZip){
    				\common\helpers\ZipHelper::unZipDir($basePath . $model->importFolderZip->name, $basePath);
    			}
		    	$importFiledsData = $this->generateData($tableName, $basePath . $model->importFile->name);
		    	try {
		    		$importResult = $this->batchImportToDb($tableName, $importFiledsData['importFileds'], $importFiledsData['importData']);
		    		Yii::$app->getSession()->setFlash('success', '成功导入数据 ' . $importResult['affectedRowCount'].' 条');
		    	} catch (\yii\db\Exception $e) {
		    		Yii::$app->getSession()->setFlash('error', '导入数据失败：' .  json_encode($e->errorInfo));
		    	}
		    	$this->redirect(Yii::$app->request->getReferrer());
    		}else{
    			$errors = $model->getErrors();
    			$errorStr = '';
    			foreach ($errors as $key => $error){
    				$errorStr .= "[$key] " . implode(' ', $error);
    			}
    			Yii::$app->getSession()->setFlash('error', '导入数据失败：' . $errorStr);
    			$this->redirect(Yii::$app->request->getReferrer());
    		}
    	}
    }
   
    /**
     * 从上传的文件中读取数据
     * @param string $tableName
     * @param string $filePath
     * @param string $fileType
     */
    private function generateData($tableName, $filePath, $fileType = 'CSV'){
    	switch (strtoupper($fileType)){
    		case 'CSV':
    			return $this->generateCSVData($tableName, $filePath);
    			break;
    		default:
    			break;
    	}
    }
    
    /**
     * 从上传的CSV文件中读取数据
     * @param string $tableName
     * @param string $filePath
     */
    private function generateCSVData($tableName, $filePath){
    	if (file_exists($filePath) && is_readable($filePath)) {
    		if(false !== ($fileHandle = fopen($filePath, 'r'))){
    			while (($rowData = fgetcsv($fileHandle, 0, ',')) !== false){
    				foreach ($rowData as $rowDatum){
    					$encoding = \common\helpers\StringHelper::getCharset($rowDatum);
    					if($encoding){
    						break;
    					}
    				}
    			}
    			fclose($fileHandle);
    		}
    	}
    	
    	$reader = \PHPExcel_IOFactory::createReader('CSV')
    	->setDelimiter(',')
    	->setEnclosure('')
    	->setInputEncoding($encoding)
    	->setSheetIndex(0);
    	$objCSV = $reader->load($filePath);
    	//选择标签页
    	$sheet = $objCSV->getSheet(0);
    	//获取列数
    	$columnNum = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());
    	
    	//获取表字段的中文到英文的对照数组
    	$db = Yii::$app->get('db', false);
	    $table = $db->getTableSchema($tableName);
	    $comments = [];
	    foreach ($table->columns as $key => $column){
	    	if(!empty($column->comment)){
	    		if($column->type === 'smallint'){//字典
	    			$comments[preg_replace('/\[.*\].*/', '', preg_replace('/\s(?=\s)/', '', trim($column->comment)))] = Inflector::camel2id($column->name, '_');
	    		}else{
	    			$zh_key = preg_replace('/\s(?=\s)/', '', trim($column->comment));
		    		$comments[$zh_key] = Inflector::camel2id($column->name, '_');
		    		if(StringHelper::endsWith($zh_key, "标识") && ($zh_key_len = StringHelper::byteLength($zh_key)) > ($zh_id_len = StringHelper::byteLength("标识"))){
		    			$comments[StringHelper::byteSubstr($zh_key, 0, $zh_key_len - $zh_id_len)] = substr_compare($comments[$zh_key], '_id', -3, 3, true) === 0 ? substr($comments[$zh_key], 0, -3) : $comments[$zh_key];;
		    		}
	    		}
	    	}
	    }
	    $fkParams = !empty(Yii::$app->params[$tableName]['fk']) ? Yii::$app->params[$tableName]['fk'] : null;
	    if(!empty($fkParams) && is_array($fkParams)){
	    	foreach ($fkParams as $tbName => $fkParam){
	    		if(!empty($fkParam['fkColumns']) && !empty($fkParam['fkColumns'][0])){
	    			$refTableSchema = $db->getTableSchema($tbName);
	    			if(!empty($refTableSchema->columns[$fkParam['fkColumns'][0]]->comment)){
		    			$comments[preg_replace('/\s(?=\s)/', '', trim($refTableSchema->columns[$fkParam['fkColumns'][0]]->comment))] = Inflector::camel2id($refTableSchema->columns[$fkParam['fkColumns'][0]]->name, '_');
	    			}
	    		}
	    	}
	    }
	    
    	//有用列数数组
    	$columns = [];
    	//取得表格中的第一行
        $fields = [];
    	for($i=0; $i<$columnNum; $i++){
    		$cellName = \PHPExcel_Cell::stringFromColumnIndex($i).'1';
    		$cellVal = trim($sheet->getCell($cellName)->getValue());//取得列内容
    		$cellVal = preg_replace('/\s+/', '', $cellVal);
    		if("#" == $cellVal || "" == $cellVal){
    			continue;
    		}
    		$columns[] = $i;
    		if(mb_strlen($cellVal) === strlen($cellVal)){//英文
    			$fields[]= 1 == count($fkCells = explode('-', $cellVal))? Inflector::camel2id($cellVal, '_') : Inflector::camel2id($fkCells[0], '_') . '-' . Inflector::camel2id($fkCells[1], '_');
    		}else{
    			$fields[]= 1 == count($fkCells = explode('-', $cellVal))? $comments[$cellVal] : $comments[$fkCells[0]] . '-'  . $comments[$fkCells[1]];
    		}
    	}
    	
    	//从第二行开始取出数据并存入数组
    	$data = [];
    	for($i=2;; $i++){
    		$row = [];
    		$columnIsNull = true;//该列是否是空列
    		foreach ($columns as $j){
    			$cellName = \PHPExcel_Cell::stringFromColumnIndex($j) . $i;
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
    		$configParams = isset(Yii::$app->params[$tableName]) ? Yii::$app->params[$tableName] : [];
    		$language = null;//导入数据的语言
    		$baseTmpPath = dirname(Yii::$app->basePath).'/uploads/tmp/';
    		$baseUploadPath = dirname(Yii::$app->basePath).'/uploads/imgs/';
	    	$insertOrUpdate = 'insert';
	    	$idAttr = null;//主键字段
	    	$db = Yii::$app->get('db', false);
	    	$table = $db->getTableSchema($tableName);
	    	$relations = [];
	    	foreach ($table->foreignKeys as $refs){
	    		$refTable = $refs[0];
	    		unset($refs[0]);
	    		$fks = array_keys($refs);
	    		$relations[$fks[0]] = ['table'=> $refTable, 'column' => $refs[$fks[0]]];
	    	}
	    	
	    	foreach ($importFileds as $key => $field){
	    		$pos = strpos($field, '-');
	    		if(false === $pos && $table->columns[$field]->isPrimaryKey){
	    			$idAttr = $field;
	    			$insertOrUpdate = 'update';
	    		}elseif(false !== $pos){
	    			$fkColumns[$key] = $field;//外键关联列key=>$field
	    			if(2 == count($fkRelation = explode('-', $field))){
	    				if(isset($relations[$fkRelation[0]]) && !isset(array_flip($importFileds)[$fkRelation[0]])){//外键不含_id
	    					$importFileds[] = $fkRelation[0];
	    				}elseif(isset($relations[$fkRelation[0].'_id']) && !isset(array_flip($importFileds)[$fkRelation[0].'_id'])){
	    					$importFileds[] = $fkRelation[0].'_id';
	    				}
	    			}
	    			unset($importFileds[$key]);
	    		}elseif($table->columns[$field]->type === 'smallint'){
	    			$dicColumns[] = $key;//字典列
	    		}elseif($table->columns[$field]->type === 'string' && (StringHelper::endsWith($table->columns[$field]->name, '_imgs') || StringHelper::endsWith($table->columns[$field]->name, '_img') || StringHelper::endsWith($table->columns[$field]->name, '_files') || StringHelper::endsWith($table->columns[$field]->name, '_file'))){
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
	    	
	    	$transaction = $db->beginTransaction();
	    	try {
				foreach ($importData as $key => $importRowData){
					foreach ($importRowData as $rowKey => $item){
						if(isset($fkColumns) && in_array($rowKey, array_keys($fkColumns))){//外键关联列
							if(2 == count($fkRelation = explode('-', $fkColumns[$rowKey]))){
								if(isset($relations[$fkRelation[0]]) && (isset(array_flip($importFileds)[$fkRelation[0]]) && empty($importRowData[array_flip($importFileds)[$fkRelation[0]]])) && !empty($importRowData[$rowKey]) && isset($relations[$fkRelation[0]])){//外键不含_id
									$row = (new \yii\db\Query())
										->select([$relations[$fkRelation[0]]['column']])
										->from($relations[$fkRelation[0]]['table'])
										->where([$fkRelation[1] => $importRowData[$rowKey]])
										->one();
									if(isset($row[$relations[$fkRelation[0]]['column']])){
										$importRowData[array_flip($importFileds)[$fkRelation[0]]] = $row[$relations[$fkRelation[0]]['column']];
									}
								}elseif(isset($relations[$fkRelation[0].'_id']) && (isset(array_flip($importFileds)[$fkRelation[0].'_id']) && empty($importRowData[array_flip($importFileds)[$fkRelation[0].'_id']])) && !empty($importRowData[$rowKey]) && isset($relations[$fkRelation[0].'_id'])){
									$row = (new \yii\db\Query())
										->select([$relations[$fkRelation[0].'_id']['column']])
										->from($relations[$fkRelation[0].'_id']['table'])
										->where([$fkRelation[1] => $importRowData[$rowKey]])
										->one();
									if(isset($row[$relations[$fkRelation[0].'_id']['column']])){
										$importRowData[array_flip($importFileds)[$fkRelation[0].'_id']] = $row[$relations[$fkRelation[0].'_id']['column']];
									}
								}else{
									$importRowData[array_flip($importFileds)[$fkRelation[0].'_id']] = null;
								}
							}
							unset($importRowData[$rowKey]);
						}elseif(isset($dicColumns) && in_array($rowKey, $dicColumns) && !empty($configParams)){//字典列
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
							if((!isset($importRowData[$rowKey]) || !is_numeric($importRowData[$rowKey])) && $table->columns[$importFileds[$rowKey]]->type === 'smallint'){
								$importRowData[$rowKey] = $table->columns[$importFileds[$rowKey]]->defaultValue;
							}
						}elseif(isset($fileColumns) && in_array($rowKey, $fileColumns)){//文件列
							if(false !== strpos($importRowData[$rowKey], '|')){
								$fileNames =  explode('|', $importRowData[$rowKey]);
							}else{
								$fileNames = [$importRowData[$rowKey]];
							}
							foreach ($fileNames as $fileKey => $fileName){
								if(!file_exists($baseUploadPath . $fileName)){
									if(file_exists($baseTmpPath . $fileName)){
										$newFileName = uniqid().strrchr($fileName, '.');
										if(@copy($baseTmpPath . $fileName, $baseUploadPath . $newFileName)){
											$fileNames[$fileKey] = $newFileName;
	// 	    								unlink($baseTmpPath . $fileName);//删除旧文件
										}else{
											unset($fileNames[$fileKey]);
										}
									}else{
										unset($fileNames[$fileKey]);
									}
								}
							}
							$importRowData[$rowKey] = implode($fileNames, '|');
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
					if('insert' == $insertOrUpdate){
						$batchInsertImportData[] = $importRowData;
						if(($key>0 && $key/2000 == 0) || $key == count($importData)-1){//每次处理2000条
							$affectedRowCount += Yii::$app->db->createCommand()->batchInsert($tableName, $importFileds, $batchInsertImportData)->execute();
							$batchInsertImportData = [];
						}
					}else{
						$idValue = null;//主键字段的值
						$updateColumns = null;
						foreach ($importFileds as $key => $importfield){
							if($idAttr && $idAttr == $importfield){
								$idValue = $importRowData[$key];
							}else{
								$updateColumns[$importfield] = $importRowData[$key];
							}
						}
						if($idAttr && $idValue && $updateColumns){
							$affectedRowCount += Yii::$app->db->createCommand()->update($tableName, $updateColumns, [$idAttr => $idValue])->execute();
						}
					}
				}	    		
	    		$transaction->commit();
	    	} catch (Exception $e) {
	    		$transaction->rollBack();
	    	}
    	}
    	 
    	return ['affectedRowCount' => $affectedRowCount];
    }
}
