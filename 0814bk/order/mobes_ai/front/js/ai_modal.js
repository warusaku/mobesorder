/*
 Version: 0.2.0 (2025-05-31)
 File Description: AI チャットモーダルの実装
*/

(function() {
  'use strict';
  console.info('[mobes_ai] ai_modal.js loaded');

  if(document.getElementById('mobesAiButton')) return;
  
  // グローバル変数
  const AI_MODE = { OMK: 'omakase', SOM: 'sommelier', SUG: 'suggest' };
  const MODE_GUIDE = {
    [AI_MODE.OMK]: 'おまかせモードです。人数やご要望を入力してください！',
    [AI_MODE.SOM]: 'ソムリエモードです。お好みのワインやシャンパンのタイプを教えてください！',
    [AI_MODE.SUG]: 'おすすめモードです。シーンやご予算などお気軽にどうぞ！'
  };
  let modalEl;
  let cartItems = [];
  let hasRecommendations = false;
  let currentMode = AI_MODE.OMK;
  const PLACEHOLDER = '/order/images/no-image.png';
  
  // AIボタン作成
  const btn = document.createElement('button');
  btn.id = 'mobesAiButton';
  document.body.appendChild(btn);

  // モーダル作成
  function ensureModal() {
    if(modalEl) return modalEl;
    
    const html = `
      <div id="mobesAiModal" class="mobes-ai-modal" style="display:none;">
        <div class="mobes-ai-card">
          <div class="mobes-ai-header">
            <ul class="ai-tabs">
              <li data-mode="omakase" class="active">おまかせ</li>
              <li data-mode="sommelier">ソムリエ</li>
              <li data-mode="suggest">おすすめ</li>
            </ul>
            <button class="mobes-ai-close">×</button>
          </div>
          <div class="mobes-ai-body" id="mobesAiBody"></div>
          <div class="mobes-ai-footer">
            <button id="mobesAiAddCart" class="add-to-cart-btn" style="display:none;">カートに追加</button>
            <div class="mobes-ai-input" style="margin-top:8px;">
              <textarea id="mobesAiTxt" rows="1" placeholder="メッセージを入力..."></textarea>
              <button id="mobesAiSend">送信</button>
            </div>
          </div>
        </div>
      </div>`;
    
    const div = document.createElement('div');
    div.innerHTML = html;
    modalEl = div.firstElementChild;
    document.body.appendChild(modalEl);
    
    // イベントリスナー
    modalEl.querySelector('.mobes-ai-close').addEventListener('click', closeModal);
    modalEl.addEventListener('click', e => {
      if(e.target === modalEl) closeModal();
    });
    
    // タブイベント
    modalEl.querySelectorAll('.ai-tabs li').forEach(tab=>{
      tab.addEventListener('click', ()=>{
        const mode = tab.dataset.mode;
        if(mode===currentMode) return;
        modalEl.querySelectorAll('.ai-tabs li').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        currentMode = mode;
        resetModal();
        appendMessage('assistant', MODE_GUIDE[mode] || '');
      });
    });
    
    const sendBtn = modalEl.querySelector('#mobesAiSend');
    const txtArea = modalEl.querySelector('#mobesAiTxt');
    
    sendBtn.addEventListener('click', sendChat);
    txtArea.addEventListener('keypress', e => {
      if(e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChat();
      }
    });
    
    modalEl.querySelector('#mobesAiAddCart').addEventListener('click', addCart);
    
    return modalEl;
  }

  function resetModal(){
    cartItems = [];
    hideAddToCartButton();
    const body = document.getElementById('mobesAiBody');
    body.innerHTML = '';
  }

  // モーダル開閉
  function openModal(mode = 'omakase') {
    const el = ensureModal();
    el.style.display = 'flex';
    loadChatHistory();
    loadFirstMessage(mode);
  }

  function closeModal() {
    if(modalEl) modalEl.style.display = 'none';
  }

  // チャット履歴読み込み
  async function loadChatHistory() {
    try {
      const sessionId = sessionStorage.getItem('order_session_id');
      const lineUserId = window.LINE_USER_ID || null;
      
      if(!sessionId && !lineUserId) return;
      
      const res = await fetch('/order/mobes_ai/api/get_chat_history.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          order_session_id: sessionId,
          line_user_id: lineUserId,
          limit: 20
        })
      });
      
      if(!res.ok) return;
      
      const data = await res.json();
      if(data.status === 'ok' && data.messages) {
        const body = document.getElementById('mobesAiBody');
        body.innerHTML = '';
        
        data.messages.forEach(msg => {
          appendMessage(msg.role, msg.message, msg.created_at);
        });
        
        body.scrollTop = body.scrollHeight;
      }
    } catch(e) {
      console.error('Failed to load chat history:', e);
    }
  }

  // 初回メッセージ読み込み
  async function loadFirstMessage(mode) {
    const sessionId = sessionStorage.getItem('order_session_id');
    const lineUserId = window.LINE_USER_ID || null;
    
    // 履歴がある場合は初回メッセージをスキップ
    const body = document.getElementById('mobesAiBody');
    if(body.children.length > 0) return;
    
    showLoading();
    
    try {
      const res = await MobesAiApi.getRecommendations({
        mode,
        order_session_id: sessionId,
        line_user_id: lineUserId
      });
      
      hideLoading();
      
      if(res.status === 'ok') {
        appendMessage('assistant', res.reply);
        
        if(res.items && res.items.length > 0) {
          cartItems = res.items.map(it => ({...it}));
          hasRecommendations = true;
          renderProducts();
          showAddToCartButton();
        }
      } else {
        appendMessage('assistant', '申し訳ありません。接続に問題が発生しました。');
      }
    } catch(e) {
      hideLoading();
      appendMessage('assistant', 'エラーが発生しました。しばらくしてから再度お試しください。');
    }
  }

  // メッセージ追加
  function appendMessage(role, text, timestamp = null) {
    const body = document.getElementById('mobesAiBody');
    const msgDiv = document.createElement('div');
    msgDiv.className = `mobes-ai-message ${role}`;
    
    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'mobes-ai-bubble';
    bubbleDiv.textContent = text;
    
    msgDiv.appendChild(bubbleDiv);
    
    if(timestamp) {
      const timeDiv = document.createElement('div');
      timeDiv.className = 'message-time';
      timeDiv.textContent = formatTime(timestamp);
      msgDiv.appendChild(timeDiv);
    }
    
    body.appendChild(msgDiv);
    body.scrollTop = body.scrollHeight;
  }

  // 商品カード表示
  function renderProducts() {
    const body = document.getElementById('mobesAiBody');
    const container = document.createElement('div');
    container.className = 'product-recommendations';
    
    cartItems.forEach((item, idx) => {
      const card = document.createElement('div');
      card.className = 'product-card';
      const imgSrc = item.image_url && item.image_url.trim()!=='' ? item.image_url : PLACEHOLDER;
      const name = item.name || `商品ID:${item.product_id}`;
      const priceDisp = item.price? `¥${Math.round(item.price).toLocaleString()}`: '';
      card.innerHTML = `
        <img src="${imgSrc}" alt="商品画像" />
        <div class="product-info">
          <h6>${name}</h6>
          <p class="ai-price">${priceDisp}</p>
          <p>数量: <span class="qty-val">${item.qty}</span></p>
        </div>
        <div class="product-controls">
          <div class="qty-control">
            <button data-idx="${idx}" class="qty-minus">−</button>
            <span>${item.qty}</span>
            <button data-idx="${idx}" class="qty-plus">＋</button>
          </div>
          <button data-idx="${idx}" class="del">×</button>
        </div>`;
      
      container.appendChild(card);
      if(imgSrc===PLACEHOLDER){loadProductImage(card, item.product_id);}
    });
    
    body.appendChild(container);
    
    // イベントリスナー
    container.querySelectorAll('.qty-plus').forEach(btn => {
      btn.addEventListener('click', e => {
        const i = parseInt(e.target.dataset.idx);
        cartItems[i].qty++;
        updateQuantity(i);
      });
    });
    
    container.querySelectorAll('.qty-minus').forEach(btn => {
      btn.addEventListener('click', e => {
        const i = parseInt(e.target.dataset.idx);
        if(cartItems[i].qty > 1) {
          cartItems[i].qty--;
          updateQuantity(i);
        }
      });
    });
    
    container.querySelectorAll('.del').forEach(btn => {
      btn.addEventListener('click', e => {
        const i = parseInt(e.target.dataset.idx);
        cartItems.splice(i, 1);
        refreshProducts();
        if(cartItems.length === 0) {
          hideAddToCartButton();
        }
      });
    });
  }

  // 数量更新
  function updateQuantity(idx) {
    const container = document.querySelector('.product-recommendations');
    const card = container.children[idx];
    const span = card.querySelector('.qty-val');
    if(span) span.textContent = cartItems[idx].qty;
    
    // +-ボタンの間の数量表示も更新
    const qtyControl = card.querySelector('.qty-control');
    if(qtyControl) {
      const qtySpan = qtyControl.querySelector('span');
      if(qtySpan) qtySpan.textContent = cartItems[idx].qty;
    }
  }

  // 商品リスト更新
  function refreshProducts() {
    const container = document.querySelector('.product-recommendations');
    if(container) container.remove();
    if(cartItems.length > 0) {
      renderProducts();
    }
  }

  // カートに追加
  function addCart() {
    if(cartItems.length === 0) return;

    const btn = modalEl.querySelector('#mobesAiAddCart');
    btn.disabled = true;
    btn.textContent = '追加中...';

    try {
      cartItems.forEach(ci => {
        const prod = {
          id: ci.product_id,
          name: ci.name || '',
          price: ci.price || 0,
          image_url: ci.image_url || ''
        };
        if(typeof addToCart === 'function'){
          addToCart(prod, ci.qty);
        }
      });

      appendMessage('assistant', 'カートに追加しました！');
      cartItems = [];
      refreshProducts();
      hideAddToCartButton();
      setTimeout(closeModal, 1500);
    } catch(e){
      console.error('AI addCart error', e);
      appendMessage('assistant', 'カートへの追加に失敗しました。');
    } finally {
      btn.disabled = false;
      btn.textContent = 'カートに追加';
    }
  }

  // チャット送信
  async function sendChat() {
    const txt = document.getElementById('mobesAiTxt');
    const msg = txt.value.trim();
    if(!msg) return;
    
    appendMessage('user', msg);
    txt.value = '';
    
    const sessionId = sessionStorage.getItem('order_session_id');
    const lineUserId = window.LINE_USER_ID || null;
    
    // ユーザーメッセージ保存
    try {
      await MobesAiApi.saveMessage({
        role: 'user',
        message: msg,
        order_session_id: sessionId,
        line_user_id: lineUserId
      });
    } catch(e) {
      console.error('Failed to save message:', e);
    }
    
    showLoading();
    
    try {
      const res = await MobesAiApi.getRecommendations({
        mode: currentMode,
        order_session_id: sessionId,
        line_user_id: lineUserId,
        history: [msg]
      });
      
      hideLoading();
      
      if(res.status === 'ok') {
        appendMessage('assistant', res.reply);
        
        if(res.items && res.items.length > 0) {
          cartItems = res.items.map(it => ({...it}));
          hasRecommendations = true;
          refreshProducts();
          showAddToCartButton();
        }
      } else {
        appendMessage('assistant', '申し訳ありません。応答の生成に失敗しました。');
      }
    } catch(e) {
      hideLoading();
      appendMessage('assistant', 'エラーが発生しました。');
    }
  }

  // 商品画像読み込み
  async function loadProductImage(card, productId) {
    try {
      const res = await fetch(`/order/api/get-product-details.php?id=${productId}`);
      if(!res.ok) return;
      
      const data = await res.json();
      if(data && data.image_url) {
        card.querySelector('img').src = data.image_url;
      }
    } catch(e) {
      console.error('Failed to load product image:', e);
    }
  }

  // カート追加ボタン表示制御
  function showAddToCartButton() {
    const btn = modalEl.querySelector('#mobesAiAddCart');
    btn.style.display = 'block';
  }

  function hideAddToCartButton() {
    const btn = modalEl.querySelector('#mobesAiAddCart');
    btn.style.display = 'none';
  }

  // ローディング表示
  let typingBubble = null;
  function showLoading() {
    if(typingBubble) return;
    typingBubble = document.createElement('div');
    typingBubble.className = 'mobes-ai-message assistant typing';
    typingBubble.innerHTML = '<div class="mobes-ai-bubble"><span class="dotting"></span></div>';
    document.getElementById('mobesAiBody').appendChild(typingBubble);
    document.getElementById('mobesAiBody').scrollTop = document.getElementById('mobesAiBody').scrollHeight;
  }

  function hideLoading() {
    if(typingBubble){ typingBubble.remove(); typingBubble=null; }
  }

  // タイムスタンプフォーマット
  function formatTime(timestamp) {
    const date = new Date(timestamp);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
  }

  // ボタンクリックイベント
  btn.addEventListener('click', () => openModal());

})(); 