<?php
/**
 * 
 * @author : lonelythinker
 * @email : 710366112@qq.com
 * @homepage : www.lonelythinker.cn
 * @date：2016年4月23日
 *
 */

use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/**
 * @var array $options
 * @var array $batchToggle
 * @var array $columnSelector
 * @var array $selectedColumns
 * @var array $menuOptions
 */

$label = ArrayHelper::remove($options, 'label');
$icon = ArrayHelper::remove($options, 'icon');
$showToggle = ArrayHelper::remove($batchToggle, 'show', true);
if (!empty($icon)) {
    $label = $icon . ' ' . $label;
}
echo Html::beginTag('div', ['class' => 'btn-group', 'role' => 'group']);
echo Html::button($label . ' <span class="caret"></span>', $options);
echo Html::beginTag('ul', $menuOptions);
?>

<?php if ($showToggle): ?>
    <?php
    $toggleOptions = ArrayHelper::remove($batchToggle, 'options', []);
    $toggleLabel = ArrayHelper::remove($batchToggle, 'label', Yii::t('ltimport', 'Toggle All'));
    Html::addCssClass($toggleOptions, 'lt-toggle-all');
    ?>
    <li>
        <div class="checkbox">
            <label>
                <?= Html::checkbox('import_columns_toggle', true) ?>
                <?= Html::tag('span', $toggleLabel, $toggleOptions) ?>
            </label>
        </div>
    </li>
    <li class="divider"></li>
<?php endif; ?>

<?php
foreach ($columnSelector as $value => $label) {
    $checked = in_array($value, $selectedColumns);
    echo '<li><div class="checkbox">' . '<label>' .
        Html::checkbox('import_columns_selector[]', $checked, ['data-key' => $value]) .
        "\n" . $label . '</label></div></li>';
}
echo Html::endTag('ul');
echo Html::endTag('div');
?>
