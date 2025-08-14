/**
 * Kitchen Monitor Notifications System
 * 
 * Handles toast notifications and audio alerts
 */

class KitchenNotifications {
    constructor() {
        this.container = document.getElementById('notification-container');
        this.notifications = new Map();
        this.maxNotifications = 5;
        this.defaultDuration = 3000;
    }

    /**
     * Show a notification
     */
    show(message, type = 'info', duration = null) {
        const id = this.generateId();
        const notification = this.createElement(id, message, type);
        
        // Add to container
        this.container.appendChild(notification);
        this.notifications.set(id, notification);

        // Trigger entrance animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        // Auto-remove after duration
        if (duration !== 0) {
            const timeout = setTimeout(() => {
                this.remove(id);
            }, duration || this.defaultDuration);

            notification.dataset.timeout = timeout;
        }

        // Remove oldest if too many notifications
        this.limitNotifications();

        return id;
    }

    /**
     * Create notification element
     */
    createElement(id, message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.dataset.id = id;

        const icon = this.getIcon(type);
        
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">${icon}</div>
                <div class="notification-message">${message}</div>
                <button class="notification-close" onclick="window.KitchenNotifications.remove('${id}')">
                    ×
                </button>
            </div>
        `;

        // Auto-hide on click (except close button)
        notification.addEventListener('click', (e) => {
            if (!e.target.classList.contains('notification-close')) {
                this.remove(id);
            }
        });

        // Initialize Lucide icons in the notification
        setTimeout(() => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }, 0);

        return notification;
    }

    /**
     * Get icon for notification type
     */
    getIcon(type) {
        const icons = {
            'success': '<i data-lucide="check" class="icon icon--sm"></i>',
            'error': '<i data-lucide="x" class="icon icon--sm"></i>',
            'warning': '<i data-lucide="alert-triangle" class="icon icon--sm"></i>',
            'info': '<i data-lucide="info" class="icon icon--sm"></i>'
        };
        return icons[type] || icons.info;
    }

    /**
     * Remove notification
     */
    remove(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;

        // Clear timeout if exists
        if (notification.dataset.timeout) {
            clearTimeout(parseInt(notification.dataset.timeout));
        }

        // Trigger exit animation
        notification.classList.add('removing');
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
            this.notifications.delete(id);
        }, 300);
    }

    /**
     * Remove all notifications
     */
    clear() {
        this.notifications.forEach((notification, id) => {
            this.remove(id);
        });
    }

    /**
     * Limit number of notifications
     */
    limitNotifications() {
        if (this.notifications.size > this.maxNotifications) {
            const oldest = this.notifications.keys().next().value;
            this.remove(oldest);
        }
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return 'notification_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Show success notification
     */
    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    /**
     * Show error notification
     */
    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    /**
     * Show warning notification
     */
    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    /**
     * Show info notification
     */
    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Initialize global notifications instance
window.KitchenNotifications = new KitchenNotifications();

// Add CSS styles for notifications
const notificationStyles = `
<style>
#notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 2000;
    pointer-events: none;
}

.notification {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    margin-bottom: 12px;
    min-width: 300px;
    max-width: 400px;
    transform: translateX(100%);
    transition: all 0.3s ease;
    pointer-events: auto;
    opacity: 0;
}

.notification.show {
    transform: translateX(0);
    opacity: 1;
}

.notification.removing {
    transform: translateX(100%);
    opacity: 0;
}

.notification-content {
    display: flex;
    align-items: flex-start;
    padding: 16px;
    gap: 12px;
}

.notification-icon {
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.notification-message {
    flex: 1;
    font-size: 14px;
    line-height: 1.4;
    color: var(--text-primary);
    word-wrap: break-word;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.notification-close:hover {
    background: rgba(0,0,0,0.1);
    color: var(--text-primary);
}

.notification.success {
    border-left: 4px solid var(--color-delivered);
}

.notification.error {
    border-left: 4px solid var(--color-cancelled);
}

.notification.warning {
    border-left: 4px solid var(--color-warning);
}

.notification.info {
    border-left: 4px solid var(--color-ordered);
}

/* Responsive adjustments */
@media (max-width: 480px) {
    #notification-container {
        left: 20px;
        right: 20px;
        top: 10px;
    }
    
    .notification {
        min-width: auto;
        max-width: none;
    }
    
    .notification-content {
        padding: 12px;
    }
}

/* Animation for urgent notifications */
.notification.urgent {
    animation: urgent-shake 0.5s ease-in-out;
}

@keyframes urgent-shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* Dark mode support */
body.dark-mode .notification {
    background: var(--bg-secondary);
    color: var(--text-light);
}

body.dark-mode .notification-message {
    color: var(--text-light);
}

body.dark-mode .notification-close {
    color: var(--text-muted);
}

body.dark-mode .notification-close:hover {
    background: rgba(255,255,255,0.1);
    color: var(--text-light);
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', notificationStyles);

/**
 * Audio Notification System
 */
class AudioNotifications {
    constructor() {
        this.sounds = {
            newOrder: document.getElementById('order-chime'),
            success: null,
            error: null,
            warning: null
        };
        
        this.enabled = true;
        this.volume = 0.7;
        
        this.loadSettings();
    }

    /**
     * Load audio settings from localStorage
     */
    loadSettings() {
        const enabled = localStorage.getItem('kitchen_audio_enabled');
        if (enabled !== null) {
            this.enabled = enabled === 'true';
        }

        const volume = localStorage.getItem('kitchen_audio_volume');
        if (volume !== null) {
            this.volume = parseFloat(volume);
        }

        this.updateSoundVolumes();
    }

    /**
     * Update volumes for all sounds
     */
    updateSoundVolumes() {
        Object.values(this.sounds).forEach(sound => {
            if (sound) {
                sound.volume = this.volume;
            }
        });
    }

    /**
     * Play sound by type
     */
    play(type) {
        if (!this.enabled) return;

        const sound = this.sounds[type];
        if (sound) {
            try {
                sound.currentTime = 0;
                sound.play().catch(error => {
                    console.warn('Could not play sound:', error);
                });
            } catch (error) {
                console.warn('Audio playback failed:', error);
            }
        }
    }

    /**
     * Play new order sound
     */
    playNewOrder() {
        this.play('newOrder');
    }

    /**
     * Enable/disable audio
     */
    setEnabled(enabled) {
        this.enabled = enabled;
        localStorage.setItem('kitchen_audio_enabled', enabled);
    }

    /**
     * Set volume level
     */
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        this.updateSoundVolumes();
        localStorage.setItem('kitchen_audio_volume', this.volume);
    }

    /**
     * Test audio playback
     */
    test() {
        this.playNewOrder();
    }
}

// Initialize global audio notifications
window.AudioNotifications = new AudioNotifications();

/**
 * Utility functions for common notification patterns
 */
window.KitchenNotifications.showOrderUpdate = function(productName, status) {
    const statusMessages = {
        'ready': `<i data-lucide="chef-hat" class="icon icon--sm"></i> 調理完了: ${productName}`,
        'delivered': `<i data-lucide="check" class="icon icon--sm"></i> 配達完了: ${productName}`,
        'cancelled': `<i data-lucide="x" class="icon icon--sm"></i> キャンセル: ${productName}`
    };
    
    const message = statusMessages[status] || `ステータス更新: ${productName}`;
    const type = status === 'cancelled' ? 'error' : 'success';
    
    return this.show(message, type);
};

window.KitchenNotifications.showConnectionError = function() {
    return this.error('サーバーとの接続に失敗しました', 5000);
};

window.KitchenNotifications.showNewOrders = function(count) {
    const message = `🔔 新しい注文が${count}件入りました`;
    window.AudioNotifications.playNewOrder();
    return this.info(message, 4000);
};