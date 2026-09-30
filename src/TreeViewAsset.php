<?php
namespace garmayev\treeview;

use yii\web\AssetBundle;

class TreeViewAsset extends AssetBundle
{
    public $sourcePath = '@vendor/garmayev/yii2-treeview-bs5/src/assets';

    public $css = [
        'css/bstreeview.css',
    ];

    public $js = [
        'js/bstreeview.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
    ];

    /**
     * Initializes the asset bundle
     */
    public function init()
    {
        parent::init();

        // Важно: добавляем зависимость от jQuery явно
        $this->depends[] = 'yii\web\JqueryAsset';

        // Для отладки - принудительно копируем файлы
        $this->publishOptions = [
            'forceCopy' => YII_DEBUG,
        ];
    }
}