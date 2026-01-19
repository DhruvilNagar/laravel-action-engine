/**
 * React Hook for Bulk Actions
 * 
 * Usage:
 * import { useBulkAction } from '@/vendor/action-engine/hooks/useBulkAction'
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

import { useState, useCallback, useEffect, useRef } from 'react'

export function useBulkAction(apiPrefix = '/api/bulk-actions') {
    const [execution, setExecution] = useState(null)
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState(null)
    const pollIntervalRef = useRef(null)

    // Computed progress
    const progress = execution ? {
        uuid: execution.uuid,
        status: execution.status,
        percentage: execution.progress_percentage || 0,
        processed: execution.processed_records || 0,
        failed: execution.failed_records || 0,
        total: execution.total_records || 0,
        canUndo: execution.can_undo,
        undoExpiresAt: execution.undo_expires_at,
    } : null

    const isInProgress = ['pending', 'processing'].includes(execution?.status)
    const isComplete = ['completed', 'failed', 'cancelled', 'partially_completed'].includes(execution?.status)

    /**
     * Get auth headers
     */
    const getAuthHeaders = useCallback(() => {
        const headers = {}

        // CSRF token for Laravel
        const csrfMeta = document.querySelector('meta[name="csrf-token"]')
        if (csrfMeta) {
            headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content')
        }

        // Bearer token if available
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        return headers
    }, [])

    /**
     * Fetch progress
     */
    const fetchProgress = useCallback(async () => {
        if (!execution?.uuid) return

        try {
            const response = await fetch(`${apiPrefix}/${execution.uuid}/progress`, {
                headers: {
                    'Accept': 'application/json',
                    ...getAuthHeaders(),
                },
            })

            const data = await response.json()

            if (response.ok && data.data) {
                setExecution(prev => ({
                    ...prev,
                    ...data.data,
                }))
            }
        } catch (e) {
            console.error('Failed to fetch progress:', e)
        }
    }, [execution?.uuid, apiPrefix, getAuthHeaders])

    /**
     * Start polling
     */
    const startPolling = useCallback((intervalMs = 2000) => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current)
        }
        pollIntervalRef.current = setInterval(fetchProgress, intervalMs)
    }, [fetchProgress])

    /**
     * Stop polling
     */
    const stopPolling = useCallback(() => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current)
            pollIntervalRef.current = null
        }
    }, [])

    // Stop polling when complete
    useEffect(() => {
        if (isComplete) {
            stopPolling()
        }
    }, [isComplete, stopPolling])

    // Cleanup on unmount
    useEffect(() => {
        return () => stopPolling()
    }, [stopPolling])

    /**
     * Execute a bulk action
     */
    const execute = useCallback(async (payload, options = {}) => {
        setIsLoading(true)
        setError(null)

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

            setExecution(data.data)

            // Start polling if not a dry run
            if (!payload.options?.dry_run && options.poll !== false) {
                startPolling()
            }

            return data.data
        } catch (e) {
            setError(e.message)
            throw e
        } finally {
            setIsLoading(false)
        }
    }, [apiPrefix, getAuthHeaders, startPolling])

    /**
     * Cancel the current action
     */
    const cancel = useCallback(async () => {
        if (!execution?.uuid) return

        setIsLoading(true)

        try {
            const response = await fetch(`${apiPrefix}/${execution.uuid}/cancel`, {
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

            setExecution(data.data)
            stopPolling()

            return data.data
        } catch (e) {
            setError(e.message)
            throw e
        } finally {
            setIsLoading(false)
        }
    }, [execution?.uuid, apiPrefix, getAuthHeaders, stopPolling])

    /**
     * Undo the action
     */
    const undo = useCallback(async () => {
        if (!execution?.uuid || !execution?.can_undo) return

        setIsLoading(true)

        try {
            const response = await fetch(`${apiPrefix}/${execution.uuid}/undo`, {
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

            setExecution(data.data.execution)

            return data.data
        } catch (e) {
            setError(e.message)
            throw e
        } finally {
            setIsLoading(false)
        }
    }, [execution?.uuid, execution?.can_undo, apiPrefix, getAuthHeaders])

    /**
     * Preview action (dry run)
     */
    const preview = useCallback(async (payload) => {
        setIsLoading(true)
        setError(null)

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
            setError(e.message)
            throw e
        } finally {
            setIsLoading(false)
        }
    }, [apiPrefix, getAuthHeaders])

    /**
     * Reset state
     */
    const reset = useCallback(() => {
        stopPolling()
        setExecution(null)
        setError(null)
        setIsLoading(false)
    }, [stopPolling])

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

export default useBulkAction
