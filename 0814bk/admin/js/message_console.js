// message_console.js
// ------------------------------------------------------------
// Lumos Lite Console front-end logic
// Version: v20240530
// Author : FG Dev Team
// ------------------------------------------------------------
console.log('Lumos Console JS v20240530');
(function(){
  "use strict";
  if(!window.LUMOS_CONSOLE_CONFIG){console.error('LUMOS_CONSOLE_CONFIG not found');return;}
  const API = LUMOS_CONSOLE_CONFIG.apiEndpoint;
  const POLL = LUMOS_CONSOLE_CONFIG.pollInterval || 5000;

  const roomCardsEl = document.getElementById('roomCards');
  roomCardsEl.classList.add('cards-grid');
  const broadcastAllBtn = document.getElementById('broadcastAllBtn');
  const modalEl     = document.getElementById('messageModal');
  const modalTitle  = document.getElementById('modalRoomTitle');
  const modalInfo   = document.getElementById('modalRoomInfo');
  const msgContainer= document.getElementById('messageContainer');
  const msgInput    = document.getElementById('messageInput');
  const sendBtn     = document.getElementById('sendBtn');
  const msgTypeSel = document.getElementById('messageTypeSelect');
  const templateSel = document.getElementById('templateSelect');
  const editTplBtn  = document.getElementById('editTemplateBtn');
  const richSelect  = document.getElementById('richSelect');

  // Template modal elements
  const tplModalEl  = document.getElementById('templateModal');
  const tplListEl   = document.getElementById('templateList');
  const newTplInput = document.getElementById('newTemplateInput');
  const addTplBtn   = document.getElementById('addTemplateBtn');
  const tplModal    = bootstrap.Modal.getOrCreateInstance(tplModalEl);

  const bsModal     = bootstrap.Modal.getOrCreateInstance(modalEl);

  const RICH_LIST = ['welcome_banner','promo_coupon','checkout_guide']; // 本番はAPI取得

  let currentRoom = '';
  let currentRoomObj = null;
  let currentLineUserId = '';

  let unreadCounts = JSON.parse(localStorage.getItem('lumos_unreadCounts')||'{}');
  let lastReadTimestamps = JSON.parse(localStorage.getItem('lumos_lastReadTimestamps')||'{}');

  function saveUnreadState() {
    localStorage.setItem('lumos_unreadCounts', JSON.stringify(unreadCounts));
    localStorage.setItem('lumos_lastReadTimestamps', JSON.stringify(lastReadTimestamps));
  }

  // --------------------------------------------
  // Fetch helpers
  // --------------------------------------------
  const getJSON = (url)=>(fetch(url,{credentials:'include'}).then(r=>r.json()));
  const postJSON= (data)=>fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify(data)}).then(r=>r.json());

  // --------------------------------------------
  // Room list polling
  // --------------------------------------------
  async function loadRooms(){
    try{
      const res = await getJSON(`${API}?action=rooms`);
      if(!res.success) throw new Error(res.message||'error');
      updateUnreadCounts(res.rooms||[]);
      renderRooms(res.rooms||[]);
    }catch(e){ console.error(e); }
  }

  function updateUnreadCounts(rooms){
    rooms.forEach(room=>{
      room.users.forEach((user, idx) => {
        const lineUserId = room.line_user_ids[idx] || '';
        const key = room.room_number + '|' + lineUserId;
        const lastRead = lastReadTimestamps[key] ? new Date(lastReadTimestamps[key]) : new Date(0);
        let count = 0;
        const latestMsgArr = room.latest_messages && room.latest_messages[idx] ? room.latest_messages[idx] : [];
        latestMsgArr.forEach(m => {
          if(m.sender === 'guest' && new Date(m.created_at) > lastRead){
            count++;
          }
        });
        unreadCounts[key] = count;
      });
    });
    saveUnreadState();
  }

  function renderRooms(rooms){
    // 未読優先＋部屋番号順でソート
    rooms.sort((a, b) => {
      const aKey = a.room_number + '|' + (a.line_user_ids[0] || '');
      const bKey = b.room_number + '|' + (b.line_user_ids[0] || '');
      const aUnread = unreadCounts[aKey] > 0 ? 1 : 0;
      const bUnread = unreadCounts[bKey] > 0 ? 1 : 0;
      if (aUnread !== bUnread) return bUnread - aUnread;
      return a.room_number.localeCompare(b.room_number, 'ja', {numeric:true});
    });
    roomCardsEl.innerHTML='';
    rooms.forEach(room=>{
      room.users.forEach((user, idx) => {
        const lineUserId = room.line_user_ids[idx] || '';
        const key = room.room_number + '|' + lineUserId;
        const period = (room.check_in_date||'')?`${room.check_in_date} ～ ${room.check_out_date}`:'';
        const orderTotal = room.order_totals && room.order_totals[idx] ? room.order_totals[idx] : {formatted: '¥0', count: 0};
        const latestMsgArr = room.latest_messages && room.latest_messages[idx] ? room.latest_messages[idx] : [];
        const latestMsgArrReversed = [...latestMsgArr].reverse();
        const latestMsgHtml = latestMsgArrReversed.length > 0
          ? latestMsgArrReversed.map((m) => {
              let text = (typeof m.text !== 'undefined' && m.text !== null) ? m.text : '';
              if (text.length > 100) text = text.substring(0, 100) + '…';
              const senderClass = m.sender === 'staff' ? 'msg-staff' : 'msg-guest';
              return `<div class="msg-card ${senderClass}">${text}</div>`;
            }).join('')
          : 'メッセージなし';
        const card = document.createElement('div');
        card.className='card card-apple shadow-sm';
        card.style.cursor='pointer';
        card.dataset.room = room.room_number;
        card.dataset.lineUserId = lineUserId;
        card.innerHTML = `
          <div class="card-header fw-bold text-center" style="position:relative;${unreadCounts[key]>0 ? 'background:#e00;color:#fff;' : ''}">
            部屋 ${room.room_number}
            ${unreadCounts[key]>0 ? `<span class='new-badge' style='position:absolute;top:8px;right:12px;background:#fff;color:#e00;border-radius:12px;padding:0 10px;font-size:0.9em;font-weight:bold;border:1px solid #e00;'>新規</span>` : ''}
          </div>
          <div class="card-body d-flex flex-column p-2 gap-1">
            <div class="small info-row"><i class="fa-solid fa-user"></i> ${user}</div>
            <div class="small text-muted info-row"><i class="fa-solid fa-calendar-days"></i> ${period}</div>
            <div class="small info-row order-total-row" style="background-color:#f8f9fa;padding:4px 8px;border-radius:4px;border-left:3px solid #007bff;cursor:pointer;" data-room="${room.room_number}">
              <span class="text-muted">合計金額</span> 
              <span class="fw-bold text-success">${orderTotal.formatted}</span>
              ${orderTotal.count > 0 ? `<span class="text-muted ms-1">(${orderTotal.count}件)</span>` : ''}
              <i class="fa-solid fa-external-link ms-1 text-muted"></i>
            </div>
            <hr class="my-1">
            <div class="flex-grow-1 overflow-auto latest-msg small">${latestMsgHtml}</div>
          </div>
          <div class="card-footer text-end py-1"><span class="badge bg-secondary">${latestMsgArr.length}</span></div>
        `;
        card.addEventListener('click',(e)=>{
          // 合計金額部分のクリックは除外
          if (e.target.closest('.order-total-row')) {
            return;
          }
          openRoom({
            room_number: room.room_number,
            users: [user],
            line_user_ids: [lineUserId],
            check_in_date: room.check_in_date,
            check_out_date: room.check_out_date,
            latest_messages: room.latest_messages
          });
        });
        roomCardsEl.appendChild(card);
      });
    });
    msgTypeSel.dispatchEvent(new Event('change'));
  }

  // --------------------------------------------
  // Modal handling
  // --------------------------------------------
  async function openRoom(roomObj){
    currentRoomObj = roomObj;
    currentRoom = roomObj.room_number;
    currentLineUserId = roomObj.line_user_ids[0];
    modalTitle.textContent = `部屋 ${roomObj.room_number}`;
    modalInfo.textContent = `${roomObj.users[0]} / LINE: ${roomObj.line_user_ids[0]}`;
    // 未読リセット
    const key = currentRoom + '|' + currentLineUserId;
    lastReadTimestamps[key] = new Date().toISOString();
    unreadCounts[key] = 0;
    saveUnreadState();
    await loadMessages();
    bsModal.show();
    focusInput();
  }

  async function loadMessages(){
    try{
      const res = await getJSON(`${API}?action=messages&room_number=${encodeURIComponent(currentRoom)}&line_user_id=${encodeURIComponent(currentLineUserId)}`);
      if(!res.success) throw new Error(res.message||'error');
      renderMessages(res.messages||[]);
    }catch(e){ console.error(e); }
  }

  function renderMessages(list){
    msgContainer.innerHTML='';
    list.forEach(m=>{
      const wrapper = document.createElement('div');
      wrapper.style.display = 'flex';
      wrapper.style.alignItems = 'flex-end';
      wrapper.style.marginBottom = '8px';
      const time = m.created_at ? new Date(m.created_at).toTimeString().slice(0,5) : '';
      const timeElem = document.createElement('div');
      timeElem.className = 'msg-time';
      timeElem.textContent = time;
      timeElem.style.fontSize = '0.8em';
      timeElem.style.color = '#888';
      timeElem.style.margin = '0 8px';
      const bubble = document.createElement('div');
      bubble.className = `bubble ${m.sender==='staff'?'staff':'guest'}`;
      bubble.innerHTML = `<div class="msg-text">${m.text}</div>`;
      if(m.sender === 'staff'){
        wrapper.appendChild(timeElem);
        wrapper.appendChild(bubble);
        wrapper.style.justifyContent = 'flex-end';
      }else{
        wrapper.appendChild(bubble);
        wrapper.appendChild(timeElem);
        wrapper.style.justifyContent = 'flex-start';
      }
      msgContainer.appendChild(wrapper);
    });
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  function focusInput(){ /* no-op */ }

  // --------------------------------------------
  // Send message
  // --------------------------------------------
  // 送信フォームをテキスト入力のみ（テンプレート選択なし）に
  msgTypeSel.innerHTML = '<option value="text">テキスト</option>';
  templateSel.classList.add('d-none');
  richSelect.classList.add('d-none');
  editTplBtn.classList.add('d-none');
  msgInput.classList.remove('d-none');
  // メッセージタイプ変更時のUI切り替えは不要

  // 送信処理
  async function send(){
    let payloadText = msgInput.value.trim();
    if(!payloadText) return;
    msgInput.value='';
    // 送信先を固定
    const url = '/lumos/api/lineMessage_Tx.php';
    // bodyをsend.phpと同じ形式に
    let body = { message: payloadText, to: currentLineUserId };
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(r => r.json()).catch(() => null);
    if(res && res.http_code === 200){
      appendBubble({sender:'staff',text:payloadText});
      focusInput();
    }else{
      alert('送信失敗');
    }
  }

  function appendBubble(m){
    const b = document.createElement('div');
    b.className = `bubble ${m.sender==='staff'?'staff':'guest'}`;
    b.textContent = m.text;
    msgContainer.appendChild(b);
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  // Broadcast all users
  broadcastAllBtn.addEventListener('click', ()=>{
    // 旧モーダルの表示処理は削除
    // send.phpのiframeモーダルのみ表示（Lumos_Lite_Console.php側の既存処理に依存）
    // ここでは何もしない、または必要ならsendModalの表示処理のみ記述
  });

  // Template editing
  editTplBtn.addEventListener('click',()=>{
    loadTemplateList();
    tplModal.show();
  });

  addTplBtn.addEventListener('click',()=>{
    const val = newTplInput.value.trim();
    if(!val) return;
    const list = getTemplates();
    list.push(val);
    saveTemplates(list);
    newTplInput.value='';
    loadTemplateList();
    loadTemplateSelect();
  });

  function getTemplates(){
    try{ return JSON.parse(localStorage.getItem('lumos_templates')||'[]'); }catch(e){ return []; }
  }
  function saveTemplates(arr){ localStorage.setItem('lumos_templates',JSON.stringify(arr)); }

  function loadTemplateList(){
    tplListEl.innerHTML='';
    getTemplates().forEach((tpl,idx)=>{
      const li=document.createElement('li');
      li.className='list-group-item d-flex justify-content-between align-items-center';
      li.textContent = tpl;
      const del=document.createElement('button');
      del.className='btn btn-sm btn-outline-danger';
      del.innerHTML='<i class="fa-solid fa-trash"></i>';
      del.addEventListener('click',()=>{
        const arr=getTemplates(); arr.splice(idx,1); saveTemplates(arr); loadTemplateList(); loadTemplateSelect(); });
      li.appendChild(del);
      tplListEl.appendChild(li);
    });
  }

  msgInput.addEventListener('keydown',e=>{
    if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); send(); }
  });
  sendBtn.addEventListener('click',send);

  // mock receive polling
  setInterval(async()=>{
     try{const res=await getJSON(`${API}?action=mock_receive`); if(res.success){highlightRoom(res.room_number,res.text);} }catch(e){}
  },15000);

  function highlightRoom(roomNumber,text){
    const cards=[...roomCardsEl.querySelectorAll('.card')];
    cards.forEach(c=>{if(c.dataset.room===roomNumber){c.classList.add('pulse');const lm=c.querySelector('.latest-msg');lm.textContent=text;}});
  }

  // --------------------------------------------
  // Start polling
  // --------------------------------------------
  loadRooms();
  setInterval(loadRooms,POLL);

  modalEl.addEventListener('shown.bs.modal',()=>{ msgInput.focus(); });

  // テンプレート作成ボタンの右にアーカイブ展開ボタンを追加
  let archiveBtn = document.getElementById('showArchiveBtn');
  if (!archiveBtn) {
    archiveBtn = document.createElement('button');
    archiveBtn.id = 'showArchiveBtn';
    archiveBtn.className = 'btn btn-secondary btn-sm ms-2';
    archiveBtn.innerHTML = '<i class="fa-solid fa-box-archive"></i> アーカイブを展開';
    const templateBtn = document.getElementById('createTemplateBtn');
    templateBtn.parentNode.insertBefore(archiveBtn, templateBtn.nextSibling);
  }

  // アーカイブリスト用モーダルを追加
  let archiveModal = document.getElementById('archiveModal');
  if (!archiveModal) {
    archiveModal = document.createElement('div');
    archiveModal.id = 'archiveModal';
    archiveModal.className = 'modal fade';
    archiveModal.tabIndex = -1;
    archiveModal.innerHTML = `
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">アーカイブ一覧</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <ul id="archiveList" class="list-group"></ul>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(archiveModal);
  }
  const archiveListEl = document.getElementById('archiveList');
  const archiveModalInstance = bootstrap.Modal.getOrCreateInstance(archiveModal);

  // 注文詳細モーダル
  const orderDetailsModal = document.getElementById('orderDetailsModal');
  const orderDetailsTitle = document.getElementById('orderDetailsTitle');
  const orderDetailsContent = document.getElementById('orderDetailsContent');
  const orderDetailsModalInstance = bootstrap.Modal.getOrCreateInstance(orderDetailsModal);

  archiveBtn.addEventListener('click', async () => {
    archiveListEl.innerHTML = '<li class="list-group-item">読み込み中...</li>';
    try {
      const res = await getJSON(`${API}?action=archived_rooms`);
      if (!res.success) throw new Error(res.message || 'error');
      archiveListEl.innerHTML = '';
      if (!res.rooms || res.rooms.length === 0) {
        archiveListEl.innerHTML = '<li class="list-group-item">アーカイブはありません</li>';
        return;
      }
      res.rooms.forEach(room => {
        const user = room.users[0] || '';
        const lineUserId = room.line_user_ids[0] || '';
        const period = (room.check_in_date||'')?`${room.check_in_date} ～ ${room.check_out_date}`:'';
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.style.cursor = 'pointer';
        li.innerHTML = `
          <div>
            <span class="fw-bold">${user}</span> <span class="text-muted">(${room.room_number})</span><br>
            <span class="small text-muted">${period}</span>
          </div>
          <i class="fa-solid fa-chevron-right"></i>
        `;
        li.addEventListener('click', () => {
          archiveModalInstance.hide();
          openRoom({
            room_number: room.room_number,
            users: room.users,
            line_user_ids: room.line_user_ids,
            check_in_date: room.check_in_date,
            check_out_date: room.check_out_date,
            latest_messages: room.latest_messages
          });
        });
        archiveListEl.appendChild(li);
      });
    } catch (e) {
      archiveListEl.innerHTML = `<li class="list-group-item text-danger">${e.message}</li>`;
    }
    archiveModalInstance.show();
  });

  // 注文詳細表示機能
  async function showOrderDetails(roomNumber) {
    orderDetailsTitle.textContent = `部屋 ${roomNumber} - 注文詳細`;
    orderDetailsContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">読み込み中...</span></div></div>';
    orderDetailsModalInstance.show();

    try {
      const res = await getJSON(`${API}?action=order_details&room_number=${encodeURIComponent(roomNumber)}`);
      if (!res.success) throw new Error(res.message || 'エラーが発生しました');
      
      renderOrderDetails(res);
    } catch (e) {
      orderDetailsContent.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
    }
  }

  function renderOrderDetails(data) {
    const { order_details, summary, room_number } = data;
    
    const statusLabels = {
      ordered: { text: '注文済み（未調理）', class: 'primary', icon: 'clock' },
      ready: { text: '調理済み（未配膳）', class: 'warning', icon: 'check-circle' },
      delivered: { text: '配膳済み', class: 'success', icon: 'truck' },
      cancelled: { text: 'キャンセル済み', class: 'secondary', icon: 'times-circle' }
    };

    let html = `
      <div class="mb-4">
        <h6 class="fw-bold">合計サマリー</h6>
        <div class="row">
          <div class="col-md-3">
            <div class="card text-center border-success">
              <div class="card-body">
                <h5 class="text-success">${summary.formatted_total}</h5>
                <small class="text-muted">合計金額</small>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center">
              <div class="card-body">
                <h5>${summary.total_items}</h5>
                <small class="text-muted">総商品数</small>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="row g-2">
              <div class="col-6">
                <div class="bg-primary text-white p-2 rounded text-center">
                  <div class="fw-bold">${summary.ordered_count}</div>
                  <small>注文済み</small>
                </div>
              </div>
              <div class="col-6">
                <div class="bg-warning text-dark p-2 rounded text-center">
                  <div class="fw-bold">${summary.ready_count}</div>
                  <small>調理済み</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    Object.entries(statusLabels).forEach(([status, config]) => {
      const items = order_details[status] || [];
      if (items.length === 0) return;

      html += `
        <div class="mb-4">
          <h6 class="fw-bold">
            <i class="fa-solid fa-${config.icon} text-${config.class}"></i>
            ${config.text} (${items.length}件)
          </h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>商品名</th>
                  <th>単価</th>
                  <th>数量</th>
                  <th>小計</th>
                  <th>注文者</th>
                  <th>注文日時</th>
                  ${status !== 'ordered' ? '<th>ステータス更新</th>' : ''}
                </tr>
              </thead>
              <tbody>
      `;

      items.forEach(item => {
        const orderDate = new Date(item.created_at).toLocaleString('ja-JP');
        const statusUpdate = item.status_updated_at ? 
          new Date(item.status_updated_at).toLocaleString('ja-JP') : '';
        
        html += `
          <tr class="border-${config.class}">
            <td class="fw-bold">${item.product_name}</td>
            <td>${item.formatted_unit_price}</td>
            <td>
              <span class="badge bg-light text-dark">${item.quantity}</span>
            </td>
            <td class="fw-bold text-${config.class}">${item.formatted_subtotal}</td>
            <td>${item.user_name}</td>
            <td><small>${orderDate}</small></td>
            ${status !== 'ordered' ? `<td><small>${statusUpdate}</small></td>` : ''}
          </tr>
        `;
        
        if (item.note) {
          html += `
            <tr class="border-${config.class}">
              <td colspan="${status !== 'ordered' ? '7' : '6'}" class="small text-muted">
                <i class="fa-solid fa-comment"></i> ${item.note}
              </td>
            </tr>
          `;
        }
      });

      html += `
              </tbody>
            </table>
          </div>
        </div>
      `;
    });

    if (Object.values(order_details).every(arr => arr.length === 0)) {
      html += '<div class="alert alert-info">この部屋には注文がありません。</div>';
    }

    orderDetailsContent.innerHTML = html;
  }

  // 合計金額クリックイベント（イベント委譲）
  document.addEventListener('click', function(e) {
    const orderTotalRow = e.target.closest('.order-total-row');
    if (orderTotalRow) {
      e.preventDefault(); // デフォルト動作を防ぐ
      e.stopPropagation(); // カードクリックイベントを防ぐ
      e.stopImmediatePropagation(); // 同じ要素の他のイベントも防ぐ
      const roomNumber = orderTotalRow.dataset.room;
      showOrderDetails(roomNumber);
      return false; // さらに確実にイベント伝播を停止
    }
  }, true); // キャプチャフェーズで実行
})(); 