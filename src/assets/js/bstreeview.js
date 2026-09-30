/*! @preserve
 * bstreeview.js
 * Version: 3.0.0 (Bootstrap 5 List Version)
 * Authors: garmayev
 * Copyright 2024
 * License: Apache License 2.0
 */

; (function ($, window, document, undefined) {
    "use strict";

    var pluginName = "bstreeview",
        defaults = {
            expandIcon: 'fa-solid fa-chevron-down',
            collapseIcon: 'fa-solid fa-chevron-right',
            itemIcon: 'fa-solid fa-file',
            folderIcon: 'fa-solid fa-folder',
            expandClass: 'show',
            indent: 1.5,
            openNodeLinkOnNewTab: false,
            expandOnNodeClick: true,
            toggleOnIconClick: false,
            debug: false
        };

    function bstreeView(element, options) {
        this.element = element;
        this.settings = $.extend({}, defaults, options);
        this.columnCount = (this.settings.columns && this.settings.columns.length) || 1;
        this.init();
    }

    $.extend(bstreeView.prototype, {
        init: function () {
            var _this = this;

            this.log('Initializing bstreeview', this.settings);

            // Добавляем класс для контейнера
            $(this.element).addClass('bstreeview');

            // Создаем заголовок если есть колонки
            if (this.settings.columns && this.settings.columns.length > 0) {
                var $header = $('<div class="bstreeview-header"></div>');

                // Добавляем пустой placeholder для иконок
                $header.append('<div class="bstreeview-header-left"></div>');

                // Контейнер для заголовков колонок
                var $headerContent = $('<div class="bstreeview-header-content"></div>');

                // Добавляем колонки заголовка
                $.each(this.settings.columns, function(index, column) {
                    var $headerCol = $('<div class="bstreeview-header-col"></div>')
                        .text(column.label || '')
                        .attr('data-column-index', index);

                    // Применяем классы выравнивания из headerOptions
                    if (column.headerOptions) {
                        if (column.headerOptions.class) {
                            $headerCol.addClass(column.headerOptions.class);
                        }
                        if (column.headerOptions.style) {
                            $headerCol.attr('style', column.headerOptions.style);
                        }
                    }

                    $headerContent.append($headerCol);
                });

                $header.append($headerContent);
                $(this.element).append($header);
            }

            // Создаем корневой список
            var $list = $('<ul class="bstreeview-list"></ul>');
            $(this.element).append($list);

            // Строим дерево
            this.buildTree($list, this.settings.data, 1);

            // Обработчики событий
            $(this.element).on('click', '.bstreeview-node', function(e) {
                _this.handleNodeClick(e, $(this));
            });

            this.log('Tree built');
        },

        log: function() {
            if (this.settings.debug && window.console) {
                console.log('[bstreeview]', ...arguments);
            }
        },

        buildTree: function($container, nodes, level) {
            var _this = this;

            this.log('Building tree at level', level, 'nodes:', nodes.length);
            $.each(nodes, function(index, node) {
                var hasChildren = node.nodes && node.nodes.length > 0;

                _this.log('Creating node:', node.id, 'hasChildren:', hasChildren, 'expanded:', node.expanded);

                // Создаем элемент списка
                var $item = $('<li class="bstreeview-item"></li>');
                // Создаем контейнер узла
                var $node = $('<div class="bstreeview-node"></div>')
                    .attr('data-id', node.id || '')
                    .attr('data-level', level)
                    .attr('data-has-children', hasChildren ? 'true' : 'false');

                if (node.expanded) {
                    $node.addClass('expanded');
                }

                if (node.class) {
                    $node.addClass(node.class);
                }

                if (node.href) {
                    $node.attr('data-href', node.href);
                }

                // Левая часть с иконками
                var $left = $('<div class="bstreeview-left"></div>');

                // Добавляем отступы для вложенности
                for (var i = 1; i < level; i++) {
                    $left.append('<span class="bstreeview-indent"></span>');
                }

                // Иконка состояния (для узлов с детьми)
                if (hasChildren) {
                    var iconClass = node.expanded ? _this.settings.expandIcon : _this.settings.collapseIcon;
                    var $stateIcon = $('<i class="bstreeview-state-icon ' + iconClass + '"></i>');
                    $left.append($stateIcon);
                } else {
                    // Пустой span для сохранения отступа
                    $left.append('<span class="bstreeview-state-icon" style="opacity:0; width:1rem;">&nbsp;</span>');
                }

                // Иконка элемента
                var itemIconClass = node.icon || (hasChildren ? _this.settings.folderIcon : _this.settings.itemIcon);
                var $itemIcon = $('<i class="bstreeview-item-icon ' + itemIconClass + '"></i>');
                $left.append($itemIcon);

                $node.append($left);

                // Контент узла (колонки данных)
                var $content = $('<div class="bstreeview-content"></div>');

                if (node.columns && node.columns.length > 0) {
                    // Добавляем колонки данных
                    for (var i = 0; i < _this.columnCount; i++) {
                        var colValue = i < node.columns.length ? node.columns[i] : '';
                        var $col = $('<div class="bstreeview-data-col"></div>').html(colValue || '&nbsp;');

                        // Применяем опции колонки если есть
                        if (_this.settings.columns && _this.settings.columns[i]) {
                            var column = _this.settings.columns[i];
                            if (column.contentOptions) {
                                if (column.contentOptions.class) {
                                    $col.addClass(column.contentOptions.class);
                                }
                                if (column.contentOptions.style) {
                                    $col.attr('style', column.contentOptions.style);
                                }
                            }
                        }

                        $content.append($col);
                    }
                } else {
                    // Если нет колонок, добавляем текст на всю ширину
                    $content.html(node.text || '');
                }

                $node.append($content);
                $item.append($node);

                // Добавляем дочерние элементы
                if (hasChildren) {
                    var $group = $('<ul class="bstreeview-group' + (node.expanded ? ' show' : '') + '"></ul>')
                        .attr('data-parent', node.id || '');

                    _this.log('Creating group for node', node.id, 'with', node.nodes.length, 'children');

                    _this.buildTree($group, node.nodes, level + 1);
                    $item.append($group);
                }

                $container.append($item);
            });
        },

        handleNodeClick: function(e, $node) {
            var _this = this;
            var $target = $(e.target);
            var isIcon = $target.hasClass('bstreeview-state-icon') ||
                $target.hasClass('bstreeview-item-icon') ||
                $target.closest('.bstreeview-state-icon, .bstreeview-item-icon').length > 0;
            var hasChildren = $node.data('has-children');
            var nodeId = $node.data('id');

            this.log('Node clicked:', nodeId, 'hasChildren:', hasChildren, 'isIcon:', isIcon);

            // Определяем, нужно ли разворачивать
            var shouldToggle = false;

            if (hasChildren) {
                if (isIcon && this.settings.toggleOnIconClick) {
                    shouldToggle = true;
                    this.log('Toggle on icon click');
                } else if (!isIcon && this.settings.expandOnNodeClick) {
                    shouldToggle = true;
                    this.log('Toggle on node click');
                }
            }
            const cell = $target.closest('.bstreeview-data-col')

            // Разворачиваем/сворачиваем
            if (shouldToggle && !cell.hasClass('bs-treeview-no-toggle')) {
                this.toggleNode($node);
            }

            // Переходим по ссылке если есть
            var href = $node.data('href');
            if (href && !isIcon && !shouldToggle) {
                this.log('Following link:', href);
                if (this.settings.openNodeLinkOnNewTab) {
                    window.open(href, '_blank');
                } else {
                    window.location.href = href;
                }
            }
        },

        toggleNode: function($node) {
            var id = $node.data('id');
            var hasChildren = $node.data('has-children');

            this.log('Toggling node:', id, 'hasChildren:', hasChildren);

            if (!hasChildren) {
                this.log('Node has no children, cannot toggle');
                return;
            }

            // Ищем группу дочерних элементов
            var $parentItem = $node.closest('.bstreeview-item');
            var $group = $parentItem.find('> .bstreeview-group').first();

            if ($group && $group.length) {
                this.log('Found group for node', id);
                if ($group.hasClass('show')) {
                    // Сворачиваем
                    this.log('Collapsing node', id);
                    $group.removeClass('show');
                    $node.removeClass('expanded');
                    $node.find('.bstreeview-state-icon')
                        .removeClass(this.settings.expandIcon)
                        .addClass(this.settings.collapseIcon);
                } else {
                    // Разворачиваем
                    this.log('Expanding node', id);
                    $group.addClass('show');
                    $node.addClass('expanded');
                    $node.find('.bstreeview-state-icon')
                        .removeClass(this.settings.collapseIcon)
                        .addClass(this.settings.expandIcon);
                }
            } else {
                this.log('ERROR: No group found for node', id);
            }
        },

        // Программное раскрытие узла
        expandNode: function(nodeId) {
            this.log('Expanding node programmatically:', nodeId);
            var $node = $(this.element).find('.bstreeview-node[data-id="' + nodeId + '"]');
            if ($node.length && !$node.hasClass('expanded')) {
                this.toggleNode($node);
            }
        },

        // Программное сворачивание узла
        collapseNode: function(nodeId) {
            this.log('Collapsing node programmatically:', nodeId);
            var $node = $(this.element).find('.bstreeview-node[data-id="' + nodeId + '"]');
            if ($node.length && $node.hasClass('expanded')) {
                this.toggleNode($node);
            }
        },

        // Раскрытие всех узлов
        expandAll: function() {
            this.log('Expanding all nodes');
            var _this = this;
            $(this.element).find('.bstreeview-node[data-has-children="true"]:not(.expanded)').each(function() {
                _this.toggleNode($(this));
            });
        },

        // Сворачивание всех узлов
        collapseAll: function() {
            this.log('Collapsing all nodes');
            var _this = this;
            $(this.element).find('.bstreeview-node[data-has-children="true"].expanded').each(function() {
                _this.toggleNode($(this));
            });
        }
    });

    // jQuery plugin definition
    $.fn[pluginName] = function (options) {
        if (typeof options === 'string') {
            var args = Array.prototype.slice.call(arguments, 1);
            var result;

            this.each(function () {
                var instance = $.data(this, "plugin_" + pluginName);
                if (instance && typeof instance[options] === 'function') {
                    var methodResult = instance[options].apply(instance, args);
                    if (result === undefined) {
                        result = methodResult;
                    }
                }
            });

            return result !== undefined ? result : this;
        } else {
            return this.each(function () {
                if (!$.data(this, "plugin_" + pluginName)) {
                    $.data(this, "plugin_" + pluginName, new bstreeView(this, options));
                }
            });
        }
    };

})(jQuery, window, document);