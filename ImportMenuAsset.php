<?php

/**
 * @copyright Copyright &copy; Kartik Visweswaran, Krajee.com, 2015 - 2016
 * @package yii2-import
 * @version 1.2.5
 */

namespace lonelythinker\yii2\import;

use kartik\base\AssetBundle;

/**
 * Asset bundle for ExportMenu Widget (for import menu data)
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @since 1.0
 */
class ImportMenuAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->setSourcePath(__DIR__ . '/assets');
        $this->setupAssets('js', ['js/lt-import-data']);
        $this->setupAssets('css', ['css/lt-import-data']);
        parent::init();
    }
}
