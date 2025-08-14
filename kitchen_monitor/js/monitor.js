/**
 * Kitchen Monitor Main JavaScript
 * 
 * Main functionality for the Mobes Kitchen Monitor interface
 */

class KitchenMonitor {
    constructor() {
        this.config = window.KITCHEN_MONITOR_CONFIG || {};
        this.lastUpdateTime = null;
        this.refreshInterval = null;
        this.orders = [];
        this.showCompleted = false;
        this.audioEnabled = this.config.audioEnabled;
        this.isConnected = true;
        this.currentView = 'ordered'; // 'both', 'ordered', 'ready'
        this.showRoomGroups = false; // Track room group display mode
        
        // Swipe gesture properties
        this.swipeStartX = 0;
        this.swipeStartY = 0;
        this.swipeStartTime = 0;
        this.swipeThreshold = 80; // minimum distance for swipe
        this.swipeTimeThreshold = 500; // maximum time for swipe (ms)
        this.currentSwipeCard = null;
        this.openMenuId = null; // Track currently open menu

        // DOM elements
        this.elements = {
            loading: document.getElementById('loading'),
            emptyState: document.getElementById('empty-state'),
            ordersColumns: document.querySelector('.orders-columns'),
            orderedGrid: document.getElementById('ordered-grid'),
            readyGrid: document.getElementById('ready-grid'),
            orderedCount: document.getElementById('ordered-count'),
            readyCount: document.getElementById('ready-count'),
            pendingCount: document.getElementById('pending-count'),
            headerReadyCount: document.getElementById('header-ready-count'),
            lastUpdate: document.getElementById('last-update'),
            showCompletedBtn: document.getElementById('show-completed-btn'),
            refreshBtn: document.getElementById('refresh-btn'),
            audioToggle: document.getElementById('audio-toggle'),
            audioIcon: document.getElementById('audio-icon'),
            connectionIndicator: document.getElementById('connection-indicator'),
            notificationContainer: document.getElementById('notification-container'),
            cancelModal: document.getElementById('cancel-modal'),
            cancelModalClose: document.getElementById('cancel-modal-close'),
            cancelModalConfirm: document.getElementById('cancel-modal-confirm'),
            cancelMessage: document.getElementById('cancel-message'),
            completedModal: document.getElementById('completed-modal'),
            completedModalClose: document.getElementById('completed-modal-close'),
            completedOrdersGrid: document.getElementById('completed-orders-grid'),
            completedLoading: document.getElementById('completed-loading'),
            completedEmpty: document.getElementById('completed-empty'),
            modalDeliveredCount: document.getElementById('modal-delivered-count'),
            modalCancelledCount: document.getElementById('modal-cancelled-count'),
            orderChime: document.getElementById('order-chime'),
            viewBothBtn: document.getElementById('view-both'),
            viewOrderedBtn: document.getElementById('view-ordered'),
            viewReadyBtn: document.getElementById('view-ready'),
            orderedColumn: document.getElementById('ordered-column'),
            readyColumn: document.getElementById('ready-column'),
            newOrderBar: document.getElementById('new-order-bar'),
            newOrderMessage: document.getElementById('new-order-message'),
            newOrderClose: document.getElementById('new-order-close'),
            viewSwitchBtn: document.getElementById('view-switch-btn'),
            roomGroupBtn: document.getElementById('room-group-btn')
        };

        // Bind methods
        this.fetchOrders = this.fetchOrders.bind(this);
        this.handleStatusUpdate = this.handleStatusUpdate.bind(this);
        this.handleCancelOrder = this.handleCancelOrder.bind(this);
    }

    /**
     * Initialize the kitchen monitor
     */
    init() {
        this.setupEventListeners();
        this.loadUserPreferences();
        this.initializeViewFromURL(); // Initialize view from URL parameter
        this.updateViewSwitchButton(); // Initialize button state
        this.fetchOrders();
        this.startAutoRefresh();
        
        console.log('Kitchen Monitor initialized');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Refresh button
        this.elements.refreshBtn.addEventListener('click', () => {
            this.fetchOrders();
        });

        // Show completed toggle
        this.elements.showCompletedBtn.addEventListener('click', () => {
            this.toggleShowCompleted();
        });

        // Audio toggle
        this.elements.audioToggle.addEventListener('click', () => {
            this.toggleAudio();
        });

        // New order bar close
        this.elements.newOrderClose.addEventListener('click', () => {
            this.hideNewOrderBar();
        });

        // View switch button
        this.elements.viewSwitchBtn.addEventListener('click', () => {
            this.toggleViewSwitch();
        });

        // Room group button
        this.elements.roomGroupBtn.addEventListener('click', () => {
            this.toggleRoomGroups();
        });

        // View toggle buttons
        this.elements.viewBothBtn.addEventListener('click', () => {
            this.setColumnView('both');
        });

        this.elements.viewOrderedBtn.addEventListener('click', () => {
            this.setColumnView('ordered');
        });

        this.elements.viewReadyBtn.addEventListener('click', () => {
            this.setColumnView('ready');
        });

        // Cancel modal
        this.elements.cancelModalClose.addEventListener('click', () => {
            this.hideCancelModal();
        });

        this.elements.cancelModalConfirm.addEventListener('click', () => {
            this.confirmCancelOrder();
        });

        // Click outside modal to close
        this.elements.cancelModal.addEventListener('click', (e) => {
            if (e.target === this.elements.cancelModal) {
                this.hideCancelModal();
            }
        });

        // Completed modal events
        this.elements.completedModalClose.addEventListener('click', () => {
            this.hideCompletedModal();
        });

        this.elements.completedModal.addEventListener('click', (e) => {
            if (e.target === this.elements.completedModal) {
                this.hideCompletedModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'r':
                case 'R':
                    e.preventDefault();
                    this.fetchOrders();
                    break;
                case 'c':
                case 'C':
                    e.preventDefault();
                    this.toggleShowCompleted();
                    break;
                case 'm':
                case 'M':
                    e.preventDefault();
                    this.toggleAudio();
                    break;
                case 'Escape':
                    this.hideCancelModal();
                    this.hideCompletedModal();
                    this.hideNewOrderBar();
                    this.closeAllMenus();
                    break;
            }
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is hidden, reduce refresh frequency
                this.setRefreshInterval(60000); // 1 minute
            } else {
                // Page is visible, normal refresh frequency
                this.setRefreshInterval(this.config.autoRefreshInterval);
                this.fetchOrders(); // Immediate refresh when page becomes visible
            }
        });

        // Close menus when clicking outside
        document.addEventListener('click', (e) => {
            if (this.openMenuId && !e.target.closest('.order-menu')) {
                this.closeAllMenus();
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            this.handlePopState(e);
        });

        // Handle online/offline status
        window.addEventListener('online', () => {
            this.setConnectionStatus(true);
            this.fetchOrders();
        });

        window.addEventListener('offline', () => {
            this.setConnectionStatus(false);
        });
    }

    /**
     * Load user preferences from localStorage
     */
    loadUserPreferences() {
        // Audio enabled state
        const audioEnabled = localStorage.getItem('kitchen_audio_enabled');
        if (audioEnabled !== null) {
            this.audioEnabled = audioEnabled === 'true';
            this.updateAudioIcon();
        }

        // Show completed state
        const showCompleted = localStorage.getItem('kitchen_show_completed');
        if (showCompleted !== null) {
            this.showCompleted = showCompleted === 'true';
            this.updateShowCompletedButton();
        }

        // Column view state
        const columnView = localStorage.getItem('kitchen_column_view');
        if (columnView !== null) {
            this.currentView = columnView;
        }
        this.updateColumnView();

        // Audio volume
        const volume = localStorage.getItem('kitchen_audio_volume');
        if (volume !== null && this.elements.orderChime) {
            this.elements.orderChime.volume = parseFloat(volume);
        } else if (this.elements.orderChime) {
            this.elements.orderChime.volume = this.config.chimeVolume || 0.7;
        }
    }

    /**
     * Fetch orders from API
     */
    async fetchOrders() {
        try {
            this.setConnectionStatus(true);
            
            const url = `${this.config.apiBaseUrl}get_orders.php?show_completed=${this.showCompleted}` +
                       (this.lastUpdateTime ? `&last_update=${encodeURIComponent(this.lastUpdateTime)}` : '');
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'API returned error');
            }

            // Check for new orders and play chime
            if (this.lastUpdateTime && data.data.new_orders_count > 0) {
                this.playOrderChime();
                this.showNotification(
                    `🔔 新しい注文が${data.data.new_orders_count}件入りました`,
                    'info'
                );
                // Show new order bar
                this.showNewOrderBar(data.data.new_orders_count);
            }

            // Update orders and UI
            this.orders = data.data.orders;
            this.updateOrdersDisplay();
            this.updateColumnView(); // Apply current view after updating display
            this.updateStats(data.data.stats);
            this.lastUpdateTime = data.data.last_update;
            this.updateLastUpdateTime();

            // Hide loading state
            this.elements.loading.style.display = 'none';

        } catch (error) {
            console.error('Failed to fetch orders:', error);
            this.setConnectionStatus(false);
            this.showNotification(`接続エラー: ${error.message}`, 'error');
            
            // Hide loading state even on error
            this.elements.loading.style.display = 'none';
        }
    }

    /**
     * Update orders display with 2-column layout
     */
    updateOrdersDisplay() {
        const orderedGrid = this.elements.orderedGrid;
        const readyGrid = this.elements.readyGrid;
        const ordersColumns = this.elements.ordersColumns;
        const emptyState = this.elements.emptyState;

        if (this.orders.length === 0) {
            ordersColumns.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        ordersColumns.style.display = 'grid';
        emptyState.style.display = 'none';
        
        // Clear existing cards
        orderedGrid.innerHTML = '';
        readyGrid.innerHTML = '';

        // Separate orders by status
        const orderedOrders = [];
        const readyOrders = [];

        this.orders.forEach(order => {
            if (order.status === 'ordered') {
                orderedOrders.push(order);
            } else if (order.status === 'ready') {
                readyOrders.push(order);
            }
        });

        // Create order cards for each column
        orderedOrders.forEach(order => {
            const card = this.createOrderCard(order);
            orderedGrid.appendChild(card);
        });

        // Display ready orders - grouped by room if room group mode is enabled
        if (this.showRoomGroups && this.currentView === 'ready') {
            this.displayRoomGroupCards(readyOrders, readyGrid);
        } else {
            readyOrders.forEach(order => {
                const card = this.createOrderCard(order);
                readyGrid.appendChild(card);
            });
        }

        // Update column counts
        this.elements.orderedCount.textContent = orderedOrders.length;
        this.elements.readyCount.textContent = readyOrders.length;
        
        // Update header ready count
        if (this.elements.headerReadyCount) {
            this.elements.headerReadyCount.textContent = readyOrders.length;
        }

        // Initialize Lucide icons after updating HTML
        setTimeout(() => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }, 0);
    }

    /**
     * Create order card element
     */
    createOrderCard(order) {
        const card = document.createElement('div');
        card.className = `order-card status-${order.status} priority-${order.priority_level}`;
        card.dataset.orderId = order.order_detail_id;
        card.dataset.status = order.status;

        const statusText = {
            'ordered': '注文済み',
            'ready': 'スタンバイ完了',
            'delivered': '配達済み',
            'cancelled': 'キャンセル'
        };

        const priorityIndicator = order.priority_level !== 'normal' ? 
            `<div class="priority-indicator ${order.priority_level}"></div>` : '';

        const memoSection = order.memo && order.memo.trim() !== '' ? 
            `<div class="order-memo">
                <div class="memo-icon">
                    <i data-lucide="message-circle" class="icon icon--sm"></i>
                </div>
                <div class="memo-text">${this.escapeHtml(order.memo)}</div>
            </div>` : '';

        card.innerHTML = `
            ${priorityIndicator}
            ${this.createCancelButton(order)}
            <div class="order-header">
                <div class="order-info">
                    <span class="order-id">#${order.order_detail_id}</span>
                    <span class="room-number"><i data-lucide="home" class="icon icon--sm"></i> ${order.room_number}</span>
                </div>
                <div class="order-time">
                    <div class="time-ago">${order.time_ago}</div>
                    <div class="time-exact">(${order.formatted_time})</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-main">
                    <span class="product-name">${order.product_name} <span class="quantity">×${order.quantity}</span></span>
                </div>
            </div>
            ${memoSection}
            <div class="swipe-indicator">
                ${this.getSwipeIndicatorText(order.status)}
            </div>
        `;

        // Add swipe gesture event listeners
        this.addSwipeListeners(card, order);

        return card;
    }

    /**
     * Create cancel button for order card
     */
    createCancelButton(order) {
        // Only show menu button for active orders (not completed or cancelled)
        if (order.status === 'delivered' || order.status === 'cancelled') {
            return '';
        }
        
        return `
            <div class="order-menu" data-order-id="${order.order_detail_id}">
                <button class="menu-btn" onclick="window.kitchenMonitor.toggleOrderMenu(${order.order_detail_id})" title="メニューを開く">
                    <i data-lucide="more-horizontal" class="icon icon--sm"></i>
                </button>
                <div class="menu-dropdown" id="menu-${order.order_detail_id}" style="display: none;">
                    <button class="menu-item cancel-item" onclick="window.kitchenMonitor.handleCancelOrder(${order.order_detail_id}, '${order.product_name}')">
                        <i data-lucide="x" class="icon icon--sm"></i>
                        注文をキャンセル
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Handle status update with optimistic UI update
     */
    async handleStatusUpdate(orderDetailId, newStatus) {
        try {
            // Optimistic UI update
            this.updateCardStatusOptimistic(orderDetailId, newStatus);

            // API call
            const response = await fetch(`${this.config.apiBaseUrl}update_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.config.csrfToken
                },
                body: JSON.stringify({
                    order_detail_id: orderDetailId,
                    new_status: newStatus,
                    updated_by: 'kitchen_monitor',
                    csrf_token: this.config.csrfToken
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            this.showNotification('ステータスが更新されました', 'success');
            
            // Refresh orders to get latest data
            setTimeout(() => this.fetchOrders(), 500);

        } catch (error) {
            console.error('Failed to update status:', error);
            this.showNotification(`更新に失敗しました: ${error.message}`, 'error');
            
            // Revert optimistic update on error
            this.revertCardStatusOptimistic(orderDetailId);
        }
    }

    /**
     * Update card status optimistically (immediate UI feedback)
     */
    updateCardStatusOptimistic(orderDetailId, newStatus) {
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        if (!card) return;

        // Store original status for potential rollback
        card.dataset.originalStatus = card.dataset.status;
        
        // Update card appearance
        card.className = card.className.replace(/status-\w+/, `status-${newStatus}`);
        card.dataset.status = newStatus;

        // Status badge has been removed, no need to update it

        // Update cancel button visibility
        const orderMenu = card.querySelector('.order-menu');
        if (orderMenu && (newStatus === 'delivered' || newStatus === 'cancelled')) {
            orderMenu.style.display = 'none';
        } else if (orderMenu && (newStatus === 'ordered' || newStatus === 'ready')) {
            orderMenu.style.display = 'block';
        }

        // Add processing indicator
        card.style.opacity = '0.7';
        card.style.pointerEvents = 'none';
    }

    /**
     * Revert optimistic card status update
     */
    revertCardStatusOptimistic(orderDetailId) {
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        if (!card || !card.dataset.originalStatus) return;

        const originalStatus = card.dataset.originalStatus;
        
        // Revert card appearance
        card.className = card.className.replace(/status-\w+/, `status-${originalStatus}`);
        card.dataset.status = originalStatus;

        // Remove processing indicator
        card.style.opacity = '';
        card.style.pointerEvents = '';

        // Refresh the entire display to ensure consistency
        this.fetchOrders();
    }

    /**
     * Handle cancel order request
     */
    handleCancelOrder(orderDetailId, productName) {
        this.currentCancelOrderId = orderDetailId;
        this.elements.cancelMessage.textContent = `「${productName}」をキャンセルしますか？`;
        this.showCancelModal();
    }

    /**
     * Confirm cancel order
     */
    confirmCancelOrder() {
        if (this.currentCancelOrderId) {
            this.handleStatusUpdate(this.currentCancelOrderId, 'cancelled');
            this.hideCancelModal();
            this.currentCancelOrderId = null;
        }
    }

    /**
     * Show cancel confirmation modal
     */
    showCancelModal() {
        this.elements.cancelModal.style.display = 'flex';
    }

    /**
     * Hide cancel confirmation modal
     */
    hideCancelModal() {
        this.elements.cancelModal.style.display = 'none';
        this.currentCancelOrderId = null;
    }

    /**
     * Update statistics display
     */
    updateStats(stats) {
        // Update header stats (existing)
        if (this.elements.pendingCount) {
            this.elements.pendingCount.textContent = stats.total_pending || 0;
        }
        
        // Column counts are updated in updateOrdersDisplay method
        // based on actual displayed orders
    }

    /**
     * Update last update time display
     */
    updateLastUpdateTime() {
        if (this.lastUpdateTime) {
            const time = new Date(this.lastUpdateTime).toLocaleTimeString('ja-JP', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            this.elements.lastUpdate.textContent = time;
        }
    }

    /**
     * Toggle show completed orders
     */
    toggleShowCompleted() {
        this.showCompletedModal();
    }

    /**
     * Show completed orders modal
     */
    async showCompletedModal() {
        this.elements.completedModal.style.display = 'flex';
        await this.fetchCompletedOrders();
        
        // Initialize Lucide icons
        setTimeout(() => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }, 0);
    }

    /**
     * Hide completed orders modal
     */
    hideCompletedModal() {
        this.elements.completedModal.style.display = 'none';
    }

    /**
     * Fetch completed orders for modal
     */
    async fetchCompletedOrders() {
        try {
            this.elements.completedLoading.style.display = 'flex';
            this.elements.completedEmpty.style.display = 'none';
            this.elements.completedOrdersGrid.style.display = 'none';

            const response = await fetch(`${this.config.apiBaseUrl}get_orders.php?show_completed=true`);
            const data = await response.json();

            if (data.success) {
                this.displayCompletedOrders(data.data.orders, data.data.stats);
            } else {
                throw new Error(data.message || 'Failed to fetch completed orders');
            }

        } catch (error) {
            console.error('Failed to fetch completed orders:', error);
            this.showNotification(`完了済み注文の取得に失敗しました: ${error.message}`, 'error');
            this.elements.completedLoading.style.display = 'none';
            this.elements.completedEmpty.style.display = 'flex';
        }
    }

    /**
     * Display completed orders in modal
     */
    displayCompletedOrders(orders, stats) {
        // Hide loading
        this.elements.completedLoading.style.display = 'none';

        // Update stats
        this.elements.modalDeliveredCount.textContent = stats.total_delivered_today || 0;
        this.elements.modalCancelledCount.textContent = stats.total_cancelled_today || 0;

        // Filter completed orders
        const completedOrders = orders.filter(order => 
            order.status === 'delivered' || order.status === 'cancelled'
        );

        if (completedOrders.length === 0) {
            this.elements.completedEmpty.style.display = 'flex';
            this.elements.completedOrdersGrid.style.display = 'none';
            return;
        }

        // Show and populate grid
        this.elements.completedEmpty.style.display = 'none';
        this.elements.completedOrdersGrid.style.display = 'grid';
        this.elements.completedOrdersGrid.innerHTML = '';

        // Sort by completion time (newest first)
        completedOrders.sort((a, b) => {
            const timeA = new Date(a.status_updated_at || a.order_datetime).getTime();
            const timeB = new Date(b.status_updated_at || b.order_datetime).getTime();
            return timeB - timeA;
        });

        // Create cards
        completedOrders.forEach(order => {
            const card = this.createOrderCard(order);
            this.elements.completedOrdersGrid.appendChild(card);
        });
    }

    /**
     * Toggle audio notifications
     */
    toggleAudio() {
        this.audioEnabled = !this.audioEnabled;
        this.updateAudioIcon();
        this.saveUserPreference('kitchen_audio_enabled', this.audioEnabled);
        
        if (this.audioEnabled) {
            this.showNotification('音声通知がオンになりました', 'info');
        } else {
            this.showNotification('音声通知がオフになりました', 'info');
        }
    }

    /**
     * Update audio icon
     */
    updateAudioIcon() {
        // Update audio icon
        this.elements.audioIcon.setAttribute('data-lucide', this.audioEnabled ? 'volume-2' : 'volume-x');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        this.elements.audioToggle.classList.toggle('muted', !this.audioEnabled);
    }

    /**
     * Play order chime sound
     */
    playOrderChime() {
        if (!this.audioEnabled || !this.elements.orderChime) return;

        try {
            this.elements.orderChime.currentTime = 0;
            this.elements.orderChime.play().catch(console.error);
        } catch (error) {
            console.error('Failed to play chime:', error);
        }
    }

    /**
     * Set connection status
     */
    setConnectionStatus(connected) {
        this.isConnected = connected;
        const indicator = this.elements.connectionIndicator;
        
        if (connected) {
            indicator.classList.remove('disconnected');
        } else {
            indicator.classList.add('disconnected');
        }
    }

    /**
     * Start auto refresh
     */
    startAutoRefresh() {
        this.setRefreshInterval(this.config.autoRefreshInterval);
    }

    /**
     * Set refresh interval
     */
    setRefreshInterval(interval) {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        this.refreshInterval = setInterval(() => {
            this.fetchOrders();
        }, interval);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = 3000) {
        if (window.KitchenNotifications) {
            window.KitchenNotifications.show(message, type, duration);
        } else {
            // Fallback to simple alert
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }

    /**
     * Save user preference to localStorage
     */
    saveUserPreference(key, value) {
        try {
            localStorage.setItem(key, value.toString());
        } catch (error) {
            console.error('Failed to save preference:', error);
        }
    }

    /**
     * Get swipe indicator text based on order status
     */
    getSwipeIndicatorText(status) {
        switch (status) {
            case 'ordered':
                return '<span class="swipe-right">調理完了 <i data-lucide="arrow-right" class="icon icon--sm"></i></span>';
            case 'ready':
                return '<span class="swipe-left"><i data-lucide="arrow-left" class="icon icon--sm"></i> 戻す</span><span class="swipe-right">配達完了 <i data-lucide="arrow-right" class="icon icon--sm"></i></span>';
            default:
                return '';
        }
    }

    /**
     * Add swipe gesture listeners to order card
     */
    addSwipeListeners(card, order) {
        // Only add swipe for active orders
        if (order.status !== 'ordered' && order.status !== 'ready') {
            return;
        }

        let startX = 0;
        let startY = 0;
        let startTime = 0;
        let isDragging = false;

        const handleTouchStart = (e) => {
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            startTime = Date.now();
            isDragging = false;
            this.currentSwipeCard = card;
            
            // Add visual feedback
            card.classList.add('swipe-active');
        };

        const handleTouchMove = (e) => {
            if (!this.currentSwipeCard) return;
            
            const touch = e.touches[0];
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;
            
            // Check if it's a horizontal swipe
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
                isDragging = true;
                e.preventDefault();
                
                // Apply visual feedback for right swipe (positive deltaX)
                if (deltaX > 0) {
                    const translateX = Math.max(0, Math.min(deltaX, this.swipeThreshold + 20));
                    card.style.transform = `translateX(${translateX}px)`;
                    card.style.opacity = Math.max(0.7, 1 - (translateX / (this.swipeThreshold + 20)) * 0.3);
                    
                    // Update swipe indicator for right swipe
                    if (translateX > this.swipeThreshold * 0.5) {
                        card.classList.add('swipe-ready-right');
                        card.classList.remove('swipe-ready-left');
                    } else {
                        card.classList.remove('swipe-ready-right', 'swipe-ready-left');
                    }
                }
                // Apply visual feedback for left swipe (negative deltaX) - only for ready status
                else if (deltaX < 0 && order.status === 'ready') {
                    const translateX = Math.max(-this.swipeThreshold - 20, Math.min(deltaX, 0));
                    card.style.transform = `translateX(${translateX}px)`;
                    card.style.opacity = Math.max(0.7, 1 - (Math.abs(translateX) / (this.swipeThreshold + 20)) * 0.3);
                    
                    // Update swipe indicator for left swipe
                    if (Math.abs(translateX) > this.swipeThreshold * 0.5) {
                        card.classList.add('swipe-ready-left');
                        card.classList.remove('swipe-ready-right');
                    } else {
                        card.classList.remove('swipe-ready-right', 'swipe-ready-left');
                    }
                }
            }
        };

        const handleTouchEnd = (e) => {
            if (!this.currentSwipeCard) return;
            
            const touch = e.changedTouches[0];
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;
            const deltaTime = Date.now() - startTime;
            
            // Remove visual feedback classes
            card.classList.remove('swipe-active', 'swipe-ready-right', 'swipe-ready-left');
            
            // Check if it's a valid right swipe gesture
            const isValidRightSwipe = 
                Math.abs(deltaX) > Math.abs(deltaY) && // More horizontal than vertical
                deltaX > this.swipeThreshold && // Sufficient distance to the right
                deltaTime < this.swipeTimeThreshold && // Quick enough
                isDragging; // Was actually dragging
            
            // Check if it's a valid left swipe gesture (only for ready status)
            const isValidLeftSwipe = 
                Math.abs(deltaX) > Math.abs(deltaY) && // More horizontal than vertical
                deltaX < -this.swipeThreshold && // Sufficient distance to the left
                deltaTime < this.swipeTimeThreshold && // Quick enough
                isDragging && // Was actually dragging
                order.status === 'ready'; // Only allow for ready orders
            
            if (isValidRightSwipe) {
                // Execute right swipe action (advance status)
                this.executeSwipeAction(order, 'right');
            } else if (isValidLeftSwipe) {
                // Execute left swipe action (go back)
                this.executeSwipeAction(order, 'left');
            } else {
                // Reset card position
                card.style.transform = '';
                card.style.opacity = '';
            }
            
            this.currentSwipeCard = null;
        };

        const handleTouchCancel = () => {
            if (this.currentSwipeCard) {
                this.currentSwipeCard.classList.remove('swipe-active', 'swipe-ready-right', 'swipe-ready-left');
                this.currentSwipeCard.style.transform = '';
                this.currentSwipeCard.style.opacity = '';
                this.currentSwipeCard = null;
            }
        };

        // Touch events
        card.addEventListener('touchstart', handleTouchStart, { passive: true });
        card.addEventListener('touchmove', handleTouchMove, { passive: false });
        card.addEventListener('touchend', handleTouchEnd, { passive: true });
        card.addEventListener('touchcancel', handleTouchCancel, { passive: true });

        // Mouse events for desktop testing (optional)
        let mouseDown = false;
        const handleMouseDown = (e) => {
            mouseDown = true;
            handleTouchStart({
                touches: [{ clientX: e.clientX, clientY: e.clientY }]
            });
        };
        
        const handleMouseMove = (e) => {
            if (!mouseDown) return;
            handleTouchMove({
                touches: [{ clientX: e.clientX, clientY: e.clientY }],
                preventDefault: () => e.preventDefault()
            });
        };
        
        const handleMouseUp = (e) => {
            if (!mouseDown) return;
            mouseDown = false;
            handleTouchEnd({
                changedTouches: [{ clientX: e.clientX, clientY: e.clientY }]
            });
        };

        card.addEventListener('mousedown', handleMouseDown);
        card.addEventListener('mousemove', handleMouseMove);
        card.addEventListener('mouseup', handleMouseUp);
        card.addEventListener('mouseleave', () => {
            if (mouseDown) {
                mouseDown = false;
                handleTouchCancel();
            }
        });
    }

    /**
     * Execute swipe action based on order status and direction
     */
    executeSwipeAction(order, direction = 'right') {
        let newStatus;
        let actionText;
        let animationDirection;
        
        if (direction === 'right') {
            // Right swipe - advance status
            switch (order.status) {
                case 'ordered':
                    newStatus = 'ready';
                    actionText = '調理完了';
                    break;
                case 'ready':
                    newStatus = 'delivered';
                    actionText = '配達完了';
                    break;
                default:
                    return; // No action for other statuses
            }
            animationDirection = '100%';
        } else if (direction === 'left' && order.status === 'ready') {
            // Left swipe - go back (only for ready status)
            newStatus = 'ordered';
            actionText = '注文済みに戻す';
            animationDirection = '-100%';
        } else {
            return; // Invalid action
        }
        
        // Animate card swipe out
        const card = this.currentSwipeCard;
        if (card) {
            card.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
            card.style.transform = `translateX(${animationDirection})`;
            card.style.opacity = '0';
            
            // Show feedback notification
            this.showNotification(`${actionText}としてマークしました`, 'success');
            
            // Execute status update after animation
            setTimeout(() => {
                this.handleStatusUpdate(order.order_detail_id, newStatus);
            }, 150);
        } else {
            // Fallback if no current swipe card
            this.handleStatusUpdate(order.order_detail_id, newStatus);
            this.showNotification(`${actionText}としてマークしました`, 'success');
        }
    }

    /**
     * Toggle view switch button (ordered <-> ready)
     */
    toggleViewSwitch() {
        // Only work if not in 'both' mode
        if (this.currentView === 'both') {
            return; // Button is disabled in both mode
        }

        const newView = this.currentView === 'ordered' ? 'ready' : 'ordered';
        this.setColumnView(newView);
    }

    /**
     * Set column view mode
     */
    setColumnView(view) {
        this.currentView = view;
        this.updateColumnView();
        this.updateViewSwitchButton();
        this.updateURL(view);
        this.saveUserPreference('kitchen_column_view', view);
    }

    /**
     * Toggle room group display mode
     */
    toggleRoomGroups() {
        this.showRoomGroups = !this.showRoomGroups;
        this.updateRoomGroupButton();
        this.updateOrdersDisplay(); // Refresh display with new mode
    }

    /**
     * Update room group button state
     */
    updateRoomGroupButton() {
        if (!this.elements.roomGroupBtn) return;

        const btn = this.elements.roomGroupBtn;
        const span = btn.querySelector('span:not(.icon)');
        
        if (this.showRoomGroups) {
            btn.style.background = '#229954';
            if (span) span.textContent = '個別表示';
        } else {
            btn.style.background = 'var(--color-delivered)';
            if (span) span.textContent = '部屋別まとめ';
        }
    }

    /**
     * Display room group cards
     */
    displayRoomGroupCards(readyOrders, container) {
        // Group orders by room
        const ordersByRoom = {};
        readyOrders.forEach(order => {
            const roomNumber = order.room_number || 'Unknown';
            if (!ordersByRoom[roomNumber]) {
                ordersByRoom[roomNumber] = [];
            }
            ordersByRoom[roomNumber].push(order);
        });

        // Sort rooms by number
        const sortedRooms = Object.keys(ordersByRoom).sort((a, b) => {
            const numA = parseInt(a) || 999999;
            const numB = parseInt(b) || 999999;
            return numA - numB;
        });

        // Create room group cards
        sortedRooms.forEach(roomNumber => {
            const roomOrders = ordersByRoom[roomNumber];
            const roomCard = this.createRoomCard(roomNumber, roomOrders);
            container.appendChild(roomCard);
        });
    }

    /**
     * Create room summary card
     */
    createRoomCard(roomNumber, orders) {
        const card = document.createElement('div');
        card.className = 'order-card room-card';
        
        // Calculate totals
        const totalItems = orders.reduce((sum, order) => sum + parseInt(order.quantity || 0), 0);
        const oldestOrder = orders.reduce((oldest, order) => {
            const orderTime = new Date(order.order_datetime);
            const oldestTime = new Date(oldest.order_datetime);
            return orderTime < oldestTime ? order : oldest;
        });

        // Generate order list
        const orderList = orders.map(order => 
            `<div class="room-order-item">${order.product_name} ×${order.quantity}</div>`
        ).join('');

        card.innerHTML = `
            <div class="order-header">
                <div class="order-info">
                    <span class="room-title">
                        <i data-lucide="home" class="icon icon--md"></i>
                        部屋 ${roomNumber}
                    </span>
                </div>
                <div class="room-stats">
                    <div class="room-total-items">${totalItems}品</div>
                    <div class="room-order-count">${orders.length}件</div>
                </div>
            </div>
            <div class="room-order-list">
                ${orderList}
            </div>
            <div class="room-time-info">
                最古注文: ${oldestOrder.time_ago} (${oldestOrder.formatted_time})
            </div>
            <div class="room-actions">
                <button class="btn btn-success" onclick="window.kitchenMonitor.handleRoomDelivery('${roomNumber}', ${JSON.stringify(orders.map(o => o.order_detail_id))})">
                    <i data-lucide="truck" class="icon icon--sm"></i>
                    部屋 ${roomNumber} 一括配達
                </button>
            </div>
        `;

        return card;
    }

    /**
     * Handle room delivery (mark all orders as delivered)
     */
    async handleRoomDelivery(roomNumber, orderDetailIds) {
        if (!confirm(`部屋 ${roomNumber} の全ての注文を配達完了にしますか？`)) {
            return;
        }

        try {
            // Update all orders in parallel
            const promises = orderDetailIds.map(orderId => 
                this.handleStatusUpdate(orderId, 'delivered')
            );
            
            await Promise.all(promises);
            
            this.showNotification(`部屋 ${roomNumber} の注文をすべて配達完了にしました`, 'success');
            
        } catch (error) {
            console.error('Room delivery failed:', error);
            this.showNotification(`一括配達に失敗しました: ${error.message}`, 'error');
        }
    }

    /**
     * Update column visibility based on current view
     */
    updateColumnView() {
        const orderedColumn = this.elements.orderedColumn;
        const readyColumn = this.elements.readyColumn;
        const ordersColumns = this.elements.ordersColumns;
        
        // Update button states
        document.querySelectorAll('.view-toggle').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.getElementById(`view-${this.currentView}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        switch (this.currentView) {
            case 'ordered':
                orderedColumn.style.display = 'block';
                readyColumn.style.display = 'none';
                ordersColumns.style.gridTemplateColumns = '1fr';
                break;
            case 'ready':
                orderedColumn.style.display = 'none';
                readyColumn.style.display = 'block';
                ordersColumns.style.gridTemplateColumns = '1fr';
                break;
            case 'both':
            default:
                orderedColumn.style.display = 'block';
                readyColumn.style.display = 'block';
                ordersColumns.style.gridTemplateColumns = '1fr 1fr';
                break;
        }

        // Show/hide room group button based on view
        if (this.elements.roomGroupBtn) {
            if (this.currentView === 'ready') {
                this.elements.roomGroupBtn.style.display = 'flex';
            } else {
                this.elements.roomGroupBtn.style.display = 'none';
                this.showRoomGroups = false; // Reset room group mode when not in ready view
            }
        }
    }

    /**
     * Update view switch button state
     */
    updateViewSwitchButton() {
        if (!this.elements.viewSwitchBtn) return;

        const btn = this.elements.viewSwitchBtn;
        const span = btn.querySelector('span');
        
        // Update data attribute and text
        btn.setAttribute('data-view', this.currentView);
        
        switch (this.currentView) {
            case 'ordered':
                span.textContent = '調理済みを表示';
                break;
            case 'ready':
                span.textContent = '注文済みを表示';
                break;
            case 'both':
                span.textContent = '表示切替';
                break;
        }
    }

    /**
     * Show new order notification bar
     */
    showNewOrderBar(orderCount) {
        if (!this.elements.newOrderBar || !this.elements.newOrderMessage) {
            return;
        }

        const message = orderCount === 1 
            ? '新しい注文が入りました' 
            : `新しい注文が${orderCount}件入りました`;
        
        this.elements.newOrderMessage.textContent = message;
        this.elements.newOrderBar.style.display = 'block';
        
        // Force reflow to ensure display change is applied
        this.elements.newOrderBar.offsetHeight;
        
        this.elements.newOrderBar.classList.add('show');

        // Auto-hide after 8 seconds
        if (this.newOrderBarTimeout) {
            clearTimeout(this.newOrderBarTimeout);
        }
        
        this.newOrderBarTimeout = setTimeout(() => {
            this.hideNewOrderBar();
        }, 8000);
    }

    /**
     * Hide new order notification bar
     */
    hideNewOrderBar() {
        if (!this.elements.newOrderBar) return;

        this.elements.newOrderBar.classList.remove('show');
        
        // Wait for animation to complete, then fully hide
        setTimeout(() => {
            if (!this.elements.newOrderBar.classList.contains('show')) {
                this.elements.newOrderBar.style.display = 'none';
                // Reset any animation state
                this.elements.newOrderBar.style.transform = '';
                this.elements.newOrderBar.style.opacity = '';
            }
        }, 450); // Slightly longer than transition duration (400ms)
        
        if (this.newOrderBarTimeout) {
            clearTimeout(this.newOrderBarTimeout);
            this.newOrderBarTimeout = null;
        }
    }


    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get URL parameter value
     */
    getURLParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    /**
     * Initialize view from URL parameter
     */
    initializeViewFromURL() {
        const viewParam = this.getURLParameter('view');
        const validViews = ['ordered', 'ready', 'both'];
        
        if (viewParam && validViews.includes(viewParam)) {
            this.currentView = viewParam;
        }
        // If no valid view parameter, keep the default or saved preference
    }

    /**
     * Update URL with current view
     */
    updateURL(view) {
        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        
        // Use replaceState to avoid adding to browser history on every view change
        window.history.replaceState({ view: view }, '', url.toString());
    }

    /**
     * Handle browser back/forward button navigation
     */
    handlePopState(event) {
        const view = event.state?.view || this.getURLParameter('view') || 'both';
        
        if (view !== this.currentView) {
            // Update view without calling updateURL to avoid infinite loop
            this.currentView = view;
            this.updateColumnView();
            this.updateViewSwitchButton();
        }
    }

    /**
     * Toggle order menu dropdown
     */
    toggleOrderMenu(orderId) {
        const menuElement = document.getElementById(`menu-${orderId}`);
        if (!menuElement) return;

        // Close other open menus
        if (this.openMenuId && this.openMenuId !== orderId) {
            this.closeMenu(this.openMenuId);
        }

        // Toggle current menu
        if (this.openMenuId === orderId) {
            this.closeMenu(orderId);
        } else {
            this.openMenu(orderId);
        }
    }

    /**
     * Open specific menu
     */
    openMenu(orderId) {
        const menuElement = document.getElementById(`menu-${orderId}`);
        if (!menuElement) return;

        menuElement.style.display = 'block';
        menuElement.classList.remove('hide');
        this.openMenuId = orderId;
    }

    /**
     * Close specific menu
     */
    closeMenu(orderId) {
        const menuElement = document.getElementById(`menu-${orderId}`);
        if (!menuElement) return;

        menuElement.classList.add('hide');
        setTimeout(() => {
            menuElement.style.display = 'none';
            menuElement.classList.remove('hide');
        }, 150);
        
        if (this.openMenuId === orderId) {
            this.openMenuId = null;
        }
    }

    /**
     * Close all open menus
     */
    closeAllMenus() {
        if (this.openMenuId) {
            this.closeMenu(this.openMenuId);
        }
    }

    /**
     * Cleanup and destroy
     */
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        if (this.newOrderBarTimeout) {
            clearTimeout(this.newOrderBarTimeout);
        }
    }
}