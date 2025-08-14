/**
 * Kitchen Monitor Status Update System
 * 
 * Handles order status updates with optimistic UI and error handling
 */

class StatusUpdateManager {
    constructor() {
        this.pendingUpdates = new Map();
        this.config = window.KITCHEN_MONITOR_CONFIG || {};
        this.maxRetries = 3;
        this.retryDelay = 1000; // 1 second
    }

    /**
     * Update order status with optimistic UI update
     */
    async updateStatus(orderDetailId, newStatus, options = {}) {
        const updateId = this.generateUpdateId();
        const updateData = {
            orderDetailId,
            newStatus,
            originalStatus: null,
            options,
            retryCount: 0,
            timestamp: Date.now()
        };

        try {
            // Store the update as pending
            this.pendingUpdates.set(updateId, updateData);

            // Get original status for rollback
            const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
            if (card) {
                updateData.originalStatus = card.dataset.status;
            }

            // Optimistic UI update
            this.applyOptimisticUpdate(orderDetailId, newStatus);

            // API call
            const result = await this.performStatusUpdate(updateData);

            if (result.success) {
                this.handleUpdateSuccess(updateId, result);
            } else {
                throw new Error(result.message || 'Status update failed');
            }

        } catch (error) {
            console.error('Status update failed:', error);
            await this.handleUpdateFailure(updateId, error);
        }

        return updateId;
    }

    /**
     * Perform the actual API call
     */
    async performStatusUpdate(updateData) {
        const { orderDetailId, newStatus, options } = updateData;

        const payload = {
            order_detail_id: orderDetailId,
            new_status: newStatus,
            updated_by: options.updatedBy || 'kitchen_monitor',
            note: options.note || '',
            csrf_token: this.config.csrfToken,
            bulk_update: options.bulkUpdate || false
        };

        if (options.orderSessionId) {
            payload.order_session_id = options.orderSessionId;
        }

        const response = await fetch(`${this.config.apiBaseUrl}update_status.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.config.csrfToken
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return data;
    }

    /**
     * Apply optimistic UI update
     */
    applyOptimisticUpdate(orderDetailId, newStatus) {
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        if (!card) return;

        // Add processing state
        card.classList.add('updating');
        card.style.opacity = '0.7';
        card.style.pointerEvents = 'none';

        // Update status classes
        const statusClasses = ['status-ordered', 'status-ready', 'status-delivered', 'status-cancelled'];
        statusClasses.forEach(cls => card.classList.remove(cls));
        card.classList.add(`status-${newStatus}`);
        card.dataset.status = newStatus;

        // Update status badge
        const statusBadge = card.querySelector('.status-badge');
        if (statusBadge) {
            const statusText = {
                'ordered': '注文済み',
                'ready': 'スタンバイ完了',
                'delivered': '配達済み',
                'cancelled': 'キャンセル'
            };
            
            statusBadge.className = `status-badge status-${newStatus}`;
            statusBadge.textContent = statusText[newStatus];
        }

        // Update action buttons with disabled state
        const actionButtons = card.querySelectorAll('.action-btn');
        actionButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });

        // Add loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'update-loading';
        loadingIndicator.innerHTML = '<div class="loading-spinner"></div>';
        card.appendChild(loadingIndicator);
    }

    /**
     * Handle successful update
     */
    handleUpdateSuccess(updateId, result) {
        const updateData = this.pendingUpdates.get(updateId);
        if (!updateData) return;

        const { orderDetailId, newStatus } = updateData;

        // Remove processing state
        this.removeProcessingState(orderDetailId);

        // Show success notification
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        const productName = card ? card.querySelector('.product-name')?.textContent : 'Order';
        
        if (window.KitchenNotifications) {
            window.KitchenNotifications.showOrderUpdate(productName, newStatus);
        }

        // Update local order data if kitchen monitor is available
        if (window.kitchenMonitor) {
            window.kitchenMonitor.updateOrderInLocalData(orderDetailId, newStatus);
        }

        // Clean up
        this.pendingUpdates.delete(updateId);

        // Trigger refresh after a short delay to ensure data consistency
        setTimeout(() => {
            if (window.kitchenMonitor) {
                window.kitchenMonitor.fetchOrders();
            }
        }, 500);
    }

    /**
     * Handle failed update
     */
    async handleUpdateFailure(updateId, error) {
        const updateData = this.pendingUpdates.get(updateId);
        if (!updateData) return;

        const { orderDetailId, retryCount } = updateData;

        // Increment retry count
        updateData.retryCount = retryCount + 1;

        // Check if we should retry
        if (updateData.retryCount <= this.maxRetries) {
            console.log(`Retrying status update (attempt ${updateData.retryCount}/${this.maxRetries})`);
            
            // Wait before retry
            await this.delay(this.retryDelay * updateData.retryCount);
            
            try {
                const result = await this.performStatusUpdate(updateData);
                if (result.success) {
                    this.handleUpdateSuccess(updateId, result);
                    return;
                } else {
                    throw new Error(result.message || 'Retry failed');
                }
            } catch (retryError) {
                console.error(`Retry ${updateData.retryCount} failed:`, retryError);
                if (updateData.retryCount >= this.maxRetries) {
                    this.finalizeFailure(updateId, retryError);
                } else {
                    // Will retry again
                    return this.handleUpdateFailure(updateId, retryError);
                }
            }
        } else {
            this.finalizeFailure(updateId, error);
        }
    }

    /**
     * Finalize failure after all retries exhausted
     */
    finalizeFailure(updateId, error) {
        const updateData = this.pendingUpdates.get(updateId);
        if (!updateData) return;

        const { orderDetailId, originalStatus } = updateData;

        // Revert optimistic update
        this.revertOptimisticUpdate(orderDetailId, originalStatus);

        // Show error notification
        if (window.KitchenNotifications) {
            window.KitchenNotifications.error(
                `ステータス更新に失敗しました: ${error.message}`,
                5000
            );
        }

        // Clean up
        this.pendingUpdates.delete(updateId);

        console.error('Status update failed after all retries:', error);
    }

    /**
     * Revert optimistic UI update
     */
    revertOptimisticUpdate(orderDetailId, originalStatus) {
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        if (!card || !originalStatus) return;

        // Remove processing state
        this.removeProcessingState(orderDetailId);

        // Revert to original status
        const statusClasses = ['status-ordered', 'status-ready', 'status-delivered', 'status-cancelled'];
        statusClasses.forEach(cls => card.classList.remove(cls));
        card.classList.add(`status-${originalStatus}`);
        card.dataset.status = originalStatus;

        // Revert status badge
        const statusBadge = card.querySelector('.status-badge');
        if (statusBadge) {
            const statusText = {
                'ordered': '注文済み',
                'ready': 'スタンバイ完了',
                'delivered': '配達済み',
                'cancelled': 'キャンセル'
            };
            
            statusBadge.className = `status-badge status-${originalStatus}`;
            statusBadge.textContent = statusText[originalStatus];
        }

        // Refresh the display to ensure consistency
        if (window.kitchenMonitor) {
            setTimeout(() => window.kitchenMonitor.fetchOrders(), 100);
        }
    }

    /**
     * Remove processing state from card
     */
    removeProcessingState(orderDetailId) {
        const card = document.querySelector(`[data-order-id="${orderDetailId}"]`);
        if (!card) return;

        // Remove processing classes and styles
        card.classList.remove('updating');
        card.style.opacity = '';
        card.style.pointerEvents = '';

        // Re-enable action buttons
        const actionButtons = card.querySelectorAll('.action-btn');
        actionButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '';
        });

        // Remove loading indicator
        const loadingIndicator = card.querySelector('.update-loading');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
    }

    /**
     * Bulk update multiple orders
     */
    async bulkUpdateBySession(orderSessionId, newStatus, options = {}) {
        const bulkOptions = {
            ...options,
            bulkUpdate: true,
            orderSessionId
        };

        // Get all orders in the session
        const sessionCards = document.querySelectorAll(`[data-order-session="${orderSessionId}"]`);
        const updatePromises = [];

        for (const card of sessionCards) {
            const orderDetailId = parseInt(card.dataset.orderId);
            if (orderDetailId) {
                updatePromises.push(this.updateStatus(orderDetailId, newStatus, bulkOptions));
            }
        }

        try {
            await Promise.all(updatePromises);
            
            if (window.KitchenNotifications) {
                window.KitchenNotifications.success(
                    `セッション ${orderSessionId} の全注文を ${newStatus} に更新しました`
                );
            }
        } catch (error) {
            console.error('Bulk update failed:', error);
            if (window.KitchenNotifications) {
                window.KitchenNotifications.error(
                    `一括更新に失敗しました: ${error.message}`
                );
            }
        }
    }

    /**
     * Check if an order is currently being updated
     */
    isUpdating(orderDetailId) {
        for (const [, updateData] of this.pendingUpdates) {
            if (updateData.orderDetailId === orderDetailId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get pending updates count
     */
    getPendingCount() {
        return this.pendingUpdates.size;
    }

    /**
     * Clear all pending updates (use with caution)
     */
    clearPending() {
        this.pendingUpdates.clear();
    }

    /**
     * Generate unique update ID
     */
    generateUpdateId() {
        return 'update_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Delay utility function
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Validate status transition
     */
    isValidTransition(currentStatus, newStatus) {
        const validTransitions = {
            'ordered': ['ready', 'cancelled'],
            'ready': ['delivered', 'cancelled', 'ordered'],
            'delivered': ['ordered'], // Allow reopening delivered orders
            'cancelled': ['ordered']  // Allow uncancelling
        };

        return validTransitions[currentStatus]?.includes(newStatus) || false;
    }
}

// Initialize global status update manager
window.StatusUpdateManager = new StatusUpdateManager();

// Add CSS for loading states
const statusUpdateStyles = `
<style>
.order-card.updating {
    position: relative;
    transition: all 0.3s ease;
}

.update-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    padding: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.update-loading .loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid var(--text-muted);
    border-top: 2px solid var(--color-ordered);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.action-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5 !important;
}

.action-btn.processing {
    position: relative;
    overflow: hidden;
}

.action-btn.processing::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Error state indicators */
.order-card.update-error {
    border-left-color: var(--color-cancelled);
    animation: error-shake 0.5s ease-in-out;
}

@keyframes error-shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-3px); }
    75% { transform: translateX(3px); }
}

/* Success state indicators */
.order-card.update-success {
    border-left-color: var(--color-delivered);
    animation: success-pulse 0.6s ease-in-out;
}

@keyframes success-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', statusUpdateStyles);

// Extend KitchenMonitor with status update methods
if (window.kitchenMonitor) {
    window.kitchenMonitor.updateOrderInLocalData = function(orderDetailId, newStatus) {
        const orderIndex = this.orders.findIndex(order => order.order_detail_id == orderDetailId);
        if (orderIndex !== -1) {
            this.orders[orderIndex].status = newStatus;
            this.orders[orderIndex].status_updated_at = new Date().toISOString();
        }
    };
}