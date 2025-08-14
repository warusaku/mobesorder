// inform_Modal.js
// 営業時間外表示モーダル & 部屋登録促しモーダル
// 設定は /admin/adminpagesetting/adminsetting.json の inform_modal セクションから取得

(function(){
    const SETTINGS_URL = '/admin/adminsetting_registrer.php?section=inform_modal';
    let modalSettings = null;
    let roomAlertCfg  = null;

    // フォントをロード（Noto Sans JP Bold）
    const fontLink = document.createElement('link');
    fontLink.href = 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@700&display=swap';
    fontLink.rel  = 'stylesheet';
    document.head.appendChild(fontLink);

    // FontAwesome 6 CSS を動的ロード（まだ読み込まれていなければ）
    function ensureFA(){
        if(document.querySelector('link[data-fa6]')) return;
        const fa = document.createElement('link');
        fa.rel='stylesheet';
        fa.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
        fa.setAttribute('data-fa6','true');
        document.head.appendChild(fa);
    }

    // 設定を取得
    function fetchSettings(){
        return fetch(SETTINGS_URL)
            .then(r=>r.json())
            .then(json=>{
                if(json && json.success && json.settings){
                    modalSettings = json.settings;
                    // ネストされた room_register_alert を抽出
                    if(modalSettings && modalSettings.room_register_alert){
                        roomAlertCfg = modalSettings.room_register_alert;
                    }
                    console.log('[inform_Modal] fetchSettings success:', modalSettings);
                } else {
                    console.warn('inform_Modal.js: 設定取得に失敗しました', json);
                }
                return modalSettings;
            })
            .catch(err=>{
                console.error('inform_Modal.js: 設定取得エラー', err);
                return null;
            });
    }

    // モーダル生成
    function createModal(cfg){
        const overlay = document.createElement('div');
        overlay.className = 'inform-modal-overlay';

        const modal   = document.createElement('div');
        modal.className = 'inform-modal-content';
        modal.style.backgroundColor = cfg.background_color || '#ffffff';
        modal.style.color = cfg.text_color || '#333333';

        // アイコン
        ensureFA();
        let iconEl = null;
        if(cfg.icon_type_fontawesome){
            iconEl = document.createElement('i');
            iconEl.className = cfg.icon_type_fontawesome;
            if(cfg.icon_size){ iconEl.style.fontSize = cfg.icon_size; }
            iconEl.style.display = 'block';
            iconEl.style.margin = '0 auto 12px auto';
            modal.appendChild(iconEl);
        }

        const title  = document.createElement('h2');
        title.className = 'inform-modal-title';
        title.textContent = cfg.title || '';

        const body   = document.createElement('p');
        body.className = 'inform-modal-body';
        body.innerHTML = cfg.message || '';

        // text color override to avoid external CSS interference
        if(cfg.text_color){
            title.style.color = cfg.text_color;
            body.style.color  = cfg.text_color;
        }

        const btn    = document.createElement('button');
        btn.className = 'inform-modal-btn';
        btn.textContent = cfg.button_text || '閉じる';
        btn.addEventListener('click', ()=>{
            document.body.removeChild(overlay);
        });

        let linkMsgEl = null;

        modal.appendChild(title);
        modal.appendChild(body);

        if(cfg.force_link && cfg.force_link_message){
            linkMsgEl = document.createElement('p');
            linkMsgEl.className = 'inform-modal-linkmsg';
            linkMsgEl.textContent = cfg.force_link_message;
            linkMsgEl.style.marginTop = '8px';
            modal.appendChild(linkMsgEl);
        }

        modal.appendChild(btn);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    function showStoreClosedInform(nextOpenTime){
        if(!modalSettings){
            return fetchSettings().then(()=>showStoreClosedInform(nextOpenTime));
        }
        const cfg = Object.assign({}, modalSettings.store_closed || {}, {
            message: (modalSettings.store_closed && modalSettings.store_closed.message) ? modalSettings.store_closed.message.replace('{NEXT_OPEN_TIME}', nextOpenTime||'--:--') : `現在モバイルオーダーは使用できません。次の営業開始は${nextOpenTime}です。`
        });
        createModal(cfg);
    }

    function showRoomRegisterInform(){
        if(!modalSettings){
            return fetchSettings().then(showRoomRegisterInform);
        }
        const cfg = modalSettings.room_register || {title:'部屋番号登録', message:'ご注文前に部屋番号を登録してください。'};
        createModal(cfg);
    }

    // ================ error-container カスタマイズ ================
    function customizeErrorContainer(){
        if(!roomAlertCfg){ console.log('[inform_Modal] roomAlertCfg not set'); return; }
        console.log('[inform_Modal] customizeErrorContainer start', roomAlertCfg);
        const ec = document.getElementById('error-container');
        if(!ec) return;
        const icon = ec.querySelector('i');
        const title = ec.querySelector('h2');
        const msg = ec.querySelector('#error-message');
        const btn = ec.querySelector('#retry-button');

        // 既存エラーコンテナは非表示にし、自前モーダルを表示
        ec.style.display='none';

        createModal(roomAlertCfg);

        if(roomAlertCfg.force_link){
            const timeout = (parseInt(roomAlertCfg.force_link_timeout,10)||5)*1000;
            setTimeout(()=>{ window.location.href = roomAlertCfg.force_link_url || '/register'; }, timeout);
        }
    }

    function observeErrorContainer(){
        const ec = document.getElementById('error-container');
        if(!ec) return;
        const observer = new MutationObserver(()=>{
            if(getComputedStyle(ec).display !== 'none'){
                console.log('[inform_Modal] error-container became visible');
                customizeErrorContainer();
            }
        });
        observer.observe(ec, {attributes:true, attributeFilter:['style']});
    }

    // 公開API
    window.informModal = {
        showStoreClosedInform,
        showRoomRegisterInform
    };

    // DOMContentLoaded 時に設定先読み
    document.addEventListener('DOMContentLoaded', ()=>{
        fetchSettings().then(observeErrorContainer);
    });
})(); 