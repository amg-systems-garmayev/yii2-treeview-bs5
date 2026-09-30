<?php
namespace garmayev\treeview;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\data\DataProviderInterface;
use yii\grid\Column;
use yii\grid\DataColumn;
use yii\grid\ActionColumn;
use yii\grid\SerialColumn;
use yii\i18n\Formatter;
use Closure;
use yii\web\JsExpression;

class TreeView extends Widget
{
    public $dataProvider;

    /**
     * @var string|Closure|null Поле для родительского ID
     */
    public $parentIdField = 'parent_id';

    /**
     * @var string|Closure|null Поле для вложенных элементов
     */
    public $childrenField = null;

    /**
     * @var string|Closure|null Поле для сортировки
     */
    public $orderField = null;

    /**
     * @var string|Closure|JsExpression|null Функция сортировки
     */
    public $orderHandler = null;

    /**
     * @var int Направление сортировки
     */
    public $orderDirection = SORT_ASC;

    /**
     * @var bool Включить drag-and-drop сортировку
     */
    public $dragAndDrop = false;

    /**
     * @var array Настройки drag-and-drop
     */
    public $dragAndDropOptions = [
        'dragHandle' => '.bstreeview-node',
        'dropTarget' => '.bstreeview-group',
        'placeholder' => 'drop-placeholder',
        'dragClass' => 'drag-source',
        'dropClass' => 'drop-target',
        'nesting' => true,
        'maxDepth' => 10,
    ];

    /**
     * @var string|array|null URL для сохранения нового порядка после сортировки
     * Может быть строкой (URL) или массивом для Url::to()
     * Пример: ['/category/update-order']
     */
    public $dragAndDropUrl = null;

    /**
     * @var string HTTP метод для запроса сортировки (POST, PUT)
     */
    public $dragAndDropMethod = 'POST';

    /**
     * @var string|Closure|JsExpression|null Обработчик после сортировки
     */
    public $onDropHandler = null;

    public $textField = 'name';
    public $iconField = 'icon';
    public $hrefField = 'href';
    public $expandedField = 'expanded';
    public $nodeClassField = 'node_class';
    public $idField = 'id';
    public $columns = [];
    public $icon = [
        'expand' => 'fa-solid fa-chevron-down',
        'collapse' => 'fa-solid fa-chevron-right',
        'item' => 'fa-solid fa-file',
        'folder' => 'fa-solid fa-folder',
        'empty' => 'fa-solid fa-circle',
    ];
    public $clientOptions = [];
    public $options = ['class' => 'bstreeview'];
    public $modelCallback;
    public $formatter = [];
    public $emptyCell = '&nbsp;';

    private $_columns = [];
    private $_formatter;
    private $_defaultClientOptions = [
        'expandIcon' => 'fa-solid fa-chevron-down',
        'collapseIcon' => 'fa-solid fa-chevron-right',
        'indent' => 1.25,
        'openNodeLinkOnNewTab' => false,
        'expandOnRowClick' => true,
        'toggleOnIconClick' => false,
    ];

    public function init()
    {
        parent::init();

        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }

        if (!$this->dataProvider instanceof DataProviderInterface) {
            throw new \yii\base\InvalidConfigException('The "dataProvider" property must implement DataProviderInterface');
        }

        $this->icon = array_merge([
            'expand' => 'fa-solid fa-chevron-down',
            'collapse' => 'fa-solid fa-chevron-right',
            'item' => 'fa-solid fa-file',
            'folder' => 'fa-solid fa-folder',
            'empty' => 'fa-solid fa-circle',
        ], $this->icon);

        // Обновляем clientOptions
        $this->clientOptions = array_merge($this->_defaultClientOptions, $this->clientOptions);
        $this->clientOptions['expandIcon'] = $this->icon['expand'];
        $this->clientOptions['collapseIcon'] = $this->icon['collapse'];
        $this->clientOptions['expandOnNodeClick'] = $this->clientOptions['expandOnRowClick'];
        unset($this->clientOptions['expandOnRowClick']);

        // Добавляем настройки drag-and-drop
        if ($this->dragAndDrop) {
            $this->clientOptions['dragAndDrop'] = true;
            $this->clientOptions['dragAndDropOptions'] = $this->dragAndDropOptions;

            // Добавляем URL для сохранения сортировки
            if ($this->dragAndDropUrl !== null) {
                $this->clientOptions['dragAndDropUrl'] = $this->createDropUrl();
                $this->clientOptions['dragAndDropMethod'] = $this->dragAndDropMethod;
            }

            if ($this->onDropHandler !== null) {
                if ($this->onDropHandler instanceof JsExpression) {
                    $this->clientOptions['onDrop'] = $this->onDropHandler;
                } elseif ($this->onDropHandler instanceof Closure) {
                    // Для PHP callback нужно создать URL для AJAX запроса
                    $this->clientOptions['onDropUrl'] = $this->createDropUrl();
                } elseif (is_string($this->onDropHandler)) {
                    $this->clientOptions['onDrop'] = new JsExpression($this->onDropHandler);
                }
            }
        }

        $this->initFormatter();
        $this->initColumns();

        // Информация о колонках
        $columnInfo = [];
        foreach ($this->_columns as $column) {
            $columnInfo[] = [
                'label' => $this->getColumnLabel($column),
                'headerOptions' => $this->getColumnHeaderOptions($column),
                'contentOptions' => $this->getColumnContentOptions($column),
            ];
        }
        $this->clientOptions['columns'] = $columnInfo;

        $this->registerAssets();
    }

    /**
     * Создает URL для обработки drop события
     */
    protected function createDropUrl()
    {
        if ($this->dragAndDropUrl === null) {
            return null;
        }

        if (is_array($this->dragAndDropUrl)) {
            return Url::to($this->dragAndDropUrl);
        }

        return $this->dragAndDropUrl;
    }

    protected function registerAssets()
    {
        $view = $this->getView();
        TreeViewAsset::register($view);

        $view->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', [
            'integrity' => 'sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==',
            'crossorigin' => 'anonymous',
            'referrerpolicy' => 'no-referrer'
        ]);

        // Регистрируем SortableJS для drag-and-drop
        if ($this->dragAndDrop) {
            $view->registerJsFile('https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', [
                'position' => \yii\web\View::POS_END
            ]);
        }
    }

    protected function initFormatter()
    {
        $this->_formatter = new Formatter();
        if (!empty($this->formatter) && is_array($this->formatter)) {
            Yii::configure($this->_formatter, $this->formatter);
        }
    }

    protected function initColumns()
    {
        if (empty($this->columns)) {
            return;
        }

        foreach ($this->columns as $i => $column) {
            if (is_string($column)) {
                $column = $this->createDataColumn($column);
            } else {
                $column = Yii::createObject(array_merge([
                    'class' => DataColumn::class,
                    'grid' => $this,
                ], $column));
            }

            if (!$column instanceof Column) {
                throw new \yii\base\InvalidConfigException('Column must be either a string or an array');
            }

            $column->grid = $this;
            $this->_columns[] = $column;
        }
    }

    protected function createDataColumn($text)
    {
        if (!preg_match('/^([^:]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            throw new \yii\base\InvalidConfigException('The column must be specified in the format of "attribute", "attribute:format" or "attribute:format:label"');
        }

        return Yii::createObject([
            'class' => DataColumn::class,
            'grid' => $this,
            'attribute' => $matches[1],
            'format' => isset($matches[3]) ? $matches[3] : 'text',
            'label' => isset($matches[5]) ? $matches[5] : null,
        ]);
    }

    protected function getColumnLabel($column)
    {
        if ($column instanceof SerialColumn) {
            return '#';
        } elseif ($column instanceof ActionColumn) {
            return 'Действия';
        } else {
            if (property_exists($column, 'label') && $column->label !== null) {
                return $column->label;
            }

            if (property_exists($column, 'attribute') && $column->attribute) {
                return $this->generateAttributeLabel($column->attribute);
            }

            return '';
        }
    }

    protected function generateAttributeLabel($name)
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    protected function getColumnHeaderOptions($column)
    {
        $options = ['class' => 'tree-data-cell'];

        if (property_exists($column, 'headerOptions')) {
            if (is_array($column->headerOptions)) {
                if (isset($column->headerOptions['class'])) {
                    $options['class'] .= ' ' . $column->headerOptions['class'];
                }
                if (isset($column->headerOptions['style'])) {
                    $options['style'] = $column->headerOptions['style'];
                }
            }
        }

        if (isset($column->contentOptions['class']) && strpos($column->contentOptions['class'], 'col-') !== false) {
            $options['class'] .= ' ' . $column->contentOptions['class'];
        }

        return $options;
    }

    protected function getColumnContentOptions($column)
    {
        $options = ['class' => 'tree-data-cell'];

        if (property_exists($column, 'contentOptions')) {
            if (is_array($column->contentOptions)) {
                if (isset($column->contentOptions['class'])) {
                    $options['class'] .= ' ' . $column->contentOptions['class'];
                }
                if (isset($column->contentOptions['style'])) {
                    $options['style'] = $column->contentOptions['style'];
                }
            }
        }

        return $options;
    }

    public function run()
    {
        $treeBuilder = new TreeViewDataProvider([
            'dataProvider' => $this->dataProvider,
            'parentIdField' => $this->parentIdField,
            'childrenField' => $this->childrenField,
            'orderField' => $this->orderField,
            'orderHandler' => $this->orderHandler,
            'orderDirection' => $this->orderDirection,
            'textField' => $this->textField,
            'iconField' => $this->iconField,
            'hrefField' => $this->hrefField,
            'expandedField' => $this->expandedField,
            'nodeClassField' => $this->nodeClassField,
            'idField' => $this->idField,
            'columns' => $this->_columns,
            'iconConfig' => $this->icon,
            'modelCallback' => $this->modelCallback,
            'formatter' => $this->_formatter,
            'emptyCell' => $this->emptyCell,
        ]);

        $treeData = $treeBuilder->buildTree();
        $this->clientOptions['data'] = $treeData;

        $jsonOptions = Json::encode($this->clientOptions);

        // Добавляем обработчик для drag-and-drop
        $dragAndDropJs = '';
        if ($this->dragAndDrop) {
            $dragAndDropJs = $this->generateDragAndDropJs();
        }

        $js = "$('#{$this->options['id']}').bstreeview($jsonOptions);";

        if ($dragAndDropJs) {
            $js .= $dragAndDropJs;
        }

        $this->getView()->registerJs($js, \yii\web\View::POS_READY);

        return Html::tag('div', '', $this->options);
    }

    /**
     * Генерирует JavaScript для drag-and-drop
     */
    protected function generateDragAndDropJs()
    {
        $id = $this->options['id'];

        // Безопасно получаем настройки
        $dragHandle = isset($this->dragAndDropOptions['dragHandle']) ? $this->dragAndDropOptions['dragHandle'] : '.bstreeview-node';
        $dragClass = isset($this->dragAndDropOptions['dragClass']) ? $this->dragAndDropOptions['dragClass'] : 'drag-source';

        // Преобразуем булевы значения в JavaScript
        $nesting = isset($this->dragAndDropOptions['nesting']) ? ($this->dragAndDropOptions['nesting'] ? 'true' : 'false') : 'true';
        $maxDepth = isset($this->dragAndDropOptions['maxDepth']) ? (int)$this->dragAndDropOptions['maxDepth'] : 10;

        // Получаем URL для сохранения сортировки
        $dropUrl = $this->createDropUrl();
        $dropUrlJs = $dropUrl ? json_encode($dropUrl) : 'null';
        $method = json_encode(strtoupper($this->dragAndDropMethod));

        $js = <<<JS
    // Функция обработки drop события
    function handleTreeDrop(evt, containerId) {
        var fromList = evt.from;
        var toList = evt.to;
        var draggedItem = evt.item;
        var newParent = null;
        var newParentId = null;
        var draggedId = draggedItem.querySelector('.bstreeview-node') ? 
            draggedItem.querySelector('.bstreeview-node').dataset.id : null;
        var oldParentId = fromList.closest('.bstreeview-item') ? 
            fromList.closest('.bstreeview-item').querySelector('.bstreeview-node').dataset.id : null;
        
        // Определяем нового родителя
        if (toList && toList.classList.contains('bstreeview-group')) {
            var parentItem = toList.closest('.bstreeview-item');
            if (parentItem) {
                newParent = parentItem.querySelector('.bstreeview-node');
                newParentId = newParent ? newParent.dataset.id : null;
            }
        }
        
        // Собираем данные о новом порядке для текущего списка
        var orderData = [];
        if (toList) {
            var items = toList.querySelectorAll(':scope > .bstreeview-item');
            items.forEach(function(item, index) {
                var node = item.querySelector('.bstreeview-node');
                if (node && node.dataset.id) {
                    orderData.push({
                        id: node.dataset.id,
                        order: index + 1,
                        parent_id: newParentId,
                        level: toList.closest('.bstreeview-item') ? 
                            parseInt(toList.closest('.bstreeview-item').querySelector('.bstreeview-node').dataset.level) + 1 : 1
                    });
                }
            });
        }
        
        // Собираем данные о всем дереве (если нужно)
        var fullTreeData = [];
        var container = document.querySelector(containerId);
        if (container) {
            var rootItems = container.querySelectorAll(':scope > .bstreeview-list > .bstreeview-item');
            fullTreeData = collectTreeData(rootItems);
        }
        
        // Создаем объект с данными для отправки
        var postData = {
            draggedId: draggedId,
            oldParentId: oldParentId,
            newParentId: newParentId,
            orderData: orderData,
            fullTreeData: fullTreeData,
            timestamp: new Date().getTime()
        };
        
        // Вызываем глобальный обработчик если есть
        if (window.treeDropHandler && typeof window.treeDropHandler === 'function') {
            window.treeDropHandler(postData);
        }
        
        // AJAX запрос если указан URL
        var dropUrl = {$dropUrlJs};
        if (dropUrl) {
            fetch(dropUrl, {
                method: {$method},
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(postData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Tree reordered successfully', data);
                // Можно добавить уведомление об успехе
                if (data.success) {
                    // Опционально: показать сообщение об успехе
                }
            })
            .catch(error => {
                console.error('Error reordering tree:', error);
                // Можно добавить уведомление об ошибке
            });
        }
    }
    
    // Функция для рекурсивного сбора данных дерева
    function collectTreeData(items) {
        var data = [];
        items.forEach(function(item, index) {
            var node = item.querySelector('.bstreeview-node');
            if (node) {
                var itemData = {
                    id: node.dataset.id,
                    order: index + 1,
                    children: []
                };
                
                var group = item.querySelector(':scope > .bstreeview-group');
                if (group) {
                    var childItems = group.querySelectorAll(':scope > .bstreeview-item');
                    if (childItems.length > 0) {
                        itemData.children = collectTreeData(childItems);
                    }
                }
                
                data.push(itemData);
            }
        });
        return data;
    }

    // Функция для проверки глубины вложенности
    function getNodeDepth(node) {
        var depth = 0;
        var current = node;
        while (current.parentNode && current.parentNode.closest('.bstreeview-item')) {
            depth++;
            current = current.parentNode.closest('.bstreeview-item');
        }
        return depth;
    }

    // Функция для проверки, можно ли переместить элемент
    function canMove(item, targetList) {
        // Если вложенность запрещена, разрешаем только перемещение внутри той же группы
        if ({$nesting} === false) {
            // Получаем родительскую группу элемента
            var sourceGroup = item.parentNode;
            // Разрешаем перемещение только в ту же группу
            return sourceGroup === targetList;
        }
        
        // Проверяем максимальную глубину
        if ({$maxDepth} > 0) {
            var targetParent = targetList.closest('.bstreeview-item');
            var targetDepth = targetParent ? getNodeDepth(targetParent) + 1 : 0;
            
            if (targetDepth > {$maxDepth}) {
                return false;
            }
        }
        
        return true;
    }

    // Функция для проверки, является ли цель перетаскивания
    function isDragHandle(element) {
        return element.matches('{$dragHandle}') || 
               element.closest('{$dragHandle}') !== null;
    }

    // Инициализация Sortable для всех списков
    function initSortable() {
        var container = document.querySelector('#{$id}');
        if (!container) return;
        
        // Общие настройки для Sortable
        var sortableOptions = {
            group: {
                name: 'tree',
                pull: function(to, from, drag) {
                    // Проверяем, можно ли переместить элемент в целевой список
                    if (drag && to.el) {
                        return canMove(drag, to.el);
                    }
                    return true;
                },
                revertClone: false
            },
            animation: 150,
            handle: '{$dragHandle}',
            draggable: '.bstreeview-item',
            ghostClass: '{$dragClass}',
            dragClass: '{$dragClass}',
            fallbackClass: '{$dragClass}',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            direction: 'vertical',
            // Отключаем перетаскивание при клике на иконки раскрытия
            filter: '.bstreeview-state-icon, .bstreeview-item-icon, .bstreeview-data-col a, .bstreeview-data-col button, .bstreeview-data-col input, .bstreeview-data-col select',
            preventOnFilter: false,
            onEnd: function(evt) {
                handleTreeDrop(evt, '#{$id}');
            },
            onStart: function(evt) {
                // Если клик был по иконке или ссылке - отменяем перетаскивание
                if (evt.originalEvent && evt.originalEvent.target) {
                    var target = evt.originalEvent.target;
                    if (target.closest('.bstreeview-state-icon') || 
                        target.closest('.bstreeview-item-icon') ||
                        target.closest('a') ||
                        target.closest('button') ||
                        target.closest('input') ||
                        target.closest('select')) {
                        evt.preventDefault();
                        return false;
                    }
                }
            }
        };
        
        // Для корневого списка
        var rootList = container.querySelector('.bstreeview-list');
        if (rootList && !rootList.sortable) {
            rootList.sortable = new Sortable(rootList, sortableOptions);
        }
        
        // Для всех вложенных списков
        var groups = container.querySelectorAll('.bstreeview-group');
        groups.forEach(function(group) {
            if (!group.sortable) {
                group.sortable = new Sortable(group, sortableOptions);
            }
        });
    }
    
    // Инициализируем после загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSortable);
    } else {
        initSortable();
    }
JS;

        // Добавляем пользовательский обработчик если есть
        if ($this->onDropHandler !== null) {
            if ($this->onDropHandler instanceof JsExpression) {
                $js .= "\nwindow.treeDropHandler = " . (string)$this->onDropHandler . ";";
            } elseif (is_string($this->onDropHandler)) {
                $js .= "\nwindow.treeDropHandler = " . $this->onDropHandler . ";";
            }
        }

        return $js;
    }

    public function getFormatter()
    {
        return $this->_formatter;
    }
}