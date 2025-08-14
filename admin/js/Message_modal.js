// Message_modal.js
// Version: 1.0.0
// ------------------------------------------------------------
// メッセンジャーUIの制御
// LINEライクなメッセンジャーインターフェースの実装
// ------------------------------------------------------------

class MessageModal {
    constructor() {
        this.modal = document.getElementById('messengerModal');
        this.messageContainer = document.getElementById('messageContainer');
        this.messageInput = document.getElementById('messageInput');
        this.sendButton = document.getElementById('sendMessage');
        this.currentGuest = null;
        this.pollingInterval = null;

        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // メッセージ送信
        this.sendButton.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // モーダル表示時の処理
        this.modal.addEventListener('show.bs.modal', (e) => {
            const button = e.relatedTarget;
            this.currentGuest = JSON.parse(button.dataset.guest);
            this.loadMessages();
            this.startPolling();
        });

        // モーダル非表示時の処理
        this.modal.addEventListener('hidden.bs.modal', () => {
            this.stopPolling();
            this.currentGuest = null;
            this.messageContainer.innerHTML = '';
        });
    }

    async loadMessages() {
        try {
            const response = await fetch(`message_Transmission.php?action=getMessages&line_user_id=${this.currentGuest.line_user_id}`);
            const messages = await response.json();
            this.renderMessages(messages);
        } catch (error) {
            console.error('Failed to load messages:', error);
        }
    }

    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;

        try {
            const response = await fetch('message_Transmission.php?action=sendMessage', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    line_user_id: this.currentGuest.line_user_id,
                    message: message
                })
            });

            if (response.ok) {
                this.messageInput.value = '';
                this.loadMessages();
            }
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    }

    renderMessages(messages) {
        this.messageContainer.innerHTML = '';
        messages.forEach(message => {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${message.is_from_guest ? 'guest' : 'staff'}`;
            messageElement.innerHTML = `
                <div class="message-content">
                    <div class="message-text">${message.content}</div>
                    <div class="message-time">${new Date(message.timestamp).toLocaleTimeString()}</div>
                </div>
            `;
            this.messageContainer.appendChild(messageElement);
        });
        this.scrollToBottom();
    }

    scrollToBottom() {
        this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
    }

    startPolling() {
        this.pollingInterval = setInterval(() => this.loadMessages(), 5000);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }
}

// ゲストカードの読み込み
async function loadGuestCards() {
    try {
        const response = await fetch('message_Transmission.php?action=getActiveGuests');
        const guests = await response.json();
        const container = document.getElementById('guestCards');
        
        // 部屋番号でグループ化
        const roomGroups = guests.reduce((groups, guest) => {
            const room = guest.room_number;
            if (!groups[room]) {
                groups[room] = [];
            }
            groups[room].push(guest);
            return groups;
        }, {});

        // カードの生成
        container.innerHTML = '';
        Object.entries(roomGroups).forEach(([room, roomGuests]) => {
            const card = document.createElement('div');
            card.className = 'col-md-4 mb-4';
            card.innerHTML = `
                <div class="card guest-card" data-bs-toggle="modal" data-bs-target="#messengerModal" 
                     data-guest='${JSON.stringify(roomGuests[0])}'>
                    <div class="card-header">
                        <h5 class="card-title">Room ${room}</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            ${roomGuests.map(guest => guest.user_name).join(', ')}
                        </p>
                        <div class="message-preview">
                            <!-- 最新メッセージのプレビュー -->
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    } catch (error) {
        console.error('Failed to load guest cards:', error);
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    new MessageModal();
}); 