function toggleUserSelect() {
    var type = document.getElementById('send_type').value;
    document.getElementById('user_select_area').style.display = (type === 'individual') ? '' : 'none';
}

function toggleMessageForm() {
    var type = document.getElementById('message_type').value;
    var textArea = document.querySelector('textarea[name="message"]');
    document.getElementById('text_message_area').style.display = (type === 'text') ? '' : 'none';
    document.getElementById('rich_message_area').style.display = (type === 'rich') ? '' : 'none';
    if (type === 'text') {
        textArea.setAttribute('required', 'required');
    } else {
        textArea.removeAttribute('required');
    }
}

function previewImage(input) {
    const preview = document.getElementById('image_preview');
    const previewNormal = document.getElementById('preview_normal');
    const imageInfo = document.getElementById('image_info');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            // 画像の読み込み
            const img = new Image();
            img.onload = function() {
                // 画像情報の表示
                const info = [
                    `ファイル名: ${file.name}`,
                    `サイズ: ${(file.size / 1024).toFixed(1)} KB`,
                    `元のサイズ: ${img.width}×${img.height}px`,
                    `アスペクト比: ${(img.width / img.height).toFixed(2)}`
                ].join('<br>');

                // 警告メッセージの追加
                let warnings = [];
                if (img.width !== img.height) {
                    warnings.push('⚠️ 画像が正方形ではありません（自動的に正方形にリサイズされます）');
                }

                imageInfo.innerHTML = info + (warnings.length ? '<br><br>' + warnings.join('<br>') : '');

                // プレビューの表示
                previewNormal.src = e.target.result;
                preview.style.display = 'block';
            };
            img.src = e.target.result;
        };

        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
        previewNormal.src = '';
        imageInfo.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleUserSelect();
    toggleMessageForm();
});

// ドラッグアンドドロップの処理
const dropZone = document.getElementById('drop_zone');
const fileInput = document.getElementById('rich_image');

// ドラッグオーバー時の処理
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// ドラッグオーバー時のスタイル変更
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.style.borderColor = '#2ecc40';
    dropZone.style.background = '#f0fff0';
}

function unhighlight(e) {
    dropZone.style.borderColor = '#ccc';
    dropZone.style.background = '#fafafa';
}

// ドロップ時の処理
dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
        fileInput.files = files;
        previewImage(fileInput);
    }
}

// クリック時の処理
dropZone.addEventListener('click', () => {
    fileInput.click();
});

// テンプレート選択に応じたリンク入力欄の動的生成
const templateTypes = {
    block1: 1,
    block2yoko: 2,
    block2tate: 2,
    block4: 4
};
function updateTemplateLinks() {
    const selected = document.querySelector('input[name="template_type"]:checked').value;
    const num = templateTypes[selected];
    const area = document.getElementById('template_links_area');
    let html = '';
    for (let i = 1; i <= num; i++) {
        html += `<div style='margin-bottom:0.5em;'><label>リンク${i}：</label><input type='text' name='template_links[]' style='width:80%;'></div>`;
    }
    area.innerHTML = html;
}
document.querySelectorAll('input[name="template_type"]').forEach(el => {
    el.addEventListener('change', function() {
        // 枠線の色を切り替え
        document.querySelectorAll('#template_select img').forEach(img => img.style.borderColor = '#ccc');
        this.parentNode.querySelector('img').style.borderColor = '#2ecc40';
        updateTemplateLinks();
    });
});
// 初期表示
updateTemplateLinks();

// 既存画像の一覧（PHPで生成したJSONを使う）
const existingImages = window.existingImagesData || [];
const baseUrl = '/fgsquare/lumos/upload/images/';
const existingImagesDiv = document.getElementById('existing_images');
existingImages.forEach(img => {
    const thumbUrl = baseUrl + img.dir + '/1040';
    const el = document.createElement('img');
    el.src = thumbUrl;
    el.style.width = '80px';
    el.style.height = '80px';
    el.style.objectFit = 'cover';
    el.style.border = '2px solid #ccc';
    el.style.borderRadius = '4px';
    el.style.cursor = 'pointer';
    el.title = img.dir;
    el.onclick = function() {
        // 選択状態の枠線を変更
        document.querySelectorAll('#existing_images img').forEach(i => i.style.borderColor = '#ccc');
        el.style.borderColor = '#2ecc40';
        // hiddenにベースURLをセット
        document.getElementById('selected_baseurl').value = baseUrl + img.dir;
        // アップロード欄をクリア
        document.getElementById('rich_image').value = '';
        previewImage({ files: [] });
    };
    existingImagesDiv.appendChild(el);
});
// アップロード時は既存画像選択をクリア
fileInput.addEventListener('change', function() {
    document.getElementById('selected_baseurl').value = '';
    document.querySelectorAll('#existing_images img').forEach(i => i.style.borderColor = '#ccc');
}); 