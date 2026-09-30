<?php
namespace garmayev\treeview;

use yii\base\BaseObject;
use yii\data\DataProviderInterface;
use yii\data\ArrayDataProvider;
use yii\db\ActiveRecordInterface;
use yii\grid\Column;
use yii\grid\DataColumn;
use yii\grid\ActionColumn;
use yii\grid\SerialColumn;
use yii\i18n\Formatter;
use Yii;
use Closure;
use yii\web\JsExpression;

class TreeViewDataProvider extends BaseObject
{
    public $dataProvider;
    public $parentIdField = 'parent_id';
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

    public $textField = 'text';
    public $iconField = 'icon';
    public $hrefField = 'href';
    public $expandedField = 'expanded';
    public $nodeClassField = 'node_class';
    public $idField = 'id';
    public $columns = [];
    public $iconConfig = [];
    public $modelCallback;
    public $formatter;
    public $emptyCell = '&nbsp;';

    private $_items = [];
    private $_isArrayDataProvider = false;
    private $_serialCounter = 0;
    private $_nodeMap = [];

    public function buildTree()
    {
        $models = $this->dataProvider->getModels();
        $this->_serialCounter = 0;
        $this->_nodeMap = [];
        $this->_isArrayDataProvider = $this->dataProvider instanceof ArrayDataProvider;

        Yii::info('=== TREEVIEW DEBUG ===', 'treeview');
        Yii::info('Models count: ' . count($models), 'treeview');

        // Если указано поле children, используем иерархическую структуру
        if ($this->childrenField !== null) {
            Yii::info('Using hierarchical tree mode', 'treeview');
            return $this->buildHierarchicalTree($models);
        }

        // Иначе строим плоскую структуру
        Yii::info('Using flat tree mode', 'treeview');

        // Сортируем исходные модели если нужно
        if ($this->orderField !== null || $this->orderHandler !== null) {
            $models = $this->sortModels($models);
        }

        // Группируем по parent_id
        $this->_items = $this->groupByParent($models);

        // Строим дерево рекурсивно
        $tree = $this->buildTreeRecursive(null);

        Yii::info('Final tree with ' . count($tree) . ' root nodes', 'treeview');
        return $tree;
    }

    /**
     * Строит дерево из иерархической структуры
     */
    protected function buildHierarchicalTree($models)
    {
        $tree = [];

        foreach ($models as $key => $model) {
            if ($this->modelCallback !== null) {
                $model = call_user_func($this->modelCallback, $model);
            }

            $node = $this->createNodeFromModel($model, $key);

            // Получаем дочерние элементы
            $children = $this->getChildrenFromModel($model);

            if (!empty($children)) {
                $node['nodes'] = $this->buildHierarchicalTree($children);
            }

            $parentId = $this->getAttributeValue($model, $this->parentIdField);
            $node['parent'] = $this->getAttributeValue($model, $parentId);

            // Определяем иконку
            if (isset($node['nodes']) && !empty($node['nodes'])) {
                if (!isset($node['icon']) || $node['icon'] === ($this->iconConfig['item'] ?? 'fa-solid fa-file')) {
                    $node['icon'] = $this->iconConfig['folder'] ?? 'fa-solid fa-folder';
                }
            } else {
                if (!isset($node['icon']) || $node['icon'] === ($this->iconConfig['folder'] ?? 'fa-solid fa-folder')) {
                    $node['icon'] = $this->iconConfig['item'] ?? 'fa-solid fa-file';
                }
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Получает дочерние элементы из модели для иерархической структуры
     */
    protected function getChildrenFromModel($model)
    {
        $value = $this->getAttributeValue($model, $this->childrenField);

        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof DataProviderInterface) {
            return $value->getModels();
        }

        if (is_object($value)) {
            if (method_exists($value, 'all')) {
                return $value->all();
            }
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
        }

        return [];
    }

    /**
     * Группирует модели по parent_id
     */
    protected function groupByParent($models)
    {
        $items = [];

        foreach ($models as $key => $model) {
            if ($this->modelCallback !== null) {
                $model = call_user_func($this->modelCallback, $model);
            }

            $node = $this->createNodeFromModel($model, $key);
            $nodeId = $this->getAttributeValue($model, $this->idField);
            $parentId = $this->getAttributeValue($model, $this->parentIdField);

            // Сохраняем в карту для быстрого доступа
            if ($nodeId !== null && $nodeId !== '') {
                $this->_nodeMap[(string)$nodeId] = $node;
            }

            // Группируем по parent_id
            $parentKey = $parentId === null || $parentId === '' || $parentId === 0 ? 'root' : (string)$parentId;

            if (!isset($items[$parentKey])) {
                $items[$parentKey] = [];
            }

            $items[$parentKey][] = $node;
        }

        return $items;
    }

    /**
     * Рекурсивно строит дерево
     */
    protected function buildTreeRecursive($parentId)
    {
        $tree = [];
        $key = $parentId === null || $parentId === '' ? 'root' : (string)$parentId;

        if (isset($this->_items[$key]) && is_array($this->_items[$key])) {
            foreach ($this->_items[$key] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemId = isset($item['id']) ? $item['id'] : null;

                // Рекурсивно получаем дочерние элементы
                $children = $this->buildTreeRecursive($itemId);

                if (!empty($children)) {
                    $item['nodes'] = $children;
                    if (isset($item['icon']) && $item['icon'] === ($this->iconConfig['item'] ?? 'fa-solid fa-file')) {
                        $item['icon'] = $this->iconConfig['folder'] ?? 'fa-solid fa-folder';
                    }
                } else {
                    if (!isset($item['icon']) || $item['icon'] === ($this->iconConfig['folder'] ?? 'fa-solid fa-folder')) {
                        $item['icon'] = $this->iconConfig['item'] ?? 'fa-solid fa-file';
                    }
                }

                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Сортировка моделей
     */
    protected function sortModels($models)
    {
        if (!is_array($models)) {
            return $models;
        }

        if ($this->orderHandler !== null) {
            if ($this->orderHandler instanceof Closure) {
                $callback = $this->orderHandler;
                usort($models, function($a, $b) use ($callback) {
                    return $callback($a, $b);
                });
            }
        } elseif ($this->orderField !== null) {
            $field = $this->orderField;
            $direction = $this->orderDirection;

            usort($models, function($a, $b) use ($field, $direction) {
                $valA = $this->getAttributeValue($a, $field);
                $valB = $this->getAttributeValue($b, $field);

                if ($valA == $valB) {
                    return 0;
                }

                $result = ($valA < $valB) ? -1 : 1;
                return ($direction == SORT_DESC) ? -$result : $result;
            });
        }

        return $models;
    }

    /**
     * Создает узел из модели
     */
    protected function createNodeFromModel($model, $key = null)
    {
        $text = $this->getAttributeValue($model, $this->textField);

        $node = [
            'text' => $text ? (string)$text : '',
        ];

        // Добавляем колонки если есть
        if (!empty($this->columns)) {
            $node['columns'] = $this->getColumnValues($model);
        }

        // Иконка
        $icon = $this->getAttributeValue($model, $this->iconField);
        if ($icon !== null && $icon !== '') {
            $node['icon'] = $icon;
        }

        // Ссылка
        $href = $this->getAttributeValue($model, $this->hrefField);
        if ($href !== null && $href !== '') {
            $node['href'] = $href;
        }

        // Развернут ли узел
        $expanded = $this->getAttributeValue($model, $this->expandedField);
        if ($expanded !== null) {
            $node['expanded'] = (bool)$expanded;
        }

        // CSS класс
        $nodeClass = $this->getAttributeValue($model, $this->nodeClassField);
        if ($nodeClass !== null && $nodeClass !== '') {
            $node['class'] = $nodeClass;
        }

        // ID узла
        $id = $this->getAttributeValue($model, $this->idField);
        if ($id !== null && $id !== '') {
            $node['id'] = (string)$id;
        } elseif ($key !== null) {
            $node['id'] = (string)$key;
        }

        $parentId = $this->getAttributeValue($model, $this->parentIdField);
        $node['parent'] = $this->getAttributeValue($model, $parentId);

        // Значение для сортировки
        if ($this->orderField !== null && !$this->orderHandler instanceof Closure) {
            $orderValue = $this->getAttributeValue($model, $this->orderField);
            if ($orderValue !== null) {
                $node['_orderValue'] = $orderValue;
            }
        }

        return $node;
    }

    /**
     * Получает значения колонок
     */
    protected function getColumnValues($model)
    {
        $values = [];
        $columnCount = count($this->columns);

        // Если модель - строка, возвращаем пустые значения
        if (is_string($model)) {
            for ($i = 0; $i < $columnCount; $i++) {
                $values[] = $this->emptyCell;
            }
            return $values;
        }

        foreach ($this->columns as $index => $column) {
            if ($column instanceof SerialColumn) {
                $values[] = ++$this->_serialCounter;
                continue;
            }

            if ($column instanceof ActionColumn) {
                $values[] = $this->renderActionColumn($column, $model);
                continue;
            }

            if ($column instanceof DataColumn) {
                $value = $this->getDataColumnValue($column, $model);
                $formatted = $this->formatDataColumnValue($value, $column);
                $values[] = $formatted === '' || $formatted === null ? $this->emptyCell : $formatted;
                continue;
            }

            $value = $this->getCustomColumnValue($column, $model);
            $formatted = $this->formatValue($value);
            $values[] = $formatted === '' || $formatted === null ? $this->emptyCell : $formatted;
        }

        while (count($values) < $columnCount) {
            $values[] = $this->emptyCell;
        }

        return $values;
    }

    protected function getDataColumnValue($column, $model)
    {
        if (is_string($model)) {
            return null;
        }

        if ($column->value !== null) {
            return call_user_func($column->value, $model, $this, $column);
        }

        if ($column->attribute !== null) {
            return $this->getAttributeValue($model, $column->attribute);
        }

        return null;
    }

    protected function formatDataColumnValue($value, $column)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($column->format === null) {
            return (string)$value;
        }

        return $this->formatter->format($value, $column->format);
    }

    protected function getCustomColumnValue($column, $model)
    {
        if (is_string($model)) {
            return null;
        }

        if (property_exists($column, 'content') && $column->content instanceof Closure) {
            return call_user_func($column->content, $model, $this, $column);
        }

        if (property_exists($column, 'value') && $column->value instanceof Closure) {
            return call_user_func($column->value, $model, $this, $column);
        }

        if (property_exists($column, 'attribute') && $column->attribute) {
            return $this->getAttributeValue($model, $column->attribute);
        }

        return null;
    }

    protected function formatValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof Closure) {
            return '';
        }

        return (string)$value;
    }

    protected function renderActionColumn($column, $model)
    {
        if (is_string($model)) {
            return $this->emptyCell;
        }

        $buttons = [];
        $template = $column->template ?? '{view} {update} {delete}';
        $id = $this->getAttributeValue($model, $this->idField);

        preg_match_all('/{([^}]+)}/', $template, $matches);

        foreach ($matches[1] as $button) {
            if (isset($column->buttons[$button])) {
                $buttonConfig = $column->buttons[$button];
                if (is_callable($buttonConfig)) {
                    $url = $column->createUrl($button, $model, $id, $this);
                    $result = call_user_func($buttonConfig, $url, $model, $button);
                    if ($result !== null) {
                        $buttons[] = $result;
                    }
                }
            } elseif (method_exists($column, 'renderButton')) {
                $buttons[] = $column->renderButton($button, $model, $id);
            }
        }

        $result = implode(' ', $buttons);
        return $result === '' ? $this->emptyCell : $result;
    }

    /**
     * Получает значение атрибута из модели
     */
    protected function getAttributeValue($model, $attribute)
    {
        // Если модель - строка, возвращаем null
        if (is_string($model)) {
            return null;
        }

        if ($attribute instanceof Closure) {
            return call_user_func($attribute, $model);
        }

        if (is_string($attribute)) {
            // Для ArrayDataProvider с массивами
            if ($this->_isArrayDataProvider && is_array($model)) {
                return isset($model[$attribute]) ? $model[$attribute] : null;
            }

            // Для объектов (ActiveRecord и т.д.)
            if (is_object($model)) {
                // Проверяем существование атрибута через hasAttribute для ActiveRecord
                if (method_exists($model, 'hasAttribute') && $model->hasAttribute($attribute)) {
                    return $model->getAttribute($attribute);
                }

                // Проверяем свойство объекта
                if (property_exists($model, $attribute)) {
                    return $model->$attribute;
                }

                // Проверяем геттер
                $methodName = 'get' . ucfirst($attribute);
                if (method_exists($model, $methodName)) {
                    return $model->$methodName();
                }

                // Пробуем магический метод __get
                if (method_exists($model, '__get')) {
                    try {
                        return $model->$attribute;
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            }
        }

        return null;
    }
}