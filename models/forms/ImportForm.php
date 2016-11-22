<?php
namespace lonelythinker\yii2\import\models\forms;

use Yii;
use yii\base\Model;

/**
 * Import form
 */
class ImportForm extends Model
{
    /**
     * @var importFolderZip
     */
    public $importFolderZip;
    /**
     * @var ImportedFile
     */
    public $importFile;    
    
    public function rules()
    {
    	return [
    			[['importFolderZip', 'importFile'], 'file'],
            	[['importFile'], 'required'],
            	['importFolderZip', 'file', 'mimeTypes' =>'application/zip', 'maxSize' => 1024*1024*100],
            	['importFile', 'file', 'mimeTypes' =>'text/*', 'maxSize' => 1024*1024*5]
    	];
    }
    
    public function upload()
    {
    	if ($this->validate()) {
    		$basePath = dirname(Yii::$app->basePath).'/uploads/tmp/';
    		if($this->importFolderZip){
    			$this->importFolderZip->saveAs($basePath . $this->importFolderZip->baseName . '.' . $this->importFolderZip->extension);
    		}
    		$this->importFile->saveAs($basePath . $this->importFile->baseName . '.' . $this->importFile->extension);
    		return true;
    	} else {
    		return false;
    	}
    }
}
