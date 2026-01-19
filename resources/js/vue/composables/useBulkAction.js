/**
 * Vue 3 Composable for Bulk Actions
 * 
 * Usage:
 * import { useBulkAction } from '@/vendor/action-engine/composables/useBulkAction'
 * 
 * const { execute, cancel, undo, progress, isLoading, error } = useBulkAction()
 * 
 * await execute({
 *   action: 'delete',
 *   model: 'App\\Models\\User',
 *   filters: { ids: [1, 2, 3] },
 *   options: { with_undo: true }
 * })
 */

import { ref, computed, onUnmounted } from 'vue'

export function useBulkAction(apiPrefix = '/api/bulk-actions') {
    const execution = ref(null)
    const isLoading = ref(false)
    const error = ref(null)
    const pollInterval = ref(null)

    const progress = computed(() => {
        if (!execution.value) return null
        return {
            uuid: execution.value.uuid,
            status: execution.value.status,
            percentage: execution.value.progress_percentage || 0,
            processed: execution.value.processed_records || 0,
            failed: execution.value.failed_records || 0,
            total: execution.value.total_records || 0,
            canUndo: execution.value.can_undo,
            undoExpiresAt: execution.value.undo_expires_at,
        }
    })

    const isInProgress = computed(() => {
        return ['pending', 'processing'].includes(execution.value?.status)
    })

    const isComplete = computed(() => {
        return ['completed', 'failed', 'cancelled', 'partially_completed'].includes(execution.value?.status)
    })

    /**
     * Execute a bulk action
     */
    async function execute(payload, options = {}) {
        isLoading.value = true
        error.value = null

        try {
            const response = await fetch(apiPrefix, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify(payload),
            })

            const data = await response.json()

            if (!response.ok) {
                throw new Error(data.message || 'Failed to execute bulk action')
            }

            execution.value = data.data

            // Start polling if not a dry run
            if (!payload.options?.dry_run && options.poll !== false) {
                startPolling()
            }

            return data.data
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Get progress for current execution
     */
    async function fetchProgress() {
        if (!execution.value?.uuid) return

        try {
            const response = await fetch(`${apiPrefix}/${execution.value.uuid}/progress`, {
                headers: {
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
            })

            const data = await response.json()

            if (response.ok && data.data) {
                // Update execution with progress data
                execution.value = {
                    ...execution.value,
                    ...data.data,
                }

                // Stop polling if complete
                if (isComplete.value) {
                    stopPolling()
                }
            }
        } catch (e) {
            console.error('Failed to fetch progress:', e)
        }
    }

    /**
     * Cancel the current action
     */
    async function cancel() {
        if (!execution.value?.uuid) return

        isLoading.value = true

        try {
            const response = await fetch(`${apiPrefix}/${execution.value.uuid}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
            })

            const data = await response.json()

            if (!response.ok) {
                throw new Error(data.message || 'Failed to cancel action')
            }

            execution.value = data.data
            stopPolling()

            return data.data
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Undo the action
     */
    async function undo() {
        if (!execution.value?.uuid || !execution.value?.can_undo) return

        isLoading.value = true

        try {
            const response = await fetch(`${apiPrefix}/${execution.value.uuid}/undo`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
            })

            const data = await response.json()

            if (!response.ok) {
                throw new Error(data.message || 'Failed to undo action')
            }

            execution.value = data.data.execution

            return data.data
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Preview action (dry run)
     */
    async function preview(payload) {
        isLoading.value = true
        error.value = null

        try {
            const response = await fetch(`${apiPrefix}/preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify(payload),
            })

            const data = await response.json()

            if (!response.ok) {
                throw new Error(data.message || 'Failed to preview action')
            }

            return data.data
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Start polling for progress
     */
    function startPolling(intervalMs = 2000) {
        stopPolling()
        pollInterval.value = setInterval(fetchProgress, intervalMs)
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollInterval.value) {
            clearInterval(pollInterval.value)
            pollInterval.value = null
        }
    }

    /**
     * Reset state
     */
    function reset() {
        stopPolling()
        execution.value = null
        error.value = null
        isLoading.value = false
    }

    /**
     * Get auth headers (csrf + bearer token)
     */
    function getAuthHeaders() {
        const headers = {}

        // CSRF token for Laravel
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken
        }

        // Bearer token if available
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        return headers
    }

    // Cleanup on unmount
    onUnmounted(() => {
        stopPolling()
    })

    return {
        // State
        execution,
        progress,
        isLoading,
        error,
        isInProgress,
        isComplete,

        // Actions
        execute,
        cancel,
        undo,
        preview,
        fetchProgress,
        reset,

        // Polling control
        startPolling,
        stopPolling,
    }
}
