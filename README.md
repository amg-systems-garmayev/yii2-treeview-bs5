# Введение
   Расширение yii2-treeview-bs5 предоставляет виджет yii2-treeview-bs5 для фреймворка Yii2, который позволяет визуализировать иерархические данные (деревья) в красивом, интерактивном виде. Оно построено на основе Bootstrap 5 и использует Font Awesome для иконок.

Ключевая особенность расширения — тесная интеграция с GridView Yii2. Это означает, что вы можете настроить колонки вашего дерева точно так же, как вы настраиваете колонки в GridView (DataColumn, ActionColumn, SerialColumn и т.д.), что делает его невероятно гибким и удобным для разработчиков, уже знакомых с Yii2.

Основные возможности:
- Отображение иерархических данных: Строит дерево на основе данных из DataProvider (например, из ActiveDataProvider).
- Интеграция с GridView: Использует знакомый синтаксис конфигурации колонок Yii2 GridView.
- Интерактивность: Поддержка раскрытия/сворачивания узлов, кликабельные ссылки, настраиваемые иконки.
- Настройка отображения: Гибкая настройка колонок данных, включая форматирование, CSS-классы и стили.
- Темная тема: Автоматически поддерживает темную тему Bootstrap 5 (data-bs-theme="dark").
- Программное управление: Возможность раскрывать/сворачивать узлы через JavaScript API.

# Установка
## Через Composer

Выполните в консоли следующую команду:

```bash
composer require garmayev/yii2-treeview-bs5
```

## Регистрация ассетов
Виджет автоматически зарегистрирует необходимые CSS и JavaScript файлы (включая bstreeview.css и bstreeview.js) через свой AssetBundle. Он также подключает Font Awesome и зависит от стандартных ассетов Yii2 (jQuery, BootstrapAsset, BootstrapPluginAsset). Вам не нужно делать ничего дополнительно.

# Использование

### Этот пример покажет дерево категорий с одной колонкой, отображающей название.

В контроллере:

```php
<?php

namespace app\controllers;

use app\models\Category;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use garmayev\treeview\TreeView;

class CategoryController extends Controller
{
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Category::find(),
            'pagination' => false, // Для деревьев пагинация обычно не нужна
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
}
```

Во view (index.php):

```php
<?php
use garmayev\treeview\TreeView;

echo TreeView::widget([
    'dataProvider' => $dataProvider,
    'parentIdField' => 'parent_id', // Поле, указывающее на родителя (по умолчанию 'parent_id')
    'textField' => 'name',           // Поле, которое будет текстом узла
    'idField' => 'id',               // Поле с идентификатором узла
    'columns' => [                   // Массив колонок (как в GridView)
        'name',                      // Просто отобразим поле 'name' в первой колонке
    ],
]);
```

### Пример с несколькими колонками и иконками
Покажем дерево продуктов с колонками "Название", "Цена" и "Количество", а также с иконками.

Во view:

```php
<?= TreeView::widget([
    'dataProvider' => $dataProvider,
    'parentIdField' => 'category_id', // Например, товары группируются по категориям
    'textField' => 'title',
    'idField' => 'id',
    'icon' => [ // Настройка иконок
        'folder' => 'fa-solid fa-box', // Иконка для папки (родительского узла)
        'item' => 'fa-solid fa-cube',  // Иконка для элемента (листового узла)
        'expand' => 'fa-solid fa-chevron-down', // Иконка раскрытия
        'collapse' => 'fa-solid fa-chevron-right', // Иконка сворачивания
    ],
    'columns' => [
        'title', // Первая колонка
        [ // Вторая колонка — цена
            'attribute' => 'price',
            'format' => ['currency', 'USD'],
            'contentOptions' => ['class' => 'text-right'], // Выравнивание контента
            'headerOptions' => ['class' => 'text-right'], // Выравнивание заголовка
        ],
        [ // Третья колонка — количество
            'attribute' => 'stock',
            'label' => 'Stock',
            'contentOptions' => ['class' => 'text-center'],
        ],
        [ // Четвертая колонка — действия (как в GridView)
            'class' => 'yii\grid\ActionColumn',
            'template' => '{view} {update}',
            'buttons' => [
                'view' => function ($url, $model, $key) {
                    return Html::a('<i class="fa-regular fa-eye"></i>', $url);
                },
                'update' => function ($url, $model, $key) {
                    return Html::a('<i class="fa-regular fa-pen-to-square"></i>', $url);
                },
            ],
        ],
    ],
]); ?>
```

### Использование кастомных колонок и modelCallback
Если вам нужно обработать модель перед рендерингом или отобразить сложные данные, используйте modelCallback и кастомные колонки.

Во view:

```php
<?= TreeView::widget([
    'dataProvider' => $dataProvider,
    'parentIdField' => 'parent_id',
    'idField' => 'id',
    'textField' => 'title',
    // modelCallback позволяет модифицировать модель перед тем, как из нее будет создан узел
    'modelCallback' => function ($model) {
        $model->title = strtoupper($model->title); // Пример: делаем заголовок заглавным
        return $model;
    },
    'columns' => [
        'title',
        [
            'class' => 'yii\grid\DataColumn',
            'label' => 'Status Badge',
            'value' => function ($model) {
                return $model->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
            },
            'format' => 'raw', // Важно для отображения HTML
        ],
        [
            'class' => 'yii\grid\DataColumn',
            'label' => 'Description',
            'value' => function ($model) {
                return \yii\helpers\StringHelper::truncate($model->description, 50);
            },
        ],
    ],
]); ?>
```

# API Свойств и Конфигурация
   Виджет TreeView принимает множество параметров для настройки.

## Основные свойства

| Свойство	        | Тип	                                                                                                          | Описание	                                                                                                                            | Значение по умолчанию                                                              |
|------------------|---------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| $dataProvider	   | DataProviderInterface                                                                                         | Источник данных, реализующий интерфейс DataProviderInterface (например, ActiveDataProvider, ArrayDataProvider).                      | -                                                                                  |
| $parentIdField	  | string\|Closure	                                                                                              | Имя поля (или анонимная функция) в модели, которое содержит ID родительского узла. | 'parent_id' |
| $textField	      | string\| Closure	                                                                                             | Имя поля (или функция) для текста узла.	                                           | 'name' |
| $idField	        | string\| Closure	                                                                                             |Имя поля (или функция) для уникального ID узла.	| 'id' | 
| $columns	        | array	                                                                                                        | Важное. Массив конфигурации колонок. Полностью совместим с конфигурацией колонок yii\grid\GridView.	                                 |[]|
| $icon	           | array	                                                                                                        | Массив для настройки CSS-классов иконок Font Awesome.	                                                                               |См. раздел 4.2|
| $clientOptions	  | array	                                                                                                        | Массив опций, передаваемых в JavaScript-плагин bstreeview.	                                                                          |См. раздел 4.3|
| $options	        | array	                                                                                                        | HTML-атрибуты для контейнера виджета. По умолчанию содержит ['class' => 'bstreeview']. ID генерируется автоматически, если не задан.	 |['class' => 'bstreeview']|
| $modelCallback	  | callable	                                                                                                     | Анонимная функция для обработки каждой модели перед построением узла. Функция должна вернуть модель.	                                |null|
| $formatter	      | array	                                                                                                        | Конфигурация для Yii Formatter компонента.	                                                                                          |[]|
| $emptyCell	      | string	                                                                                                       | HTML-контент, отображаемый в пустой ячейке колонки.	                                                                                 |'&nbsp;'|
| $hrefField	      | string\| Closure	Имя поля (или функция), содержащее URL для перехода при клике на узел.	                      |'href'|
| $expandedField	  | string\| Closure	Имя поля (или функция), указывающее, должен ли узел быть раскрыт по умолчанию (true/false).	 |'expanded'| 
| $nodeClassField	 | string\| Closure	Имя поля (или функция) для дополнительного CSS-класса для конкретного узла.	                 |'node_class'|
| $iconField	     | string\| Closure	|Имя поля (или функция), которое позволяет переопределить иконку для конкретного узла.	|'icon'| 

## Настройка иконок ($icon)
|Ключ	|Описание	|Значение по умолчанию |
|---|---|---|
|expand	|Иконка для раскрытия узла (когда узел открыт).	|'fa-solid fa-chevron-down' |
|collapse	|Иконка для сворачивания узла (когда узел закрыт).	|'fa-solid fa-chevron-right'|
|item	|Иконка для элемента, у которого нет детей (лист).	|'fa-solid fa-file'|
|folder	|Иконка для элемента, у которого есть дети (папка).	|'fa-solid fa-folder'|
|empty	|(Зарезервировано)	|'fa-solid fa-circle'|

## Клиентские опции ($clientOptions)

Эти опции передаются в JavaScript плагин bstreeview.

|Опция	|Тип	|Описание	|Значение по умолчанию |
|---|---|---|---|
|openNodeLinkOnNewTab	|boolean	|Открывать ссылку (из hrefField) в новой вкладке?	|false |
|toggleOnIconClick	|boolean	|Разворачивать/сворачивать узел только при клике на иконку (+/-)? Если false, то при клике на любую часть строки.	|false |
|expandOnNodeClick	|boolean	|Разворачивать/сворачивать узел при клике на строку (если toggleOnIconClick = false).	|true |
|debug	|boolean	|Включать отладочные сообщения в консоль браузера.	|false|

Примечание: Параметры expandIcon и collapseIcon в clientOptions автоматически заполняются из массива $icon.

## Конфигурация колонок ($columns)

Это самая мощная часть расширения. Вы можете использовать все типы колонок, доступные в Yii2 GridView:

- yii\grid\DataColumn: Для отображения данных из атрибута модели.
- yii\grid\SerialColumn: Для отображения порядкового номера строки.
- yii\grid\ActionColumn: Для отображения кнопок действий (view, update, delete).
- yii\grid\CheckboxColumn: (Может потребоваться адаптация, но должна работать).
- Любые свои колонки, наследующие yii\grid\Column.

Специфические возможности при работе с TreeView:

- Заголовки (label, headerOptions): Используются для отображения заголовков колонок в шапке виджета.
- Контент (value, contentOptions): Используются для отображения данных в ячейках каждой строки дерева.
- Выравнивание: Классы text-left, text-center, text-right, переданные через contentOptions и headerOptions, будут применены к соответствующим элементам дерева.

# JavaScript API
   После инициализации виджета вы можете управлять им программно через jQuery.

## Доступ к экземпляру плагина
Чтобы вызвать методы, сначала нужно получить доступ к экземпляру плагина для конкретного элемента.

```javascript
var treeInstance = $('#id-вашего-виджета').data('plugin_bstreeview');
```

## Методы

|Метод	|Описание	|Пример |
|---|---|---|
|expandNode(nodeId)	|Программно раскрывает узел с указанным ID.	|treeInstance.expandNode('5'); |
|collapseNode(nodeId)	|Программно сворачивает узел с указанным ID.	|treeInstance.collapseNode('5'); |
|expandAll()	|Раскрывает все узлы дерева.	|treeInstance.expandAll(); |
|collapseAll()	|Сворачивает все узлы дерева.	|treeInstance.collapseAll(); |
|toggleNode($node)	|Переключает состояние конкретного jQuery-объекта узла. (Внутренний метод, но может быть вызван).	|treeInstance.toggleNode($('#some-node'));|

## Пример использования

```html
<!-- Где-то во view, после виджета TreeView -->
<button id="btn-expand-all">Раскрыть всё</button>
<button id="btn-collapse-all">Свернуть всё</button>

<script>
$(document).ready(function() {
    var tree = $('#w0').data('plugin_bstreeview'); // w0 — это ID, сгенерированный по умолчанию

    $('#btn-expand-all').click(function() {
        if (tree) {
            tree.expandAll();
        }
    });

    $('#btn-collapse-all').click(function() {
        if (tree) {
            tree.collapseAll();
        }
    });
});
</script>
```

# Стилизация и Темная тема
   
## Основные CSS-классы

Виджет использует следующие CSS-классы для стилизации, которые вы можете переопределить в своем проекте:

- .bstreeview: Основной контейнер.
- .bstreeview-header: Заголовок с названиями колонок.
- .bstreeview-node: Контейнер для строки одного узла.
- .bstreeview-node.expanded: Строка раскрытого узла.
- .bstreeview-group: Контейнер для дочерних элементов (список <ul>).
- .bstreeview-state-icon: Иконка раскрытия/сворачивания.
- .bstreeview-item-icon: Иконка элемента (файла/папки).
- .bstreeview-data-col: Ячейка данных.
- .bstreeview-header-col: Ячейка заголовка.

## Темная тема
Виджет автоматически реагирует на атрибут data-bs-theme="dark", установленный на родительском элементе (обычно на теге <html> или <body>). Если ваша страница использует темную тему Bootstrap, дерево будет перекрашено соответствующим образом.

```html
<html data-bs-theme="dark">
    <!-- Дерево будет в темных тонах -->
</html>
```

# Обработка ошибок

Если вы не передадите dataProvider, виджет выбросит исключение yii\base\InvalidConfigException.

Если поле, указанное в parentIdField или других полях, не существует в модели, виджет вернет null для этого атрибута, но не вызовет ошибку.

Включите режим debug в clientOptions, чтобы видеть подробную информацию о работе JavaScript-плагина в консоли браузера. Это поможет отладить проблемы с раскрытием узлов или кликами.

# Полный пример рабочего сценария

Модель Category (предполагается, что у нее есть поля: id, name, parent_id, description, is_visible).

Контроллер CategoryController:

```php
<?php

namespace app\controllers;

use app\models\Category;
use yii\data\ActiveDataProvider;
use yii\web\Controller;

class CategoryController extends Controller
{
    public function actionTree()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Category::find()->orderBy('name'),
            'pagination' => false,
        ]);

        return $this->render('tree', [
            'dataProvider' => $dataProvider,
        ]);
    }
}
```

View tree.php:

```php
<?php

use garmayev\treeview\TreeView;
use yii\helpers\Html;

$this->title = 'Управление категориями';
?>

<div class="category-tree">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= TreeView::widget([
        'dataProvider' => $dataProvider,
        'parentIdField' => 'parent_id',
        'textField' => 'name',
        'idField' => 'id',
        'icon' => [
            'folder' => 'fa-regular fa-folder-open',
            'item' => 'fa-regular fa-file-lines',
        ],
        'clientOptions' => [
            'toggleOnIconClick' => true,
            'openNodeLinkOnNewTab' => false,
            'debug' => YII_DEBUG,
        ],
        'options' => [
            'id' => 'category-tree-widget',
            'class' => 'bstreeview border-0', // Дополнительный класс
        ],
        'columns' => [
            'name', // Первая колонка — имя
            [
                'attribute' => 'description',
                'format' => 'ntext',
                'label' => 'Описание',
                'contentOptions' => ['class' => 'text-muted'],
            ],
            [
                'class' => 'yii\grid\DataColumn',
                'label' => 'Статус',
                'value' => function ($model) {
                    return $model->is_visible ? 'Активна' : 'Скрыта';
                },
                'contentOptions' => function ($model) {
                    return ['class' => $model->is_visible ? 'text-success' : 'text-secondary'];
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model, $key) {
                        return Html::a('<i class="fa-regular fa-pen-to-square"></i>', $url, ['class' => 'me-2']);
                    },
                    'delete' => function ($url, $model, $key) {
                        return Html::a('<i class="fa-regular fa-trash-can"></i>', $url, [
                            'data-method' => 'post',
                            'data-confirm' => 'Удалить категорию?',
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>

    <!-- Кнопки управления -->
    <div class="mt-3">
        <button id="expand-all-btn" class="btn btn-outline-primary btn-sm">Раскрыть все</button>
        <button id="collapse-all-btn" class="btn btn-outline-secondary btn-sm">Свернуть все</button>
    </div>
</div>

<?php
$js = <<<JS
$('#expand-all-btn').click(function() {
    var tree = $('#category-tree-widget').data('plugin_bstreeview');
    if (tree) tree.expandAll();
});
$('#collapse-all-btn').click(function() {
    var tree = $('#category-tree-widget').data('plugin_bstreeview');
    if (tree) tree.collapseAll();
});
JS;
$this->registerJs($js);
?>
```