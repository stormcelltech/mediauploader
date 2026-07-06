/**
 * @fileoverview FileUploader — A robust, dependency-free vanilla JavaScript file upload 
 * component utilizing an immutable, React-inspired state-to-view architecture.
 *
 * @module FileUploader
 * @version 1.0.0
 * * @description
 * High-performance UI component that manages single/multi-file uploads, preview states, 
 * and media library tab toggling via a declarative HTML dataset API. Built to replace 
 * heavy framework overhead while maintaining a predictable unidirectional data flow.
 *
 * ### DOM Configuration (Dataset API)
 * The component instantiates dynamically by parsing configuration attributes from its 
 * host element wrapper.
 *
 * @example
 * <div id="uploader"
 * class="uploader"
 * data-fileinputname="logo"
 * data-uploadtext="Select File"
 * data-uploadtype="single"
 * data-preview="true"
 * data-hideMediaTab="false"
 * data-hasfile="2">
 * </div>
 *
 * ---
 * * ### CRITICAL DATA INTEGRITY RULES (data-hasfile)
 * @important
 * The `data-hasfile` attribute handles hydration of existing server-side assets. 
 * To ensure persistence and proper form submission lifecycle, it **MUST STRICTLY** * adhere to the following data schemas:
 * * 1. **Single Mode:** Must be a positive integer representing the numeric database Media ID (e.g., `data-hasfile="2"`).
 * 2. **Multi Mode:** Must be a valid JSON-serialized array of numeric IDs (e.g., `data-hasfile="[2, 45, 91]"`).
 *
 * **CRITICAL ANTI-PATTERN:** Never pass a URL, filepath string, or filename to `data-hasfile`. 
 * Doing so breaks backend hydration routines, prevents the component from resolving existing assets, 
 * and inhibits the generation of critical hidden form inputs until a user manually re-selects a file.
 *
 * ---
 * * @property {string}  data-fileinputname - The `name` attribute applied to the generated hidden input element.
 * @property {string}  data-uploadtext    - The call-to-action text displayed on the main trigger button.
 * @property {string}  [data-uploadtype=single] - Selection mode strategy. Options: `'single'` | `'multi'`.
 * @property {boolean} [data-preview=true]     - Toggles rendering of rich media thumbnail previews.
 * @property {boolean} [data-hideMediaTab=false] - When true, forces the UI to hide global media/gallery sourcing options.
 * @property {string|number} [data-hasfile]    - Numeric reference(s) for pre-existing media hydration. See strict guidelines above.
 */

class FileUploader {
    static modalInitialized = false;
    static activeInstance = null;
    static instances = new Map();

    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Container with id "${containerId}" not found`);
            return;
        }

        // Configuration
        this.mediaEndpoint = '/media';
        this.uploadPath = '/media/upload';
        this.instanceId = containerId;
        this.previewContainerId = this.container.getAttribute('data-previewid') ?? `preview-${containerId}`;
        this.allowedFileTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
        this.maxFileSize = 5 * 1024 * 1024; // 5MB
        this.maxFiles = 10;

        // Data attributes
        this.hasFile = this.container.getAttribute('data-hasfile') ?? null;
        this.uploadType = this.container.getAttribute('data-uploadtype') ?? 'single';
        this.hideMediaTab = this.container.getAttribute('data-hidemediatab') === 'true';
        this.buttonType = this.container.getAttribute('data-buttontype') ?? 'input';
        this.buttonStyle = this.container.getAttribute('data-buttonstyle') ?? 'btn';
        this.showPreview = this.container.getAttribute('data-preview') !== 'false';
        this.customUpload = this.container.getAttribute('data-customupload') === 'true';
        this.updateEndpoint = this.container.getAttribute('data-updateendpoint') ?? null;
        this.showButton = this.container.getAttribute('data-showbutton') ?? '1';

        // ---  fileInputName collisions -------------------------------
        // If two uploader instances on the same page accidentally share the
        // same data-fileinputname (e.g. both set to "media"), the browser
        // will submit two fields with the same name and the backend will
        // silently only see one of them. We keep the developer-provided
        // name (so existing backends keep working) but warn loudly in the
        // console if we detect a collision with another live instance, so
        // the mistake is caught during development instead of failing
        // silently at submit time in production.
        this.fileInputName = this.container.getAttribute('data-fileinputname') ?? 'image';
        this.warnIfDuplicateFileInputName();

        // State
        this.page = 1;
        this.search = '';
        this.uploadProgress = {};
        this.selectedFiles = [];
        this.previewFiles = [];
        this.mediaLibrary = [];
        this.isLoadingMedia = false;

        // Register instance
        FileUploader.instances.set(containerId, this);

        // Initialize
        this.init();
    }

    /**
     * Warn (don't fail) if another live instance already uses the same
     * fileInputName — this is the #1 cause of "the value isn't submitted"
     * bug reports, since one of the two same-named hidden inputs silently
     * overwrites/competes with the other at submit time.
     */
    warnIfDuplicateFileInputName() {
        for (const [id, instance] of FileUploader.instances) {
            if (id !== this.instanceId && instance.fileInputName === this.fileInputName) {
                console.warn(
                    `[FileUploader] Container "#${this.instanceId}" and "#${id}" both use ` +
                    `data-fileinputname="${this.fileInputName}". Only one of these values will ` +
                    `reach the server on submit. Give each uploader a unique data-fileinputname ` +
                    `(e.g. "logo_media_id" and "favicon_media_id").`
                );
            }
        }
    }

    init() {
        this.createUploadButton();
        if (this.showPreview) {
            this.createPreviewContainer();
        }
        this.loadExistingFiles();

        if (!FileUploader.modalInitialized) {
            this.createModal();
            this.initModalEvents();
            FileUploader.modalInitialized = true;
        }
    }

    /**
     * Load existing files from server.
     *
     * --- FIX 2: silent failure on initial load -----------------------------
     * Previously, if data-hasfile held something the /media/{id}/get
     * endpoint couldn't resolve (a URL, an empty string, a filename, etc.),
     * fetchFile() failed quietly and NO hidden input was ever created.
     * That meant: load the settings page, change an unrelated field, hit
     * Save — and the existing favicon/logo media id silently disappears
     * from the request, because nothing ever populated previewFiles/the
     * hidden input in the first place.
     *
     * We now always seed a hidden input immediately from data-hasfile
     * (synchronously, no round-trip required), so the value is present on
     * submit even before/without a successful preview fetch. The fetch is
     * still used to render the actual thumbnail/filename — that part is
     * purely cosmetic and should never gate whether the ID gets submitted.
     */
    loadExistingFiles() {
        if (!this.hasFile || this.hasFile === '') return;

        let fileIds;
        try {
            fileIds = Array.isArray(this.hasFile) ? this.hasFile : JSON.parse(this.hasFile);
        } catch {
            fileIds = [this.hasFile];
        }

        if (!Array.isArray(fileIds)) {
            fileIds = [fileIds];
        }

        fileIds = fileIds.filter(id => id !== null && id !== undefined && id !== '');
        if (fileIds.length === 0) return;

        // Seed previewFiles + hidden inputs immediately so the value is
        // guaranteed to be in the form regardless of whether the thumbnail
        // fetch below succeeds.
        this.previewFiles = fileIds.map(id => ({ id }));
        this.updateHiddenInputs();

        // Now fetch full file details (thumb, filename) purely for display.
        fileIds.forEach(fileId => this.fetchFile(fileId));
    }

    /**
     * Fetch single file from server
     */
    fetchFile(fileId) {
        fetch(`${this.mediaEndpoint}/${fileId}/get`, {
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(res => {
                if (!res.ok) throw new Error(res.statusText || 'Failed to load file');
                return res.json();
            })
            .then(data => {
                if (data.data) {
                    // Merge in display info without re-deriving the id list
                    // (we already guaranteed the hidden input above).
                    this.renderPreviewOnly(data.data);
                } else {
                    console.warn(`[FileUploader] No file data returned for id "${fileId}". The hidden ` +
                        `input still carries this id, but no thumbnail/filename could be shown.`);
                }
            })
            .catch(err => {
                console.error('Error loading file:', err);
                // Intentionally do NOT clear previewFiles/hidden inputs here —
                // a failed thumbnail fetch must never remove a value that's
                // already correctly queued for submission.
                this.showAlert('error', 'Failed to load image preview');
            });
    }

    /**
     * Create upload button
     */
    createUploadButton() {
        if (this.showButton !== '1') return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = this.getButtonClasses();

        const chooseFile = document.createElement('span');
        chooseFile.className = this.buttonStyle ?? 'h-full py-3 px-4 bg-gray-100 text-nowrap dark:bg-gray-800';
        chooseFile.textContent = this.container.dataset.uploadtext ?? 'Select Files';

        btn.appendChild(chooseFile);

        if (this.buttonType === 'input') {
            const inputbox = document.createElement('span');
            inputbox.className = 'group grow flex overflow-hidden h-full py-3 px-4 items-center';

            const inputBoxInner = document.createElement('span');
            inputBoxInner.className = 'group-has-[div]:hidden text-gray-600 dark:text-gray-400';
            inputBoxInner.textContent = 'No file chosen';

            inputbox.appendChild(inputBoxInner);
            btn.appendChild(inputbox);
        }

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.openModal();
        });

        this.container.appendChild(btn);
    }

    /**
     * Get button CSS classes based on configuration
     */
    getButtonClasses() {
        const baseClasses = 'relative flex w-full border-none overflow-hidden  shadow-sm rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:focus:border-gray-600 transition-colors';

        if (this.buttonType === 'button') {
            return `${baseClasses} flex-col`;
        }

        return `${baseClasses} flex-row`;
    }

    /**
     * Create preview container
     */
    createPreviewContainer() {
        const preview = document.createElement('div');
        preview.id = this.previewContainerId;
        preview.className = 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-4';
        this.container.appendChild(preview);
    }

    /**
     * Create modal structure
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'fileUploaderModal';
        modal.className = 'hidden fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4 overflow-auto';

        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-900 w-full max-w-4xl rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="sticky top-0 flex justify-between items-center border-b border-gray-200 dark:border-gray-700 p-6 bg-white dark:bg-gray-900 z-[9998]">
                    <div class="flex gap-6">
                        <button type="button" id="tab-upload" class="pb-2 px-1 border-b-2 border-blue-600 font-medium transition-colors text-blue-600 dark:text-blue-400">Upload</button>
                        <button type="button" id="tab-media" class="pb-2 px-1 border-b-2 border-transparent font-medium transition-colors text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Media Library</button>
                    </div>
                    <button type="button" id="closeModalBtn" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="p-6">
                    <!-- Upload Tab -->
                    <div id="uploadTab" class="space-y-4">
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-all" id="dropZone">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Click or drag files here</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Supported formats: PNG, JPG, SVG, WebP (Max 5MB each)</p>
                        </div>
                        <input type="file" class="hidden" id="fileInput" multiple accept="image/png,image/jpeg,image/svg+xml,image/webp" />
                        <div id="modalPreview" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
                    </div>

                    <!-- Media Library Tab -->
                    <div id="mediaTab" class="space-y-4 hidden">
                        <div class="relative">
                            <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" id="mediaSearch" placeholder="Search media..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        </div>
                        <div id="mediaGallery" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[400px] overflow-y-auto"></div>
                        <button type="button" id="loadMoreMedia" class="w-full py-2 px-4 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors">Load More</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Hide media tab if requested
        if (this.hideMediaTab) {
            document.getElementById('tab-media').classList.add('hidden');
        }
    }

    /**
     * Open modal
     */
    openModal() {
        const modal = document.getElementById('fileUploaderModal');
        modal.classList.remove('hidden');
        FileUploader.activeInstance = this;
        this.page = 1;
        this.search = '';
        document.getElementById('mediaSearch').value = '';

        // Always land on Upload tab — media loads fresh only if user clicks the tab
        const uploadTab = document.getElementById('uploadTab');
        const mediaTab = document.getElementById('mediaTab');
        const tabUpload = document.getElementById('tab-upload');
        const tabMedia = document.getElementById('tab-media');
        uploadTab.classList.remove('hidden');
        mediaTab.classList.add('hidden');
        this.updateTabStyles(tabUpload, tabMedia);
        document.getElementById('modalPreview').innerHTML = '';

        // Respect data-hidemediatab on a per-instance basis even though the
        // modal markup is shared across all instances.
        if (this.hideMediaTab) {
            tabMedia.classList.add('hidden');
        } else {
            tabMedia.classList.remove('hidden');
        }
    }

    /**
     * Close modal
     */
    static closeModal() {
        document.getElementById('fileUploaderModal').classList.add('hidden');
    }

    /**
     * Initialize modal event listeners
     */
    initModalEvents() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const tabUpload = document.getElementById('tab-upload');
        const tabMedia = document.getElementById('tab-media');
        const uploadTab = document.getElementById('uploadTab');
        const mediaTab = document.getElementById('mediaTab');
        const mediaSearch = document.getElementById('mediaSearch');
        const loadMoreBtn = document.getElementById('loadMoreMedia');
        const closeModalBtn = document.getElementById('closeModalBtn');

        // Close modal
        closeModalBtn.addEventListener('click', () => FileUploader.closeModal());

        // File input
        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => {
            if (FileUploader.activeInstance) {
                FileUploader.activeInstance.handleFiles(e.target.files);
            }
        });

        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
            if (FileUploader.activeInstance) {
                FileUploader.activeInstance.handleFiles(e.dataTransfer.files);
            }
        });

        // Tab switching
        tabUpload.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadTab.classList.remove('hidden');
            mediaTab.classList.add('hidden');
            this.updateTabStyles(tabUpload, tabMedia);
        });

        tabMedia.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadTab.classList.add('hidden');
            mediaTab.classList.remove('hidden');
            this.updateTabStyles(tabMedia, tabUpload);
            if (FileUploader.activeInstance) {
                // Always reset page/search to match the newly active instance
                // and clear stale gallery items from a previous instance
                FileUploader.activeInstance.page = 1;
                document.getElementById('mediaGallery').innerHTML = '';
                FileUploader.activeInstance.loadMedia();
            }
        });

        // Media search
        mediaSearch.addEventListener('input', (e) => {
            if (FileUploader.activeInstance) {
                FileUploader.activeInstance.page = 1;
                FileUploader.activeInstance.search = e.target.value;
                FileUploader.activeInstance.loadMedia();
            }
        });

        // Load more
        loadMoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (FileUploader.activeInstance) {
                FileUploader.activeInstance.page++;
                FileUploader.activeInstance.loadMedia(true);
            }
        });
    }

    /**
     * Update tab styling
     */
    updateTabStyles(activeTab, inactiveTab) {
        activeTab.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
        activeTab.classList.remove('border-transparent', 'text-gray-500');

        inactiveTab.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
        inactiveTab.classList.add('border-transparent', 'text-gray-500');
    }

    /**
     * Get CSRF token
     */
    getCsrfToken() {
        let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!csrfToken) {
            csrfToken = document.cookie
                .split('; ')
                .find(row => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
        }

        return csrfToken;
    }

    /**
     * Handle file selection
     */
    handleFiles(files) {
        const fileArray = Array.from(files);
        const newFiles = [];

        for (const file of fileArray) {
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showAlert('error', validation.error);
                continue;
            }
            newFiles.push(file);
        }

        if (newFiles.length > 0) {
            this.selectedFiles.push(...newFiles);
            newFiles.forEach(file => this.uploadFile(file));
        }
    }

    /**
     * Validate file
     */
    validateFile(file) {
        if (!this.allowedFileTypes.includes(file.type)) {
            return {
                valid: false,
                error: `Invalid file type: ${file.name}`
            };
        }

        if (file.size > this.maxFileSize) {
            return {
                valid: false,
                error: `File too large: ${file.name} (Max 5MB)`
            };
        }

        if (this.uploadType === 'multiple' && this.previewFiles.length >= this.maxFiles) {
            return {
                valid: false,
                error: `Maximum ${this.maxFiles} files allowed`
            };
        }

        return { valid: true };
    }

    /**
     * Upload file
     */
    uploadFile(file) {
        const fileId = Math.random().toString(36).substr(2, 9);
        this.uploadProgress[fileId] = 0;

        const formData = new FormData();
        formData.append('file', file);

        const csrfToken = this.getCsrfToken();

        if (!csrfToken) {
            console.error('CSRF token not found');
            this.showAlert('error', 'Security token missing. Please refresh the page.');
            return;
        }

        const xhr = new XMLHttpRequest();

        xhr.open('POST', this.uploadPath);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.setRequestHeader('Accept', 'application/json');

        // Progress tracking
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                this.uploadProgress[fileId] = percent;
                this.updateProgressUI();
            }
        };

        // On load
        xhr.onload = () => {
            this.handleUploadResponse(xhr, fileId, file);
        };

        // On error
        xhr.onerror = () => {
            console.error('Upload XHR error');
            this.showAlert('error', 'Network error occurred');
            delete this.uploadProgress[fileId];
            this.updateProgressUI();
        };

        xhr.send(formData);
    }

    /**
     * Handle upload response
     */
    handleUploadResponse(xhr, fileId, file) {
        try {
            const response = JSON.parse(xhr.responseText);

            if (xhr.status === 200) {
                if (response.data) {
                    this.handleFileUploadSuccess(response.data);
                    delete this.uploadProgress[fileId];
                    this.selectedFiles = this.selectedFiles.filter(f => f !== file);
                    this.updateProgressUI();
                } else {
                    throw new Error(response.message || 'Upload failed');
                }
            } else if (xhr.status === 419) {
                this.showAlert('error', 'Session expired. Please refresh the page and try again.');
                delete this.uploadProgress[fileId];
            } else if (xhr.status === 422) {
                const errors = response.errors || response.message || 'Validation failed';
                this.showAlert('error', typeof errors === 'string' ? errors : 'File validation failed');
                delete this.uploadProgress[fileId];
            } else {
                throw new Error(`Upload failed (${xhr.status}): ${xhr.statusText}`);
            }

            this.updateProgressUI();
        } catch (err) {
            console.error('Failed to process response:', err);
            this.showAlert('error', err.message);
            delete this.uploadProgress[fileId];
            this.updateProgressUI();
        }
    }

    /**
     * Update progress UI
     */
    updateProgressUI() {
        const modalPreview = document.getElementById('modalPreview');
        const hasProgress = Object.keys(this.uploadProgress).length > 0;

        if (!hasProgress) {
            modalPreview.innerHTML = '';
            return;
        }

        // Rebuild progress items
        modalPreview.innerHTML = '';
        this.selectedFiles.forEach((file, idx) => {
            const progress = Object.values(this.uploadProgress)[idx] || 0;
            const div = document.createElement('div');
            div.className = 'rounded-lg bg-gray-100 dark:bg-gray-800 p-3';
            div.innerHTML = `
                <div class="aspect-square rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center mb-2">
                    ${progress !== 100 ?
                    '<svg class="w-6 h-6 animate-spin text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>' :
                    '<svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                    }
                </div>
                <p class="text-xs font-medium truncate text-gray-700 dark:text-gray-300">${file.name}</p>
                <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-1.5 mt-1">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all" style="width: ${progress}%"></div>
                </div>
            `;
            modalPreview.appendChild(div);
        });
    }

    /**
     * Handle successful file upload
     */
    handleFileUploadSuccess(uploadedFile) {
        this.loadPreview(uploadedFile);
        this.showAlert('success', 'File uploaded successfully');

        if (this.customUpload && this.updateEndpoint) {
            this.updateUpload(uploadedFile.id);
        }
    }

    /**
     * Load file preview AND register it as the value that will be submitted.
     * Use this when the user actively selects/uploads a file.
     */
    loadPreview(file) {
        if (this.uploadType === 'single') {
            this.previewFiles = [file];
        } else {
            // Guard against re-adding the same file twice (e.g. selecting the
            // same media-library item twice in one session).
            if (!this.previewFiles.some(f => f.id === file.id)) {
                this.previewFiles.push(file);
            }
        }

        this.renderPreviewOnly(file);
        this.updateHiddenInputs();

        // Fire event when image selected
        this.container.dispatchEvent(
            new CustomEvent('file:selected', {
                detail: file
            })
        );
        FileUploader.closeModal();
    }

    /**
     * Render a file's thumbnail/filename into the preview area WITHOUT
     * touching previewFiles or the hidden inputs. Used by fetchFile() to
     * decorate an already-queued id with its thumbnail once it loads,
     * and by loadPreview() to do the actual DOM rendering for a freshly
     * selected file.
     */
    renderPreviewOnly(file) {
        const container = document.getElementById(this.previewContainerId);
        if (!container) return;

        // Keep the in-memory record for this id up to date with full file
        // details (thumb/filename) now that we have them, without changing
        // which ids are queued for submission.
        const idx = this.previewFiles.findIndex(f => f.id === file.id);
        if (idx !== -1) {
            this.previewFiles[idx] = { ...this.previewFiles[idx], ...file };
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'relative group';
        wrapper.dataset.fileId = file.id;

        const imageContainer = document.createElement('div');
        imageContainer.className = 'relative overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 aspect-square';

        const img = document.createElement('img');
        img.src = file.thumb || file.url;
        img.alt = file.filename ?? '';
        img.className = 'w-full h-full object-cover';
        img.loading = 'lazy';

        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center';

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'p-2 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors';
        deleteBtn.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';

        deleteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            wrapper.remove();
            this.previewFiles = this.previewFiles.filter(f => f.id !== file.id);
            this.updateHiddenInputs();
        });

        overlay.appendChild(deleteBtn);
        imageContainer.appendChild(img);
        imageContainer.appendChild(overlay);

        const filename = document.createElement('p');
        filename.className = 'mt-1 text-xs font-medium text-gray-700 dark:text-gray-300 truncate';
        filename.textContent = file.filename ?? '';

        wrapper.appendChild(imageContainer);
        wrapper.appendChild(filename);

        if (this.uploadType === 'single') {
            container.innerHTML = '';
            container.appendChild(wrapper);
        } else {
            // Replace an existing card for this id if present, otherwise append.
            const existing = container.querySelector(`[data-file-id="${file.id}"]`);
            if (existing) {
                existing.replaceWith(wrapper);
            } else {
                container.appendChild(wrapper);
            }
        }
    }

    /**
     * Update hidden input fields.
     *
     * Single source of truth: always rebuilt from this.previewFiles, so any
     * code path that mutates previewFiles and calls this afterwards is
     * guaranteed to produce hidden inputs that match exactly what's about to
     * be submitted.
     */
    updateHiddenInputs() {
        // Remove existing hidden inputs created by this instance only.
        const existingInputs = this.container.querySelectorAll(`input[type="hidden"][data-uploader-id="${this.instanceId}"]`);
        existingInputs.forEach(input => input.remove());

        // Add new hidden inputs
        this.previewFiles.forEach((file) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = this.uploadType === 'single' ? this.fileInputName : `${this.fileInputName}[]`;
            input.value = file.id;
            input.dataset.uploaderId = this.instanceId;
            this.container.appendChild(input);
        });
    }

    /**
     * Load media library
     */
    loadMedia(append = false) {
        if (this.isLoadingMedia) return;

        this.isLoadingMedia = true;
        const gallery = document.getElementById('mediaGallery');
        const loadMoreBtn = document.getElementById('loadMoreMedia');

        if (!append) {
            gallery.innerHTML = '<div class="col-span-full flex justify-center py-12"><svg class="w-8 h-8 animate-spin text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div>';
        }

        const query = `${this.mediaEndpoint}/list?page=${this.page}&search=${encodeURIComponent(this.search)}`;

        fetch(query)
            .then(res => {
                if (!res.ok) throw new Error('Failed to load media');
                return res.json();
            })
            .then(response => {
                this.isLoadingMedia = false;

                if (!append) {
                    gallery.innerHTML = '';
                }

                const files = response.data?.data || [];

                if (files.length === 0 && !append) {
                    gallery.innerHTML = `
                        <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-500">
                            <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p>No media files found</p>
                        </div>
                    `;
                    loadMoreBtn.style.display = 'none';
                    return;
                }

                files.forEach(file => {
                    const div = document.createElement('div');
                    div.className = 'group relative rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 cursor-pointer hover:ring-2 hover:ring-blue-500 transition-all';

                    const img = document.createElement('img');
                    img.src = file.thumb;
                    img.alt = file.filename;
                    img.className = 'w-full h-48 object-cover group-hover:opacity-75 transition-opacity cursor-pointer';

                    const overlay = document.createElement('div');
                    overlay.className = 'absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100';

                    const selectBtn = document.createElement('button');
                    selectBtn.type = 'button';
                    selectBtn.className = 'p-2 bg-blue-500 text-white rounded-full hover:bg-blue-600';
                    selectBtn.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';

                    // IMPORTANT: capture the instance that opened the modal at
                    // bind time, not whatever FileUploader.activeInstance
                    // happens to be when the button is eventually clicked.
                    // The gallery markup is rebuilt per-open, so this is safe
                    // and removes any risk of a stale/switched active instance.
                    const owningInstance = this;
                    selectBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        owningInstance.loadPreview(file);
                        if (owningInstance.customUpload && owningInstance.updateEndpoint) {
                            owningInstance.updateUpload(file.id);
                        }
                    });

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'p-2 bg-red-500 text-white rounded-full hover:bg-red-600';
                    deleteBtn.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';

                    deleteBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.deleteMediaFile(file.id, div);
                    });

                    overlay.appendChild(selectBtn);
                    overlay.appendChild(deleteBtn);

                    const filename = document.createElement('p');
                    filename.className = 'absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/80 to-transparent text-white text-xs font-medium truncate';
                    filename.textContent = file.filename;

                    div.appendChild(img);
                    div.appendChild(overlay);
                    div.appendChild(filename);

                    gallery.appendChild(div);
                });

                // Show/hide load more button
                if (files.length > 0) {
                    loadMoreBtn.style.display = 'block';
                } else if (!append) {
                    loadMoreBtn.style.display = 'none';
                }
            })
            .catch(err => {
                console.error('Error loading media:', err);
                this.isLoadingMedia = false;
                this.showAlert('error', 'Failed to load media library');
            });
    }

    /**
     * Delete media file
     */
    deleteMediaFile(fileId, element) {
        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }

        const csrfToken = this.getCsrfToken();

        if (!csrfToken) {
            this.showAlert('error', 'Security token missing. Please refresh the page.');
            return;
        }

        fetch(`${this.mediaEndpoint}/${fileId}/delete`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        })
            .then(res => {
                if (res.status === 419) {
                    this.showAlert('error', 'Session expired. Please refresh the page.');
                    return;
                }
                if (!res.ok) throw new Error('Failed to delete');
                return res.json();
            })
            .then(data => {
                element.remove();
                this.showAlert('success', data.message || 'File deleted successfully');
            })
            .catch(err => {
                console.error('Delete error:', err);
                this.showAlert('error', 'Failed to delete file');
            });
    }

    /**
     * Update upload (custom endpoint, e.g. for instant-save UIs)
     */
    updateUpload(imageId) {
        if (!this.updateEndpoint) return;

        const csrfToken = this.getCsrfToken();

        if (!csrfToken) {
            this.showAlert('error', 'Security token missing. Please refresh the page.');
            return;
        }

        fetch(this.updateEndpoint, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ imageId }),
        })
            .then(res => {
                if (res.status === 419) {
                    this.showAlert('error', 'Session expired. Please refresh the page.');
                    return;
                }
                return res.json();
            })
            .then(data => {
                if (data.status === 200) {
                    this.showAlert('success', data.message || 'Updated successfully');
                } else {
                    this.showAlert('error', data.message || 'Update failed');
                }
            })
            .catch(err => {
                console.error('Update error:', err);
                this.showAlert('error', 'Network error');
            });
    }

    /**
     * Show alert notification
     */
    showAlert(type, message) {
        const alertId = `alert-${Date.now()}`;
        const alert = document.createElement('div');
        alert.id = alertId;
        alert.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 animate-in fade-in slide-in-from-top-2 flex items-center gap-2 ${
            type === 'success'
                ? 'bg-green-500 text-white'
                : 'bg-red-500 text-white'
        }`;

        const icon = type === 'success'
            ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
            : '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';

        alert.innerHTML = `${icon}<span>${message}</span>`;
        document.body.appendChild(alert);

        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    
}

/**
 * Auto-initialize all uploader instances on page load
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.uploader').forEach(el => {
        if (el.id) {
            new FileUploader(el.id);
        }
    });
});
window.FileUploader = FileUploader;

export default FileUploader;