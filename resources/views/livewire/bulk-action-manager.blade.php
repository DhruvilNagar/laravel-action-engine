<div>
    {{-- Messages --}}
    @if($error)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-400 text-red-700 dark:text-red-400 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ $error }}</span>
            <button wire:click="$set('error', null)" class="absolute top-0 right-0 px-4 py-3">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    @endif

    @if($success)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-400 text-green-700 dark:text-green-400 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ $success }}</span>
            <button wire:click="$set('success', null)" class="absolute top-0 right-0 px-4 py-3">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- Action Buttons --}}
    <div class="flex items-center space-x-2 mb-4">
        @foreach($availableActions as $actionName => $actionConfig)
            <button 
                wire:click="executeAction('{{ $actionName }}')"
                @if(empty($selectedIds)) disabled @endif
                class="inline-flex items-center px-4 py-2 rounded-md transition
                    @if(empty($selectedIds))
                        bg-gray-300 text-gray-500 cursor-not-allowed
                    @else
                        @switch($actionConfig['color'] ?? 'primary')
                            @case('danger') bg-red-600 hover:bg-red-700 text-white @break
                            @case('warning') bg-yellow-600 hover:bg-yellow-700 text-white @break
                            @case('success') bg-green-600 hover:bg-green-700 text-white @break
                            @default bg-blue-600 hover:bg-blue-700 text-white
                        @endswitch
                    @endif
                "
            >
                @if(isset($actionConfig['icon']))
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @switch($actionConfig['icon'])
                            @case('trash')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                @break
                            @case('archive')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                @break
                            @case('refresh')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                @break
                        @endswitch
                    </svg>
                @endif
                {{ $actionConfig['label'] ?? ucfirst($actionName) }}
                @if(!empty($selectedIds))
                    <span class="ml-2 text-xs opacity-75">({{ count($selectedIds) }})</span>
                @endif
            </button>
        @endforeach
        
        @if($execution && $this->isInProgress)
            <button 
                wire:click="cancelExecution"
                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Cancel
            </button>
        @endif
    </div>

    {{-- Confirmation Modal --}}
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelAction"></div>
                
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                    Confirm Action
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $availableActions[$pendingAction]['confirmation'] ?? 'Are you sure you want to perform this action?' }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        <strong>{{ count($selectedIds) }}</strong> record(s) will be affected.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            wire:click="confirmAction" 
                            type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Confirm
                        </button>
                        <button 
                            wire:click="cancelAction" 
                            type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Progress Modal --}}
    @if($showProgressModal && $execution)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @if($this->isComplete) wire:click="closeModals" @endif></div>
                
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ ucfirst($execution->action_name) }} Progress
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($execution->status)
                                        @case('pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                        @case('processing') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                        @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                        @case('failed') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                        @case('cancelled') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                        @default bg-gray-100 text-gray-800
                                    @endswitch
                                ">
                                    {{ ucfirst($execution->status) }}
                                </span>
                                @if($this->isComplete)
                                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-500">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>

            {{-- Progress Bar --}}
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold inline-block text-blue-600 dark:text-blue-400">
                            {{ number_format($execution->progress_percentage, 1) }}%
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold inline-block text-gray-600 dark:text-gray-400">
                            {{ $execution->processed_records }} / {{ $execution->total_records }}
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-gray-200 dark:bg-gray-700">
                    <div 
                        style="width: {{ $execution->progress_percentage }}%"
                        class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-blue-500 to-indigo-600 transition-all duration-500"
                    ></div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $execution->processed_records }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Processed</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ $execution->processed_records - $execution->failed_records }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Successful</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-red-600">
                        {{ $execution->failed_records }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                </div>
            </div>

                        {{-- Undo Button --}}
                        @if($execution->status === 'completed' && $execution->can_undo)
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button 
                                    wire:click="undoAction"
                                    wire:loading.attr="disabled"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 transition disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="undoAction">Undo Action</span>
                                    <span wire:loading wire:target="undoAction">Undoing...</span>
                                    <span class="ml-2 text-xs opacity-75">
                                        (expires {{ $execution->undo_expires_at->diffForHumans() }})
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>
                    @if($this->isComplete)
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button 
                                wire:click="closeModals" 
                                type="button" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Close
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Auto-refresh Script --}}
    @if($autoRefresh && $showProgressModal && $execution && $this->isInProgress)
        <script>
            document.addEventListener('livewire:init', () => {
                let pollInterval;

                Livewire.on('start-progress-polling', () => {
                    if (pollInterval) clearInterval(pollInterval);
                    
                    pollInterval = setInterval(() => {
                        @this.call('refreshProgress');
                    }, 2000); // Poll every 2 seconds
                });

                Livewire.on('stop-progress-polling', () => {
                    if (pollInterval) {
                        clearInterval(pollInterval);
                        pollInterval = null;
                    }
                });

                // Start polling immediately
                Livewire.dispatch('start-progress-polling');
            });
        </script>
    @endif
</div>
