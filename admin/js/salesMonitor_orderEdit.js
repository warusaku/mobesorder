/**
 * 注文編集モジュール
 * バージョン: 1.0.0
 * ファイル説明: sales_monitor.php の部屋ごとの注文情報で、注文詳細の編集・保存を担当する JS。
 * 依存: Bootstrap 5, FontAwesome 6 (アイコン), fetch API
 */

(function(){
    'use strict';

    /* ===== ユーティリティ ===== */
    function formatJPY(val){
        return '¥' + Number(val).toLocaleString('ja-JP');
    }

    function showToast(msg){
        if (typeof showNotification === 'function'){
            showNotification(msg);
        }else{
            alert(msg);
        }
    }

    /* ===== 編集モード切替 ===== */
    document.querySelectorAll('.edit-order-btn').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation(); // 行クリックとの衝突回避
            const orderId = this.dataset.orderId;
            enterEditMode(orderId);
        });
    });

    function enterEditMode(orderId){
        const editBtn  = document.querySelector('.edit-order-btn[data-order-id="'+orderId+'"]');
        const saveBtn  = document.querySelector('.save-order-btn[data-order-id="'+orderId+'"]');
        const cancelBtn = document.querySelector('.cancel-edit-btn[data-order-id="'+orderId+'"]');
        if(!editBtn || !saveBtn){return;}
        editBtn.classList.add('d-none');
        saveBtn.classList.remove('d-none');
        if(cancelBtn) cancelBtn.classList.remove('d-none');
        
        // 編集モードを通知
        if(typeof window.setEditMode === 'function'){
            window.setEditMode(true);
        }

        // 対象明細行の操作ボタンを表示
        document.querySelectorAll('#details-'+orderId+' .order-detail-row').forEach(function(r){
            r.querySelectorAll('.qty-minus, .qty-plus, .delete-detail-btn').forEach(function(b){
                b.classList.remove('d-none');
            });
        });

        // save ボタンにリスナを付与 (一度だけ)
        if(!saveBtn.dataset.listener){
            saveBtn.addEventListener('click',function(e){
                e.stopPropagation();
                saveOrderEdit(orderId);
            });
            saveBtn.dataset.listener = '1';
        }
        
        // cancel ボタンにリスナを付与
        if(cancelBtn && !cancelBtn.dataset.listener){
            cancelBtn.addEventListener('click',function(e){
                e.stopPropagation();
                exitEditMode(orderId);
            });
            cancelBtn.dataset.listener = '1';
        }
    }
    
    function exitEditMode(orderId){
        const editBtn  = document.querySelector('.edit-order-btn[data-order-id="'+orderId+'"]');
        const saveBtn  = document.querySelector('.save-order-btn[data-order-id="'+orderId+'"]');
        const cancelBtn = document.querySelector('.cancel-edit-btn[data-order-id="'+orderId+'"]');
        
        if(editBtn) editBtn.classList.remove('d-none');
        if(saveBtn) saveBtn.classList.add('d-none');
        if(cancelBtn) cancelBtn.classList.add('d-none');
        
        // 編集モード終了を通知
        if(typeof window.setEditMode === 'function'){
            window.setEditMode(false);
        }
        
        // 操作ボタンを非表示
        document.querySelectorAll('#details-'+orderId+' .order-detail-row').forEach(function(r){
            r.querySelectorAll('.qty-minus, .qty-plus, .delete-detail-btn').forEach(function(b){
                b.classList.add('d-none');
            });
        });
    }
    
    // キャンセルボタンの処理を追加
    document.querySelectorAll('.cancel-edit-btn').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            const orderId = this.dataset.orderId;
            exitEditMode(orderId);
        });
    });

    /* ===== 数量 +/- & 削除 ===== */
    document.querySelectorAll('.qty-minus').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            const row = this.closest('.order-detail-row');
            updateQuantityDisplay(row, -1);
        });
    });

    document.querySelectorAll('.qty-plus').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            const row = this.closest('.order-detail-row');
            updateQuantityDisplay(row, +1);
        });
    });

    function updateQuantityDisplay(row, diff){
        if(!row) return;
        const qtySpan = row.querySelector('.qty-value');
        const subtotalCell = row.querySelector('.subtotal-cell');
        const unitPriceCell = row.children[3];
        const unitPrice = Number(unitPriceCell.textContent.replace(/[^0-9.-]/g,''));
        let qty = parseInt(qtySpan.textContent,10) || 0;
        qty = qty + diff;
        if(qty < 0) qty = 0;
        qtySpan.textContent = qty;
        subtotalCell.textContent = formatJPY(unitPrice * qty);
    }

    // ゴミ箱 (削除) ボタン
    document.querySelectorAll('.delete-detail-btn').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            const row = this.closest('.order-detail-row');
            if(row){
                row.classList.add('table-danger');
                row.dataset.deleted = '1';
            }
        });
    });

    /* ===== 保存処理 ===== */
    function gatherEdits(orderId){
        const edits = [];
        document.querySelectorAll('#details-'+orderId+' .order-detail-row').forEach(function(row){
            const detailId = parseInt(row.dataset.detailId,10);
            const deleted  = row.dataset.deleted === '1';
            const qty      = parseInt(row.querySelector('.qty-value').textContent,10) || 0;
            edits.push({ detail_id: detailId, quantity: qty, delete: deleted });
        });
        return edits;
    }

    function saveOrderEdit(orderId){
        if(!confirm(`注文ID:${orderId} の編集を確定しますか？`)){
            return;
        }
        
        // 編集モード終了
        if(typeof window.setEditMode === 'function'){
            window.setEditMode(false);
        }
        
        const items = gatherEdits(orderId);
        fetch('api/edit_order.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ order_id: orderId, items })
        }).then(r=>{
            if (!r.ok) {
                // HTTPエラーの場合、レスポンスを読んでからエラーをスロー
                return r.text().then(text => {
                    // レスポンスが空の場合の処理
                    if (!text || text.trim() === '') {
                        throw new Error('サーバーから空のレスポンスが返されました（PHPエラーの可能性があります）');
                    }
                    try {
                        const data = JSON.parse(text);
                        throw new Error(data.message || 'サーバーエラー');
                    } catch (e) {
                        if (e instanceof SyntaxError) {
                            throw new Error('サーバーエラー: ' + text.substring(0, 200));
                        }
                        throw e;
                    }
                });
            }
            return r.json();
        })
        .then(function(res){
            if(res.success){
                showToast('保存しました');
                setTimeout(()=>window.location.reload(), 800);
            }else{
                showToast('エラー:'+ (res.message||'保存に失敗'));
            }
        }).catch(function(err){
            console.error('エラー詳細:', err);
            showToast('通信エラー:'+ err.message);
        });
    }
})(); 