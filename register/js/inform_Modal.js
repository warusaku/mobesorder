// register/js/inform_Modal.js
// 部屋登録ページ向けモーダル制御ユーティリティ
// order/js/inform_Modal.js をベースに、register 用の追加アラートキーを扱えるよう汎用化

(function(){
    const SETTINGS_URL = '/admin/adminsetting_registrer.php?section=inform_modal';
    let modalSettings = null;
    let modalRedirectTimer = null; // 既存のリダイレクトタイマーを保持

    // --- 外部リソースロード ----------------------------------------
    const fontLink = document.createElement('link');
    fontLink.href = 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@700&display=swap';
    fontLink.rel  = 'stylesheet';
    document.head.appendChild(fontLink);

    function ensureFA(){
        if(document.querySelector('link[data-fa6]')) return;
        const fa = document.createElement('link');
        fa.rel='stylesheet';
        fa.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
        fa.setAttribute('data-fa6','true');
        document.head.appendChild(fa);
    }

    // --- 設定 -------------------------------------------------------
    function fetchSettings(){
        if(modalSettings) return Promise.resolve(modalSettings);
        return fetch(SETTINGS_URL)
            .then(r=>r.json())
            .then(j=>{ if(j && j.success && j.settings){ modalSettings=j.settings; } else { console.warn('[informModal] 設定取得失敗',j);} return modalSettings; })
            .catch(err=>{ console.error('[informModal] fetchSettings error',err); return null; });
    }

    // --- モーダル生成 ----------------------------------------------
    function createModal(cfg, modalKey=''){
        // 既にタイマーが動いていればクリア
        if(modalRedirectTimer){
            clearTimeout(modalRedirectTimer);
            modalRedirectTimer = null;
        }

        ensureFA();
        const overlay = document.createElement('div');
        overlay.className='inform-modal-overlay';
        Object.assign(overlay.style,{position:'fixed',top:0,left:0,width:'100%',height:'100%',background:'rgba(0,0,0,0.5)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:9999});

        const modal = document.createElement('div');
        modal.className='inform-modal-content';
        Object.assign(modal.style,{maxWidth:'350px',padding:'24px',borderRadius:'8px',textAlign:'center',fontFamily:'"Noto Sans JP",sans-serif',backgroundColor:cfg.background_color||'#fff',color:cfg.text_color||'#333'});

        if(cfg.icon_type_fontawesome){
            const i=document.createElement('i');
            i.className=cfg.icon_type_fontawesome;
            i.style.fontSize=cfg.icon_size||'48px';
            i.style.marginBottom='12px';
            modal.appendChild(i);
        }

        if(cfg.title){
            const h=document.createElement('h2');
            h.textContent=cfg.title;
            h.style.margin='0 0 8px 0';
            modal.appendChild(h);
        }
        if(cfg.message){
            const p=document.createElement('p');
            p.innerHTML=cfg.message;
            p.style.margin='0 0 16px 0';
            modal.appendChild(p);
        }
        const btn=document.createElement('button');
        btn.className='inform-modal-btn';
        btn.textContent=cfg.button_text||'OK';
        Object.assign(btn.style,{padding:'8px 24px',border:'none',borderRadius:'4px',background:'#007bff',color:'#fff',fontSize:'16px',cursor:'pointer'});
        btn.addEventListener('click',()=>{document.body.removeChild(overlay);});
        modal.appendChild(btn);

        // 強制リンク
        // 厳密な真偽値チェック（"0", "false", false, 0 などを偽と判定）
        const isForceLink = cfg.force_link === true || cfg.force_link === "true" || cfg.force_link === "on" || cfg.force_link === 1 || cfg.force_link === "1";

        // --- デバッグログ ---
        try {
            console.log('[inform_Modal] createModal', {
                key: modalKey,
                force_link_raw: cfg.force_link,
                isForceLink,
                timeout: cfg.force_link_timeout,
                url: cfg.force_link_url
            });
        } catch(e){}

        if(isForceLink){
            const timeout = (parseInt(cfg.force_link_timeout,10)||5)*1000;
            const linkMsg = document.createElement('p');
            linkMsg.textContent = cfg.force_link_message||'';
            linkMsg.style.marginTop='8px';
            modal.appendChild(linkMsg);
            // 新たなタイマーをセット
            modalRedirectTimer = setTimeout(()=>{ window.location.href = cfg.force_link_url || '/register'; }, timeout);
        }

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    // --- プレースホルダー置換 ---------------------------------------
    function applyVars(str, vars){
        if(!str) return str;
        return str.replace(/\{(.*?)\}/g,(m,k)=> (vars && vars[k]!==undefined)? vars[k]: m);
    }

    // --- 公開 API ----------------------------------------------------
    async function showModal(key, vars={}){
        await fetchSettings();
        if(!modalSettings || !modalSettings[key]){ console.warn('[informModal] no config for',key); return; }
        const raw = modalSettings[key];
        const cfg = Object.assign({}, raw);
        cfg.title   = applyVars(raw.title, vars);
        cfg.message = applyVars(raw.message, vars);
        createModal(cfg, key);
    }

    window.registerInformModal = {
        show: showModal
    };

})(); 