/**
 * sortable_manager.js
 * 
 * カテゴリ管理および商品表示設定ページのドラッグ＆ドロップ並び替え処理を共通化
 * jQuery / jQuery-UI に依存
 */
(function (global, $) {
    'use strict';

    if (!$ || !$.fn || !$.fn.sortable) {
        console.error('SortableManager: jQuery UI sortable がロードされていません');
        return;
    }

    const SortableManager = {
        /** カテゴリ管理ページ用 */
        initCategorySort: function () {
            const $table = $('#sortable-categories');
            if (!$table.length || $table.data('ui-sortable')) return;

            injectPlaceholderStyle('sortable-placeholder-cat');

            $table.sortable({
                cursor: 'move',
                axis: 'y',
                items: '> tr',
                placeholder: 'sortable-placeholder-cat',
                helper: maintainCellWidth,
                update: updateCategoryOrder
            }).disableSelection();
        },
        /** 商品表示設定ページ用 */
        initProductSort: function () {
            const $table = $('#sortable-products');
            if (!$table.length || $table.data('ui-sortable')) return;

            injectPlaceholderStyle('sortable-placeholder-prod');

            $table.sortable({
                cursor: 'move',
                axis: 'y',
                items: '> tr',
                placeholder: 'sortable-placeholder-prod',
                helper: maintainCellWidth,
                update: function () {
                    // ページ側の updateProductOrder があれば呼び出す
                    if (typeof updateProductOrder === 'function') {
                        updateProductOrder();
                    }
                }
            }).disableSelection();
        }
    };

    /* ---------- 内部ユーティリティ ---------- */
    function maintainCellWidth(e, tr) {
        const $orig = tr.children();
        const $helper = tr.clone();
        $helper.children().each(function (idx) {
            $(this).width($orig.eq(idx).outerWidth());
        });
        return $helper;
    }

    function updateCategoryOrder() {
        $('#sortable-categories tr').each(function (idx) {
            const newOrder = idx + 1;
            $(this).find('input[name="display_order[]"]').val(newOrder);
            $(this).find('.display-order-number').text(newOrder);
        });
    }

    function injectPlaceholderStyle(className) {
        const styleId = `placeholder-style-${className}`;
        if (document.getElementById(styleId)) return;
        const css = `.${className}{height:42px;background:#f0f8ff;}`;
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = css;
        document.head.appendChild(style);
    }

    /* 自動初期化 */
    document.addEventListener('DOMContentLoaded', () => {
        SortableManager.initCategorySort();
        SortableManager.initProductSort();
    });

    global.SortableManager = SortableManager;

})(window, window.jQuery); 