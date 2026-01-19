/**
 * Alpine.js Bulk Action Component
 * 
 * Usage:
 * <div x-data="bulkAction({ apiPrefix: '/api/bulk-actions' })">
 *   <button @click="execute({ action: 'delete', model: 'App\\Models\\User', filters: { ids: selectedIds } })">
 *     Delete Selected
 *   </button>
 *   
 *   <template x-if="isInProgress">
 *     <div class="progress-bar" :style="{ width: progress.percentage + '%' }"></div>
 *   </template>
 * </div>
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('bulkAction', (config = {}) => ({
        apiPrefix: config.apiPrefix || '/api/bulk-actions',
        execution: null,
        isLoading: false,
        error: null,
        pollInterval: null,

        get progress() {
            if (!this.execution) return null
            return {
                uuid: this.execution.uuid,
                status: this.execution.status,
                percentage: this.execution.progress_percentage || 0,
                processed: this.execution.processed_records || 0,
                failed: this.execution.failed_records || 0,
                total: this.execution.total_records || 0,
                canUndo: this.execution.can_undo,
                undoExpiresAt: this.execution.undo_expires_at,
            }
        },

        get isInProgress() {
            return ['pending', 'processing'].includes(this.execution?.status)
        },

        get isComplete() {
            return ['completed', 'failed', 'cancelled', 'partially_completed'].includes(this.execution?.status)
        },

        getAuthHeaders() {
            const headers = {}

            const csrfMeta = document.querySelector('meta[name="csrf-token"]')
            if (csrfMeta) {
                headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content')
            }

            const token = localStorage.getItem('token') || sessionStorage.getItem('token')
            if (token) {
                headers['Authorization'] = `Bearer ${token}`
            }

            return headers
        },

        async execute(payload, options = {}) {
            this.isLoading = true
            this.error = null

            try {
                const response = await fetch(this.apiPrefix, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...this.getAuthHeaders(),
                    },
                    body: JSON.stringify(payload),
                })

                const data = await response.json()

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to execute bulk action')
                }

                this.execution = data.data

                if (!payload.options?.dry_run && options.poll !== false) {
                    this.startPolling()
                }

                this.$dispatch('bulk-action-started', this.execution)

                return data.data
            } catch (e) {
                this.error = e.message
                this.$dispatch('bulk-action-error', { error: e.message })
                throw e
            } finally {
                this.isLoading = false
            }
        },

        async fetchProgress() {
            if (!this.execution?.uuid) return

            try {
                const response = await fetch(`${this.apiPrefix}/${this.execution.uuid}/progress`, {
                    headers: {
                        'Accept': 'application/json',
                        ...this.getAuthHeaders(),
                    },
                })

                const data = await response.json()

                if (response.ok && data.data) {
                    this.execution = { ...this.execution, ...data.data }

                    if (this.isComplete) {
                        this.stopPolling()
                        this.$dispatch('bulk-action-completed', this.execution)
                    }
                }
            } catch (e) {
                console.error('Failed to fetch progress:', e)
            }
        },

        async cancel() {
            if (!this.execution?.uuid) return

            this.isLoading = true

            try {
                const response = await fetch(`${this.apiPrefix}/${this.execution.uuid}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        ...this.getAuthHeaders(),
                    },
                })

                const data = await response.json()

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to cancel action')
                }

                this.execution = data.data
                this.stopPolling()
                this.$dispatch('bulk-action-cancelled', this.execution)

                return data.data
            } catch (e) {
                this.error = e.message
                throw e
            } finally {
                this.isLoading = false
            }
        },

        async undo() {
            if (!this.execution?.uuid || !this.execution?.can_undo) return

            this.isLoading = true

            try {
                const response = await fetch(`${this.apiPrefix}/${this.execution.uuid}/undo`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        ...this.getAuthHeaders(),
                    },
                })

                const data = await response.json()

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to undo action')
                }

                this.execution = data.data.execution
                this.$dispatch('bulk-action-undone', data.data)

                return data.data
            } catch (e) {
                this.error = e.message
                throw e
            } finally {
                this.isLoading = false
            }
        },

        async preview(payload) {
            this.isLoading = true
            this.error = null

            try {
                const response = await fetch(`${this.apiPrefix}/preview`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...this.getAuthHeaders(),
                    },
                    body: JSON.stringify(payload),
                })

                const data = await response.json()

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to preview action')
                }

                return data.data
            } catch (e) {
                this.error = e.message
                throw e
            } finally {
                this.isLoading = false
            }
        },

        startPolling(intervalMs = 2000) {
            this.stopPolling()
            this.pollInterval = setInterval(() => this.fetchProgress(), intervalMs)
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval)
                this.pollInterval = null
            }
        },

        reset() {
            this.stopPolling()
            this.execution = null
            this.error = null
            this.isLoading = false
        },

        destroy() {
            this.stopPolling()
        },
    }))
})
