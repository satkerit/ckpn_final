@props([
    'id' => 'upload-progress-modal',
    'show' => false
])

<div 
    x-data="uploadProgressModal({{ json_encode(['show' => $show]) }})"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title" 
    role="dialog" 
    aria-modal="true"
>
    <!-- Backdrop -->
    <div 
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
    ></div>

    <!-- Modal -->
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
        >
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 mb-4">
                    <svg class="h-8 w-8 text-blue-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2" x-text="title"></h3>
                <p class="text-sm text-gray-500 mb-4" x-text="subtitle"></p>
            </div>

            <!-- Progress Section -->
            <div class="space-y-4">
                <!-- Progress Bar -->
                <div class="relative">
                    <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                        <span>Progress</span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div 
                            class="h-full bg-gradient-to-r from-blue-500 to-blue-600 rounded-full transition-all duration-500 ease-out relative overflow-hidden"
                            :style="'width: ' + progress + '%'"
                        >
                            <!-- Animated shine effect -->
                            <div class="absolute top-0 left-0 h-full w-full bg-gradient-to-r from-transparent via-white to-transparent opacity-30 animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <!-- Status Text -->
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-sm font-medium text-gray-700 mb-1" x-text="statusTitle"></p>
                    <p class="text-xs text-gray-500" x-text="statusText"></p>
                </div>

                <!-- File Information -->
                <div x-show="fileInfo" class="border-l-4 border-blue-500 bg-blue-50 p-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-4 w-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-2">
                            <p class="text-xs font-medium text-blue-800" x-text="fileInfo"></p>
                        </div>
                    </div>
                </div>

                <!-- Processing Animation -->
                <div x-show="isProcessing" class="flex justify-center">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                    </div>
                </div>

                <!-- Cancel Button (optional) -->
                <div x-show="showCancel" class="flex justify-center pt-2">
                    <button 
                        @click="cancel()"
                        type="button" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Batalkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function uploadProgressModal(config = {}) {
    return {
        show: config.show || false,
        title: 'Mengupload Data...',
        subtitle: 'Mohon tunggu, proses upload sedang berlangsung',
        progress: 0,
        statusTitle: 'Memulai upload...',
        statusText: 'Menyiapkan file untuk diproses',
        fileInfo: '',
        isProcessing: true,
        showCancel: false,
        
        init() {
            // Listen for global events
            this.$watch('show', (value) => {
                if (value) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            });

            // Listen for progress updates
            window.addEventListener('upload-progress', (event) => {
                this.updateProgress(event.detail);
            });

            // Listen for upload complete
            window.addEventListener('upload-complete', (event) => {
                this.completeUpload(event.detail);
            });

            // Listen for upload error
            window.addEventListener('upload-error', (event) => {
                this.handleError(event.detail);
            });
        },

        open(options = {}) {
            this.title = options.title || 'Mengupload Data...';
            this.subtitle = options.subtitle || 'Mohon tunggu, proses upload sedang berlangsung';
            this.progress = 0;
            this.statusTitle = 'Memulai upload...';
            this.statusText = 'Menyiapkan file untuk diproses';
            this.fileInfo = options.fileInfo || '';
            this.isProcessing = true;
            this.showCancel = options.showCancel || false;
            this.show = true;
        },

        updateProgress(data) {
            this.progress = Math.round(data.percentage || 0);
            this.statusTitle = data.statusTitle || this.statusTitle;
            this.statusText = data.statusText || this.statusText;
            this.fileInfo = data.fileInfo || this.fileInfo;
            
            if (data.percentage >= 100) {
                this.statusTitle = 'Upload Selesai';
                this.statusText = 'Memproses data...';
                this.isProcessing = false;
            }
        },

        completeUpload(data) {
            this.progress = 100;
            this.statusTitle = 'Berhasil!';
            this.statusText = data.message || 'Upload berhasil diselesaikan';
            this.isProcessing = false;
            
            setTimeout(() => {
                this.close();
                if (data.callback && typeof data.callback === 'function') {
                    data.callback();
                }
            }, 1500);
        },

        handleError(data) {
            this.statusTitle = 'Upload Gagal';
            this.statusText = data.message || 'Terjadi kesalahan saat upload';
            this.isProcessing = false;
            this.showCancel = true;
            
            // Show error notification
            window.showError(data.message || 'Upload gagal', 'Error!');
        },

        cancel() {
            // Emit cancel event
            window.dispatchEvent(new CustomEvent('upload-cancel'));
            this.close();
        },

        close() {
            this.show = false;
        }
    }
}
</script>