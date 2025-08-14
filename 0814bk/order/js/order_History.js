// order/js/order_History.js
(function(){
  "use strict";
  function log(){
    if(window.DEBUG_LEVEL>=3){
      console.log('[ORDER_HISTORY]',...arguments);
    }
  }

  let TAX_RATE = 0.1;

  function fetchTaxRate(){
    return fetch('../admin/adminsetting_registrer.php?section=order_settings')
      .then(r=>r.json()).then(j=>{
        if(j && j.success && j.settings && typeof j.settings['tax rate']!=='undefined'){
          const p=parseFloat(j.settings['tax rate']);
          if(!isNaN(p)) TAX_RATE=p/100;
        }
      }).catch(()=>{});
  }

  async function loadOrderHistory(){
    const listEl = document.getElementById('order-history-list');
    if(!listEl){
      console.error('#order-history-list が見つかりません');
      return;
    }
    listEl.innerHTML = '<div class="loading"><div class="spinner"></div><p>読み込み中...</p></div>';

    if(!window.roomInfo || !roomInfo.room_number){
      listEl.innerHTML = '<p class="error">部屋情報が取得できません。</p>';
      return;
    }
    const roomNumber = roomInfo.room_number;
    log('room_number =', roomNumber);
    try{
      const resp = await fetch('api/lib/order_History_registrer.php?room_number='+encodeURIComponent(roomNumber));
      const data = await resp.json();
      log('API response', data);
      if(!data.success){
        throw new Error(data.error || 'failed');
      }
      await fetchTaxRate();
      renderHistory(data.orders);
    }catch(err){
      console.error(err);
      listEl.innerHTML = '<p class="error">履歴の取得に失敗しました。</p>';
    }
  }

  function renderHistory(orders){
    const listEl = document.getElementById('order-history-list');
    if(!orders || orders.length===0){
      listEl.innerHTML = '<p class="empty">注文履歴がありません</p>';
      return;
    }
    let subtotalAll = 0;
    let html = '';
    orders.forEach(order=>{
      subtotalAll += order.total;
      html += `<div class="order-history-item">\n`+
              `  <div class="order-header">注文#${order.order_id} <span class="date">${order.created_at}</span></div>`;
      order.items.forEach(it=>{
        html += `<div class="order-row">`+
                `<span class="name">${it.product_name}</span>`+
                `<span class="qty">x${it.quantity}</span>`+
                `<span class="price">¥${it.subtotal.toLocaleString()}</span>`+
                `</div>`;
      });
      html += `<div class="order-total">合計: ¥${order.total.toLocaleString()}</div>`+
              `</div>`;
    });
    const taxAll = Math.round(subtotalAll * TAX_RATE);
    const totalAll = subtotalAll + taxAll;
    html += `<div class="order-grand-total">ご利用合計(税抜き): ¥${subtotalAll.toLocaleString()}</div>`;
    html += `<div class="order-grand-total">消費税: ¥${taxAll.toLocaleString()}</div>`;
    html += `<div class="order-grand-total">これまでのご利用合計: <strong>¥${totalAll.toLocaleString()}</strong></div>`;
    listEl.innerHTML = html;
  }

  // ボタンに紐付け
  document.addEventListener('DOMContentLoaded',()=>{
    const btn = document.getElementById('order-history-button');
    if(btn){
      btn.addEventListener('click', loadOrderHistory);
    }
  });

  const cartState = {}; // dummy to locate position (not needed)
})(); 