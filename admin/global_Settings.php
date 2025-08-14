<?php
/**
 * global_Settings.php
 *
 * システム全体の設定管理ページ。
 *   (1) adminsetting_registrer.php が扱う adminsetting.json
 *   (2) advanced_Setting_registrer.php が扱う api/config/config.php の define 定数
 *
 * 画面ロード時に両方の設定を取得し JSON 形式で表示。
 * 編集後「更新」ボタンで POST し反映する。更新時は確認モーダルを表示。
 */

$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Utils.php';

session_start();

// ===== 共通ヘッダー =====
$pageTitle = 'システム詳細設定';
require_once __DIR__ . '/inc/admin_header.php';

if (!$isLoggedIn) {
    require_once __DIR__ . '/inc/admin_footer.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システム詳細設定 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        textarea.settings-editor {
            width: 100%;
            min-height: 300px;
            font-family: "Courier New", monospace;
            font-size: 0.85rem;
        }
        /* Apple-like Section Styling */
        .settings-section{
            background:#f9f9fb;
            border-radius:12px;
            padding:16px 20px;
            margin-bottom:24px;
        }
        /* ネストされたセクションは白背景 + 枠線で奥行きを演出 */
        .settings-section .settings-section{
            background:#ffffff;
            border:1px solid #e5e5e9;
        }
        .section-title{
            font-size:1.05rem;
            font-weight:600;
            margin-bottom:6px;
        }
        .section-desc{
            font-size:0.85rem;
            color:#6c757d;
            margin-bottom:12px;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- システム詳細設定 -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="m-0">システム詳細設定 (adminsetting.json)</h3>
            <button class="btn btn-primary btn-sm" id="saveSystemBtn"><i class="bi bi-save"></i> 更新</button>
        </div>
        <div class="card-body">
            <!-- mobes_ai専用UI -->
            <div id="mobesAiSection" class="mb-4" style="display:none;">
                <h4 class="mb-3"><i class="bi bi-robot"></i> AI設定 (mobes_ai)</h4>
                
                <!-- タブナビゲーション -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="prompts-tab" data-bs-toggle="tab" data-bs-target="#prompts" type="button">プロンプト設定</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="meta-desc-tab" data-bs-toggle="tab" data-bs-target="#meta-desc" type="button">商品詳細情報</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="other-settings-tab" data-bs-toggle="tab" data-bs-target="#other-settings" type="button">その他の設定</button>
                    </li>
                </ul>
                
                <!-- タブコンテンツ -->
                <div class="tab-content">
                    <!-- プロンプト設定タブ -->
                    <div class="tab-pane fade show active" id="prompts" role="tabpanel">
                        <div id="promptsContainer"></div>
                    </div>
                    
                    <!-- 商品詳細情報タブ -->
                    <div class="tab-pane fade" id="meta-desc" role="tabpanel">
                        <div class="mb-3">
                            <button class="btn btn-success btn-sm" id="addMetaDescBtn">
                                <i class="bi bi-plus-circle"></i> 新規商品説明を追加
                            </button>
                            <span class="ms-3 text-muted">
                                <i class="bi bi-info-circle"></i> 商品はカテゴリーから選択できます
                            </span>
                        </div>
                        <div id="metaDescContainer"></div>
                    </div>
                    
                    <!-- その他の設定タブ -->
                    <div class="tab-pane fade" id="other-settings" role="tabpanel">
                        <div id="otherSettingsContainer"></div>
                    </div>
                </div>
            </div>
            
            <!-- 通常のフォーム（mobes_ai以外） -->
            <form id="systemForm" class="mb-3"></form>
            <details>
                <summary>raw JSON を表示 / 隠す</summary>
                <textarea id="systemSettings" class="settings-editor" spellcheck="false"></textarea>
            </details>
        </div>
    </div>

    <!-- Advanced Setting -->
    <div class="card mb-5">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="m-0">Advanced Setting (config.php)</h3>
            <button class="btn btn-danger btn-sm" id="saveAdvancedBtn"><i class="bi bi-save"></i> 更新</button>
        </div>
        <div class="card-body">
            <!-- フラットフォーム -->
            <form id="advancedForm" class="mb-3"></form>
            <details>
                <summary>raw config 定義を表示 / 隠す</summary>
                <textarea id="advancedSettings" class="settings-editor" spellcheck="false"></textarea>
            </details>
            <div class="form-text">※ config.php の define 定数が対象です。編集は十分注意してください。</div>
        </div>
    </div>
</div>

<!-- 確認モーダル -->
<div class="modal" id="confirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">設定更新の確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>設定を更新します。システム動作に影響する場合があります。続行しますか？</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn btn-primary" id="confirmUpdateBtn">更新する</button>
      </div>
    </div>
  </div>
</div>

<!-- 商品選択モーダル -->
<div class="modal" id="productSelectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">商品を選択</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">カテゴリーを選択</label>
                    <select class="form-select" id="categorySelect">
                        <option value="">カテゴリーを選択してください...</option>
                        <option value="all">全てのカテゴリー</option>
                    </select>
                </div>
                <div class="mb-3" id="productSelectContainer" style="display:none;">
                    <label class="form-label">商品を選択</label>
                    <select class="form-select" id="productSelect" size="10">
                        <option value="">商品を選択してください...</option>
                    </select>
                    <div class="form-text" id="productInfo"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="confirmProductBtn" disabled>選択</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const systemTA   = document.getElementById('systemSettings');
    const advancedTA = document.getElementById('advancedSettings');
    const saveSysBtn = document.getElementById('saveSystemBtn');
    const saveAdvBtn = document.getElementById('saveAdvancedBtn');
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    let pendingType = null; // 'system' or 'advanced'
    const systemForm = document.getElementById('systemForm');
    const advancedForm = document.getElementById('advancedForm');

    function detectType(val, keyPath=''){
        if(typeof val==='boolean') return 'checkbox';
        if(typeof val==='number' && (val===0||val===1)) return 'checkboxNum';
        if(typeof val==='string'){
            const v=val.toLowerCase();
            if(v==='true'||v==='false') return 'checkboxStr';
            if(/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(val)) return 'color';
            if(/^(?:[01]?\d|2[0-3]):[0-5]\d$/.test(val)) return 'time';
        }
        return 'text';
    }

    function createInput(name,value,desc=''){
        const div = document.createElement('div');
        const type = detectType(value,name);
        // チェックボックス系は form-check スタイルを利用
        if(type==='checkbox'||type==='checkboxStr'||type==='checkboxNum'){
            div.className = 'form-check form-switch mb-3';
        }else{
            div.className = 'mb-3';
        }

        // ------ ラベル生成 ------
        const label = document.createElement('label');
        const inputId = 'fld_'+name.replace(/[^a-zA-Z0-9_\-]/g,'_');
        const friendly = (labelMappings[name] || name.split('.').pop()).replace(/_/g,' ');
        // アイコンは入力タイプに応じて切替
        let iconCls = 'bi-pencil-square';
        if(type==='checkbox'||type==='checkboxStr'||type==='checkboxNum') iconCls = 'bi-toggle2-on';
        else if(type==='color') iconCls = 'bi-palette-fill';
        else if(type==='time') iconCls = 'bi-clock';
        label.innerHTML = `<i class="bi ${iconCls} me-1"></i> ${friendly} <span class="small text-muted">(${name})</span>`;
        label.className = (type==='checkbox'||type==='checkboxStr'||type==='checkboxNum') ? 'form-check-label fw-semibold' : 'form-label fw-semibold';
        label.htmlFor = inputId;

        // ------ 入力生成 ------
        let input;
        if(type==='checkbox'||type==='checkboxStr'||type==='checkboxNum'){
            input = document.createElement('input');
            input.type='checkbox';
            input.className='form-check-input';
            input.name=name;
            input.id=inputId;
            const valBool = (typeof value==='boolean')?value:(String(value)==='1'||String(value).toLowerCase()==='true');
            input.checked=valBool;
            // hidden を追加して未チェック時に値を送信
            const hidden=document.createElement('input'); hidden.type='hidden'; hidden.name=name; hidden.value='0';
            div.appendChild(hidden);
            div.appendChild(input);
            div.appendChild(label);
        }else if(type==='color'){
            input=document.createElement('input');
            input.type='color';
            input.className='form-control form-control-color';
            input.name=name; input.value=value; input.id=inputId;
            div.appendChild(label);
            div.appendChild(input);
        }else if(type==='time'){
            input=document.createElement('input');
            input.type='time'; input.className='form-control form-control-sm'; input.name=name; input.value=value; input.id=inputId;
            div.appendChild(label);
            div.appendChild(input);
        }else{
            input=document.createElement('input');
            input.type='text'; input.className='form-control form-control-sm'; input.name=name; input.value=value; input.id=inputId;
            div.appendChild(label);
            div.appendChild(input);
        }

        // ------ 説明文 ------
        if(desc){
            const small=document.createElement('small');
            small.className='form-text text-muted';
            small.textContent=desc;
            div.appendChild(small);
        }
        return div;
    }

    function getDescription(path, rootObj){
        const parts=path.split('.');
        let curr=rootObj, desc='';
        for(const p of parts){
            if(curr[p]!==undefined){
                curr=curr[p];
                if(typeof curr==='object' && curr!==null && curr.description){
                    desc=curr.description;
                }
            }else{break;}
        }
        return desc;
    }

    function flatten(obj,prefix='',res={}){
        for(const k in obj){
            if(k==='setting_name' || k==='description') continue; // メタデータは除外
            const val = obj[k];
            const path = prefix?prefix+'.'+k:k;
            if(Array.isArray(val)){
                val.forEach((v,i)=>{res[path+'.'+i]=v;});
            }else if(typeof val==='object' && val!==null){
                flatten(val,path,res);
            }else{
                res[path]=val;
            }
        }
        return res;
    }

    function unflatten(flat){
        const result={};
        for(const path in flat){
            const parts=path.split('.');
            let cur=result;
            for(let i=0;i<parts.length;i++){
                const p=parts[i];
                if(i===parts.length-1){cur[p]=flat[path];}
                else{cur[p]=cur[p]||{};cur=cur[p];}
            }
        }
        return result;
    }

    const advDescriptions = {
        'DB_HOST':'データベースホスト (MySQL サーバアドレス)',
        'DB_NAME':'データベース名',
        'DB_USER':'データベース接続ユーザー',
        'DB_PASS':'データベース接続パスワード',
        'SQUARE_ACCESS_TOKEN':'Square アクセストークン',
        'SQUARE_LOCATION_ID':'Square ロケーションID',
        'SQUARE_ENVIRONMENT':'Square 環境 (production / sandbox)',
        'LINE_CHANNEL_ACCESS_TOKEN':'LINE チャンネルアクセストークン',
        'LINE_CHANNEL_SECRET':'LINE チャンネルシークレット',
        'BASE_URL':'API ベースURL',
        'CORS_ALLOWED_ORIGINS':'CORS 許可オリジン(カンマ区切り)',
        'LOG_LEVEL':'ログレベル',
        'ADMIN_KEY':'管理者キー',
        'SYNC_TOKEN':'同期トークン'
    };

    const topTitles={
        'admin_setting':'管理者設定',
        'product_display_util':'商品表示設定',
        'open_close':'営業時間設定',
        'register_settings':'部屋登録設定',
        'order_settings':'注文設定',
        'login_settings':'ログイン設定',
        'inform_modal':'通知モーダル',
        'square_settings':'スクエア設定',
        'order_webhooks':'注文Webhook',
        'lumos_console':'Lumosコンソール',
        'mobes_ai':'AI設定'
    };

    const labelMappings={
        'product_display_util.directlink_baseURL':'メニューURLベース',
        'open_close.default_open':'デフォルト開店時間',
        'open_close.default_close':'デフォルト閉店時間',
        'open_close.Restrict individual':'個別制限適用',
        'order_settings.tax_rate':'税率',
        'admin_setting.admin_mail':'管理者メールアドレス',
        // mobes_ai設定のラベル
        'mobes_ai.prompt':'基本システムプロンプト',
        'mobes_ai.gemini_api_key':'Gemini APIキー',
        'mobes_ai.gemini_model_id':'Geminiモデル',
        'mobes_ai.basicstyle':'基本的な文体',
        'mobes_ai.rate_limit_pm':'レート制限(分あたり)',
        'mobes_ai.use_order_history':'注文履歴利用',
        'mobes_ai.stock lock':'在庫ロック',
        'mobes_ai.mode_prompts.omakase':'おまかせモード プロンプト',
        'mobes_ai.mode_prompts.sommelier':'ソムリエモード プロンプト',
        'mobes_ai.mode_prompts.suggest':'おすすめモード プロンプト',
        'mobes_ai.history_prompts.omakase.has_history':'おまかせ: 履歴ありプロンプト',
        'mobes_ai.history_prompts.omakase.no_history':'おまかせ: 履歴なしプロンプト',
        'mobes_ai.history_prompts.sommelier.has_wine_history':'ソムリエ: ワイン履歴ありプロンプト',
        'mobes_ai.history_prompts.sommelier.no_history':'ソムリエ: 履歴なしプロンプト',
        'mobes_ai.history_prompts.suggest.has_history':'おすすめ: 履歴ありプロンプト',
        'mobes_ai.history_prompts.suggest.no_history':'おすすめ: 履歴なしプロンプト'
        // 追加項目はここに
    };

    /**
     * JSON 階層を再帰的にセクションとして描画
     * @param {Object} obj       対象オブジェクト
     * @param {HTMLElement} el   追加先コンテナ
     * @param {String} path      現在のパス (dot 連結)
     * @param {Object} rootObj   ルート JSON 全体 (説明取得用)
     */
    function buildSection(obj, el, path='', rootObj=obj){
        Object.entries(obj).forEach(([key, val])=>{
            if(key==='setting_name' || key==='description') return; // メタはUIに直接は表示しない

            const currPath = path ? `${path}.${key}` : key;

            if(typeof val==='object' && val!==null && !Array.isArray(val)){
                // ----- セクション（オブジェクト） -----
                const section=document.createElement('div');
                section.className='settings-section';

                // タイトル
                const title=document.createElement('div');
                title.className='section-title';
                title.textContent= val.setting_name || key;
                section.appendChild(title);

                // 説明
                if(val.description){
                    const descEl=document.createElement('div');
                    descEl.className='section-desc';
                    descEl.textContent=val.description;
                    section.appendChild(descEl);
                }

                // 再帰的に子要素を構築
                buildSection(val, section, currPath, rootObj);

                el.appendChild(section);
            }else{
                if(Array.isArray(val)){
                    val.forEach((item,idx)=>{
                        const itemPath = `${currPath}.${idx}`;
                        const desc=getDescription(itemPath, rootObj);
                        el.appendChild(createInput(itemPath, item, desc));
                    });
                }else{
                    // ----- プリミティブ値：入力生成 -----
                    const desc=getDescription(currPath, rootObj);
                    el.appendChild(createInput(currPath, val, desc));
                }
            }
        });
    }

    function buildSystemForm(settings){
        systemForm.innerHTML='';
        
        // mobes_ai設定は専用UIで処理
        const mobesAiSection = document.getElementById('mobesAiSection');
        if (settings.mobes_ai) {
            mobesAiSection.style.display = 'block';
            buildMobesAiUI(settings.mobes_ai);
            
            // mobes_ai以外の設定を通常フォームに構築
            const otherSettings = {...settings};
            delete otherSettings.mobes_ai;
            buildSection(otherSettings, systemForm, '', otherSettings);
        } else {
            mobesAiSection.style.display = 'none';
            buildSection(settings, systemForm, '', settings);
        }
    }

    function loadSettings(){
        fetch('adminsetting_registrer.php')
          .then(r=>r.json())
          .then(d=>{ if(d.success){
                const settings=d.settings; systemTA.value=JSON.stringify(settings,null,2);
                buildSystemForm(settings);
          }}).catch(e=>console.error(e));

        fetch('advanced_Setting_registrer.php')
          .then(r=>r.json())
          .then(d=>{ if(d.success){
                advancedTA.value=JSON.stringify(d.settings,null,2);
                Object.entries(d.settings).forEach(([k,v])=>{
                       const desc=advDescriptions[k]||'';
                       advancedForm.appendChild(createInput(k,v,desc));
                });
          }}).catch(e=>console.error(e));
    }

    loadSettings();

    // ボタンイベント
    saveSysBtn.addEventListener('click', ()=>{ pendingType='system'; confirmModal.show(); });
    saveAdvBtn.addEventListener('click', ()=>{ pendingType='advanced'; confirmModal.show(); });

    document.getElementById('confirmUpdateBtn').addEventListener('click', ()=>{
        if(pendingType==='system') updateSystem();
        if(pendingType==='advanced') updateAdvanced();
        confirmModal.hide();
    });

    function updateSystem(){
        // gather inputs
        const flat = {};
        new FormData(systemForm).forEach((v,k)=>{
            const elem=systemForm.querySelector(`[name="${k}"]`);
            if(elem&&elem.type==='checkbox'){
                flat[k]=elem.checked?1:0;
            }else{ flat[k]=v; }
        });
        
        // mobes_ai設定も収集
        document.querySelectorAll('#mobesAiSection input, #mobesAiSection textarea').forEach(elem => {
            if (elem.name) {
                if (elem.type === 'checkbox') {
                    flat[elem.name] = elem.checked ? 1 : 0;
                } else {
                    flat[elem.name] = elem.value;
                }
            }
        });
        
        // meta_descriptionの特別処理（削除された項目を除外）
        const metaDescriptions = {};
        document.querySelectorAll('#metaDescContainer .card').forEach(card => {
            const productName = card.querySelector('.product-name').value;
            const description = card.querySelector('.meta-description').value;
            if (productName && description) {
                metaDescriptions[productName] = description;
            }
        });
        
        // meta_descriptionを手動で設定
        if (Object.keys(metaDescriptions).length > 0) {
            flat['mobes_ai.meta_description'] = JSON.stringify(metaDescriptions);
        }
        
        // mode_keywordsの特別処理（カンマ区切りのテキストを配列に変換）
        const categoryKeywords = document.querySelector('textarea[name="mobes_ai.mode_keywords.sommelier.category_keywords"]');
        const nameKeywords = document.querySelector('textarea[name="mobes_ai.mode_keywords.sommelier.name_keywords"]');
        
        if (categoryKeywords || nameKeywords) {
            const keywordsData = {
                sommelier: {
                    category_keywords: categoryKeywords ? categoryKeywords.value.split(',').map(k => k.trim()).filter(k => k) : [],
                    name_keywords: nameKeywords ? nameKeywords.value.split(',').map(k => k.trim()).filter(k => k) : []
                }
            };
            flat['mobes_ai.mode_keywords'] = JSON.stringify(keywordsData);
        }
        
        const json = unflatten(flat);
        
        // meta_descriptionの復元
        if (json.mobes_ai && flat['mobes_ai.meta_description']) {
            json.mobes_ai.meta_description = JSON.parse(flat['mobes_ai.meta_description']);
        }
        
        // mode_keywordsの復元
        if (json.mobes_ai && flat['mobes_ai.mode_keywords']) {
            json.mobes_ai.mode_keywords = JSON.parse(flat['mobes_ai.mode_keywords']);
        }
        
        fetch('adminsetting_registrer.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(json)
        }).then(r=>r.json()).then(d=>{
            alert(d.message||'保存しました');
            loadSettings(); // 再読み込み
        }).catch(e=>alert('保存失敗'));
    }

    function updateAdvanced(){
        const json = {};
        new FormData(advancedForm).forEach((v,k)=>{
            const elem=advancedForm.querySelector(`[name="${k}"]`);
            if(elem&&elem.type==='checkbox'){
                json[k]=elem.checked?1:0;
            }else{ json[k]=v; }
        });
        fetch('advanced_Setting_registrer.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(json)
        }).then(r=>r.json()).then(d=>{
            alert(d.message||'保存しました');
        }).catch(e=>alert('保存失敗'));
    }

    // mobes_ai専用UIの構築
    function buildMobesAiUI(mobesAiConfig) {
        // プロンプト設定
        const promptsContainer = document.getElementById('promptsContainer');
        promptsContainer.innerHTML = '';
        
        // 基本プロンプト
        if (mobesAiConfig.prompt !== undefined) {
            promptsContainer.appendChild(createTextareaInput('mobes_ai.prompt', mobesAiConfig.prompt, '基本システムプロンプト', 5));
        }
        
        // モード別プロンプト
        if (mobesAiConfig.mode_prompts) {
            const modeSection = document.createElement('div');
            modeSection.className = 'settings-section mt-3';
            modeSection.innerHTML = '<h5 class="mb-3">モード別プロンプト</h5>';
            
            Object.entries(mobesAiConfig.mode_prompts).forEach(([mode, prompt]) => {
                modeSection.appendChild(createTextareaInput(`mobes_ai.mode_prompts.${mode}`, prompt, labelMappings[`mobes_ai.mode_prompts.${mode}`] || mode, 8));
            });
            promptsContainer.appendChild(modeSection);
        }
        
        // 履歴プロンプト
        if (mobesAiConfig.history_prompts) {
            const historySection = document.createElement('div');
            historySection.className = 'settings-section mt-3';
            historySection.innerHTML = '<h5 class="mb-3">履歴プロンプト</h5>';
            
            Object.entries(mobesAiConfig.history_prompts).forEach(([mode, prompts]) => {
                const modeDiv = document.createElement('div');
                modeDiv.className = 'mb-3';
                modeDiv.innerHTML = `<h6>${mode}モード</h6>`;
                
                Object.entries(prompts).forEach(([key, prompt]) => {
                    modeDiv.appendChild(createTextareaInput(`mobes_ai.history_prompts.${mode}.${key}`, prompt, labelMappings[`mobes_ai.history_prompts.${mode}.${key}`] || key, 4));
                });
                historySection.appendChild(modeDiv);
            });
            promptsContainer.appendChild(historySection);
        }
        
        // モード別キーワード設定
        if (mobesAiConfig.mode_keywords || true) { // 常に表示
            const keywordsSection = document.createElement('div');
            keywordsSection.className = 'settings-section mt-3';
            keywordsSection.innerHTML = '<h5 class="mb-3">モード別キーワード設定</h5>';
            keywordsSection.innerHTML += '<p class="text-muted small">各モードで使用する商品フィルタリングキーワードを設定します。カンマ区切りで複数指定可能です。</p>';
            
            // ソムリエモードのキーワード
            const sommelierKeywords = mobesAiConfig.mode_keywords?.sommelier || {
                category_keywords: ["wine", "ワイン", "シャンパン", "champagne", "カクテル", "cocktail", "アルコール", "ハイボール", "ビール", "日本酒", "焼酎", "ウイスキー", "赤ワイン", "白ワイン", "ロゼワイン", "スパークリング"],
                name_keywords: ["ワイン", "シャンパン", "カクテル", "ハイボール", "ビール", "酒"]
            };
            
            const sommelierDiv = document.createElement('div');
            sommelierDiv.className = 'mb-3';
            sommelierDiv.innerHTML = '<h6>ソムリエモード</h6>';
            
            // カテゴリーキーワード
            const categoryKeywordsDiv = document.createElement('div');
            categoryKeywordsDiv.className = 'mb-3';
            categoryKeywordsDiv.innerHTML = `
                <label class="form-label fw-semibold">
                    <i class="bi bi-tag me-1"></i> カテゴリー名キーワード
                </label>
                <textarea class="form-control" name="mobes_ai.mode_keywords.sommelier.category_keywords" rows="3">${sommelierKeywords.category_keywords.join(', ')}</textarea>
                <small class="form-text text-muted">カテゴリー名に含まれる場合にマッチします</small>
            `;
            sommelierDiv.appendChild(categoryKeywordsDiv);
            
            // 商品名キーワード
            const nameKeywordsDiv = document.createElement('div');
            nameKeywordsDiv.className = 'mb-3';
            nameKeywordsDiv.innerHTML = `
                <label class="form-label fw-semibold">
                    <i class="bi bi-basket me-1"></i> 商品名キーワード
                </label>
                <textarea class="form-control" name="mobes_ai.mode_keywords.sommelier.name_keywords" rows="2">${sommelierKeywords.name_keywords.join(', ')}</textarea>
                <small class="form-text text-muted">商品名に含まれる場合にマッチします</small>
            `;
            sommelierDiv.appendChild(nameKeywordsDiv);
            
            keywordsSection.appendChild(sommelierDiv);
            promptsContainer.appendChild(keywordsSection);
        }
        
        // 商品詳細情報
        const metaDescContainer = document.getElementById('metaDescContainer');
        metaDescContainer.innerHTML = '';
        
        if (mobesAiConfig.meta_description) {
            Object.entries(mobesAiConfig.meta_description).forEach(([productName, description]) => {
                metaDescContainer.appendChild(createMetaDescriptionItem(productName, description));
            });
        }
        
        // その他の設定
        const otherContainer = document.getElementById('otherSettingsContainer');
        otherContainer.innerHTML = '';
        
        const otherSettings = {...mobesAiConfig};
        delete otherSettings.prompt;
        delete otherSettings.mode_prompts;
        delete otherSettings.history_prompts;
        delete otherSettings.meta_description;
        delete otherSettings.mode_keywords;
        
        buildSection(otherSettings, otherContainer, 'mobes_ai', mobesAiConfig);
    }

    // テキストエリア入力の作成
    function createTextareaInput(name, value, label, rows = 3) {
        const div = document.createElement('div');
        div.className = 'mb-3';
        
        const labelEl = document.createElement('label');
        labelEl.className = 'form-label fw-semibold';
        labelEl.innerHTML = `<i class="bi bi-pencil-square me-1"></i> ${label}`;
        labelEl.htmlFor = 'fld_' + name.replace(/[^a-zA-Z0-9_\-]/g, '_');
        
        const textarea = document.createElement('textarea');
        textarea.className = 'form-control';
        textarea.name = name;
        textarea.id = labelEl.htmlFor;
        textarea.rows = rows;
        textarea.value = value;
        
        div.appendChild(labelEl);
        div.appendChild(textarea);
        
        return div;
    }

    // 商品詳細情報アイテムの作成
    function createMetaDescriptionItem(productName, description) {
        const div = document.createElement('div');
        div.className = 'card mb-3';
        div.innerHTML = `
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">商品名</label>
                        <input type="text" class="form-control product-name" value="${productName}" readonly>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">詳細説明</label>
                        <textarea class="form-control meta-description" name="mobes_ai.meta_description.${productName}" rows="4">${description}</textarea>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-danger remove-meta-desc" data-product="${productName}">
                                <i class="bi bi-trash"></i> 削除
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // 削除ボタンのイベント
        div.querySelector('.remove-meta-desc').addEventListener('click', function() {
            if (confirm(`"${productName}" の詳細説明を削除しますか？`)) {
                div.remove();
            }
        });
        
        return div;
    }

    // 商品選択モーダル
    const productSelectModal = new bootstrap.Modal(document.getElementById('productSelectModal'));
    let availableProducts = [];

    // カテゴリーリストを読み込み
    async function loadCategories() {
        try {
            const response = await fetch('api/get_products_for_meta.php', {
                method: 'GET',
                credentials: 'include',  // セッションクッキーを確実に含める
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            // レスポンスのテキストを取得
            const responseText = await response.text();
            console.log('Response status:', response.status);
            console.log('Response text:', responseText);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}, response: ${responseText}`);
            }
            
            // JSONとしてパース
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Invalid JSON response: ' + responseText);
            }
            
            if (data.success && data.categories) {
                const categorySelect = document.getElementById('categorySelect');
                categorySelect.innerHTML = '<option value="">カテゴリーを選択してください...</option>';
                categorySelect.innerHTML += '<option value="all">全てのカテゴリー</option>';
                data.categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    categorySelect.appendChild(option);
                });
            } else if (!data.success && data.message) {
                throw new Error(data.message);
            }
        } catch (e) {
            console.error('カテゴリー読み込みエラー:', e);
            alert('カテゴリーの読み込みに失敗しました: ' + e.message);
        }
    }

    // 商品リストを読み込み
    async function loadProducts(category) {
        try {
            const response = await fetch(`api/get_products_for_meta.php?category=${encodeURIComponent(category)}`, {
                method: 'GET',
                credentials: 'include',  // セッションクッキーを確実に含める
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            // レスポンスのテキストを取得
            const responseText = await response.text();
            console.log('Product response status:', response.status);
            console.log('Product response text:', responseText);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}, response: ${responseText}`);
            }
            
            // JSONとしてパース
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Invalid JSON response: ' + responseText);
            }
            
            if (data.success && data.products) {
                availableProducts = data.products;
                const productSelect = document.getElementById('productSelect');
                productSelect.innerHTML = '<option value="">商品を選択してください...</option>';
                
                data.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.name;
                    option.textContent = product.name;
                    if (product.has_meta_description) {
                        option.textContent += ' (設定済み)';
                        option.style.color = '#6c757d';
                    }
                    option.dataset.category = product.category_name;
                    productSelect.appendChild(option);
                });
                
                document.getElementById('productSelectContainer').style.display = 'block';
            } else if (!data.success && data.message) {
                throw new Error(data.message);
            }
        } catch (e) {
            console.error('商品読み込みエラー:', e);
            alert('商品の読み込みに失敗しました: ' + e.message);
        }
    }

    // カテゴリー選択時
    document.getElementById('categorySelect').addEventListener('change', function() {
        const category = this.value;
        if (category) {
            loadProducts(category);
        } else {
            document.getElementById('productSelectContainer').style.display = 'none';
            document.getElementById('confirmProductBtn').disabled = true;
        }
    });

    // 商品選択時
    document.getElementById('productSelect').addEventListener('change', function() {
        const selectedProduct = this.value;
        const confirmBtn = document.getElementById('confirmProductBtn');
        const productInfo = document.getElementById('productInfo');
        
        if (selectedProduct) {
            confirmBtn.disabled = false;
            const selectedOption = this.options[this.selectedIndex];
            productInfo.textContent = `カテゴリー: ${selectedOption.dataset.category}`;
        } else {
            confirmBtn.disabled = true;
            productInfo.textContent = '';
        }
    });

    // 新規商品説明追加（修正版）
    document.getElementById('addMetaDescBtn').addEventListener('click', async function() {
        await loadCategories();
        document.getElementById('categorySelect').value = '';
        document.getElementById('productSelectContainer').style.display = 'none';
        document.getElementById('confirmProductBtn').disabled = true;
        productSelectModal.show();
    });

    // 商品選択確定
    document.getElementById('confirmProductBtn').addEventListener('click', function() {
        const productName = document.getElementById('productSelect').value;
        if (productName) {
            // 既存の商品かチェック
            const existingCards = document.querySelectorAll('#metaDescContainer .product-name');
            let exists = false;
            existingCards.forEach(card => {
                if (card.value === productName) {
                    exists = true;
                }
            });
            
            if (exists) {
                alert(`"${productName}" は既に追加されています。`);
            } else {
                const metaDescContainer = document.getElementById('metaDescContainer');
                metaDescContainer.appendChild(createMetaDescriptionItem(productName, ''));
            }
            
            productSelectModal.hide();
        }
    });
})();
</script>
</body>
</html>
<?php require_once __DIR__.'/inc/admin_footer.php'; ?> 