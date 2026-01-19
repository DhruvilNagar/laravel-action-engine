{{--
    Bulk Action Progress Bar Component
    
    Displays real-time progress of bulk action execution with status indicators,
    progress percentage, and optional undo functionality.
    
    @param BulkActionExecution $execution The execution instance to track
    
    Usage:
    @include('action-engine::blade.progress-bar', ['execution' => $execution])
--}}

@props(['execution'])

@if($execution)
<div class="bulk-action-progress" 
     data-uuid="{{ $execution->uuid }}" 
     role="progressbar" 
     aria-valuenow="{{ $execution->progress_percentage }}" 
     aria-valuemin="0" 
     aria-valuemax="100">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ ucfirst($execution->action_name) }}
        </span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ number_format($execution->progress_percentage, 1) }}%
        </span>
    </div>
    
    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
        <div 
            class="h-2.5 rounded-full transition-all duration-300
                @switch($execution->status)
                    @case('completed') bg-green-600 @break
                    @case('failed') bg-red-600 @break
                    @case('cancelled') bg-gray-400 @break
                    @default bg-blue-600
                @endswitch
            "
            style="width: {{ $execution->progress_percentage }}%"
        ></div>
    </div>
    
    <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
        <span>{{ $execution->processed_records }} / {{ $execution->total_records }} processed</span>
        @if($execution->failed_records > 0)
            <span class="text-red-600">{{ $execution->failed_records }} failed</span>
        @endif
    </div>

    @if($execution->status === 'completed' && $execution->can_undo)
        <div class="mt-3">
            <form method="POST" action="{{ route('action-engine.undo', $execution->uuid) }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-amber-600 hover:text-amber-800 underline">
                    Undo (expires {{ $execution->undo_expires_at->diffForHumans() }})
                </button>
            </form>
        </div>
    @endif
</div>

<script>
/**
 * Real-time progress polling for bulk action execution
 * Polls the API every 2 seconds and updates the progress bar dynamically
 */
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const progressBar = document.querySelector('[data-uuid="{{ $execution->uuid }}"]');
        
        if (!progressBar) {
            console.warn('Progress bar element not found');
            return;
        }
        
        @if($execution->isInProgress())
        let pollAttempts = 0;
        const maxPollAttempts = 1800; // 1 hour max (2s * 1800 = 3600s)
        
        const pollInterval = setInterval(async () => {
            if (++pollAttempts > maxPollAttempts) {
                clearInterval(pollInterval);
                console.warn('Max polling attempts reached');
                return;
            }
            
            try {
                const response = await fetch('/api/bulk-actions/{{ $execution->uuid }}/progress');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success && data.data) {
                    // Update progress bar width
                    const bar = progressBar.querySelector('.h-2\\.5');
                    if (bar) {
                        bar.style.width = data.data.progress_percentage + '%';
                        progressBar.setAttribute('aria-valuenow', data.data.progress_percentage);
                    }
                    
                    // Stop polling and reload on completion
                    if (['completed', 'failed', 'cancelled'].includes(data.data.status)) {
                        clearInterval(pollInterval);
                        setTimeout(() => location.reload(), 500);
                    }
                }
            } catch (error) {
                console.error('Progress poll failed:', error);
            }
        }, 2000);
        @endif
    });
})();
</script>
@endif
