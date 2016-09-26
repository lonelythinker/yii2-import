<?php
namespace lonelythinker\yii2\import;

use Yii;
use yii\grid\GridView;
use yii\bootstrap\ButtonDropdown;
use yii\bootstrap\Modal;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Import menu widget.
 *
 * @author : lonelythinker
 * @email : 710366112@qq.com
 * @homepage : www.lonelythinker.cn
 * @date：2016年4月23日
 *
 */
class ImportMenu extends GridView
{
    /**
     * Import formats
     */
    const FORMAT_HTML = 'HTML';
    const FORMAT_CSV = 'CSV';
    const FORMAT_TEXT = 'TXT';
    const FORMAT_PDF = 'PDF';
    const FORMAT_EXCEL = 'Excel5';
    const FORMAT_EXCEL_X = 'Excel2007';
    
    /**
     * @var array the HTML attributes for the import button menu. Applicable only if `asDropdown` is set to `true`. The
     *     following special options are available:
     * - label: string, defaults to empty string
     * - icon: string, defaults to `<i class="glyphicon glyphicon-import"></i>`
     * - title: string, defaults to `Import data in selected format`.
     * - menuOptions: array, the HTML attributes for the dropdown menu.
     * - itemsBefore: array, any additional items that will be merged/prepended before with the import dropdown list.
     *     This should be similar to the `items` property as supported by `\yii\bootstrap\ButtonDropdown` widget. Note
     *     the page import items will be automatically generated based on settings in the `importConfig` property.
     * - itemsAfter: array, any additional items that will be merged/appended after with the import dropdown list. This
     *     should be similar to the `items` property as supported by `\yii\bootstrap\ButtonDropdown` widget. Note the
     *     page import items will be automatically generated based on settings in the `importConfig` property.
     */
    public $dropdownOptions = ['class' => 'btn btn-default'];
    
    /**
     * @var array, HTML attributes for the container to wrap the widget. Defaults to ['class'=>'btn-group',
     *     'role'=>'group']
     */
    public $container = ['class' => 'btn-group', 'role' => 'group'];
    
    /**
     * @var array the import configuration. The array keys must be the one of the `format` constants (CSV, HTML, TEXT,
     *     EXCEL, PDF) and the array value is a configuration array consisting of these settings:
     * - label: string, the label for the import format menu item displayed
     * - icon: string, the glyphicon or font-awesome name suffix to be displayed before the import menu item label. If
     *     set to an empty string, this will not be displayed.
     * - iconOptions: array, HTML attributes for import menu icon.
     * - linkOptions: array, HTML attributes for each import item link.
     * - options: array, HTML attributes for the import menu item.
     */
    public $importConfig = [];
    
    /**
     * @var string the table to import
     */
    public $tableName = '';
    
    /**
     * @var boolean has imgs to upload or not
     */
    public $uploadZipImgs = false;    
    
    /**
     * @var BaseDataProvider the modified data provider for usage with import.
     */
    protected $_provider;
    
    /**
     * @var array the default import configuration
     */
    
    protected $_defaultImportConfig = [];
    
    public function init()
    {
    	parent::init();
    }
    
    public function run()
    {
    	$this->initImport();
    	$this->setDefaultImportConfig();
    	$this->importConfig = ArrayHelper::merge($this->_defaultImportConfig, $this->importConfig);
    	$this->renderImportMenu();
    	$this->renderImportUpload();
    }
    

    /**
     * Initializes import settings
     *
     * @return void
     */
    public function initImport()
    {
    	$this->_provider = clone($this->dataProvider);
    }
    
    /**
     * Renders the import menu
     *
     * @return string the import menu markup
     */
    public function renderImportMenu()
    {
    	$items = [];
    	foreach ($this->importConfig as $format => $settings) {
    		if (empty($settings) || $settings === false) {
    			continue;
    		}
    		$label = '';
    		if (!empty($settings['icon'])) {
    			$css = 'glyphicon glyphicon-';
    			$iconOptions = ArrayHelper::getValue($settings, 'iconOptions', []);
    			Html::addCssClass($iconOptions, $css . $settings['icon']);
    			$label = Html::tag('i', '', $iconOptions) . ' ';
    		}
    		if (!empty($settings['label'])) {
    			$label .= $settings['label'];
    		}
    		$fmt = strtolower($format);
    		$linkOptions = ArrayHelper::getValue($settings, 'linkOptions', []);
    		$linkOptions['id'] = $this->options['id'] . '-' . $fmt;
    		$linkOptions['data-format'] = $format;
    		$linkOptions['data-toggle'] = "modal";
    		$linkOptions['data-target'] = "#import-modal";
    		$options = ArrayHelper::getValue($settings, 'options', []);
    		Html::addCssClass($linkOptions, "import-{$fmt}");
			$items[] = [
    			'label' => $label,
    			'url' => '#',
    			'linkOptions' => $linkOptions,
    			'options' => $options,
    		];
    	}
		$icon = ArrayHelper::remove($this->dropdownOptions, 'icon', '<i class="glyphicon glyphicon-import"></i>');
		$label = ArrayHelper::remove($this->dropdownOptions, 'label', '');
		$label = empty($label) ? $icon : $icon . ' ' . $label;
		if (empty($this->dropdownOptions['title'])) {
			$this->dropdownOptions['title'] = Yii::t('ltimport', 'Import data in selected format');
		}
		$menuOptions = ArrayHelper::remove($this->dropdownOptions, 'menuOptions', []);
		$itemsBefore = ArrayHelper::remove($this->dropdownOptions, 'itemsBefore', []);
		$itemsAfter = ArrayHelper::remove($this->dropdownOptions, 'itemsAfter', []);
		$items = ArrayHelper::merge($itemsBefore, $items, $itemsAfter);
// 		$content = strtr($this->template, [
// 				'{menu}' => ButtonDropdown::widget([
// 						'label' => $label,
// 						'dropdown' => ['items' => $items, 'encodeLabels' => false, 'options' => $menuOptions],
// 						'options' => $this->dropdownOptions,
// 						'encodeLabel' => false,
// 				]),
// 				'{columns}' => $this->renderColumnSelector(),
// 		]) . "\n" ;
		$content = ButtonDropdown::widget([
				'label' => $label,
				'dropdown' => ['items' => $items, 'encodeLabels' => false, 'options' => $menuOptions],
				'options' => $this->dropdownOptions,
				'encodeLabel' => false,
		]) . "\n";
		echo Html::tag('div', $content, $this->container);
    }
    
    /**
     * Renders the import menu
     *
     * @return string the import menu markup
     */
    public function renderImportUpload(){
    	$modal = Modal::begin([
    			'options' => ['id' => "import-modal",],
    			'header' => '<h2>'.Yii::t('ltimport', 'Import data').'</h2>',
    			'footer' => '<button type="button" class="btn btn-default pull-right" data-dismiss="modal">Close</button>',
    			]);
    	$form = ActiveForm::begin(['action' => ['import/import'], 'layout' => 'horizontal', 'options' => ['enctype' => 'multipart/form-data']]);
    	echo Html::hiddenInput('tableName', $this->tableName);
    	if($this->uploadZipImgs){
    		echo Html::label(Yii::t('ltimport', 'Import Zip File for imgs')).'<br>';
	    	echo \kartik\widgets\FileInput::widget([
			    'name' => 'importImgFolderZip', 
			    'options' => [
			    	'multiple' => false,
			    ], 
			    'pluginOptions' => ['previewFileType' => 'any',]
			]).'<br><br>';
    	}
    	echo Html::label(Yii::t('ltimport', 'Import data in selected format')).'<br>';
		echo \kartik\widgets\FileInput::widget([
				'name' => 'importFile',
				'options' => [
						'multiple' => false,
				],
				'pluginOptions' => ['previewFileType' => 'any',]
		]).'<br><br>';
    	$form::end();
    	Modal::end();
    }    
    /**
     * Sets the default import configuration
     *
     * @return void
     */
    protected function setDefaultImportConfig()
    {
        $this->_defaultImportConfig = [
            self::FORMAT_HTML => [
                'label' => Yii::t('ltimport', 'HTML'),
                'icon' => 'floppy-saved',
                'iconOptions' => ['class' => 'text-info'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Hyper Text Markup Language')],
            ],
            self::FORMAT_CSV => [
                'label' => Yii::t('ltimport', 'CSV'),
                'icon' => 'floppy-open',
                'iconOptions' => ['class' => 'text-primary'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Comma Separated Values')],
            ],
            self::FORMAT_TEXT => [
                'label' => Yii::t('ltimport', 'Text'),
                'icon' => 'floppy-save',
                'iconOptions' => ['class' => 'text-muted'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Tab Delimited Text')],
            ],
            self::FORMAT_PDF => [
                'label' => Yii::t('ltimport', 'PDF'),
                'icon' => 'floppy-disk',
                'iconOptions' => ['class' => 'text-danger'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Portable Document Format')],
            ],
            self::FORMAT_EXCEL => [
                'label' => Yii::t('ltimport', 'Excel 95 +'),
                'icon' => 'floppy-remove',
                'iconOptions' => ['class' => 'text-success'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Microsoft Excel 95+ (xls)')],
            ],
            self::FORMAT_EXCEL_X => [
                'label' => Yii::t('ltimport', 'Excel 2007+'),
                'icon' => 'floppy-remove',
                'iconOptions' => ['class' => 'text-success'],
                'linkOptions' => [],
                'options' => ['title' => Yii::t('ltimport', 'Microsoft Excel 2007+ (xlsx)')],
            ],
        ];
    }
}
