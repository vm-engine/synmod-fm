/**
 * synmod-fm — shared Alpine component for the file manager and file picker.
 *
 * Loaded via Livewire @assets from route('fm.assets'). Registered on
 * alpine:init, or immediately when Alpine is already running (SPA navigation /
 * lazily rendered picker). Alpine runs in CSP mode, so Blade attributes only
 * call the methods defined here.
 *
 * config (json_encoded into x-data by the component):
 *   mode: 'manager' | 'picker', canUpload, maxSizeKb, allowedExtensions[],
 *   accept, labels{}, confirm{} (manager), modalName + pickerKey (picker).
 */
(() => {
    const register = () => {
        window.Alpine.data('fmBrowser', (config) => ({
            config,
            sidebarOpen: false,
            dropdown: '',
            dragging: false,
            dragCounter: 0,
            uploading: false,
            uploadProgress: 0,
            menu: { open: false, x: 0, y: 0, kind: '', key: '', name: '', url: '', preview: '' },

            init() {
                if (this.config.mode === 'picker') {
                    this.bindPickerEvents();
                }
            },

            /* ---------------------------------------------- navigation */

            toggleSidebar() {
                this.sidebarOpen = !this.sidebarOpen;
            },
            closeSidebar() {
                this.sidebarOpen = false;
            },
            go(event) {
                this.sidebarOpen = false;
                this.$wire.navigateTo(event.currentTarget.dataset.path);
            },
            toggleNodeFrom(event) {
                this.$wire.toggleNode(event.currentTarget.dataset.path);
            },
            switchFolderFrom(event) {
                this.dropdown = '';
                this.$wire.switchFolder(event.currentTarget.dataset.path);
            },

            /* ------------------------------------------------ dropdowns */

            toggleDropdown(event) {
                const wrapper = event.currentTarget.closest('[data-dropdown]');
                const name = wrapper.dataset.dropdown;
                this.dropdown = this.dropdown === name ? '' : name;
                // Popovers with a field (picker "New folder") focus it on open.
                this.$nextTick(() => {
                    const input = wrapper.querySelector('input');
                    if (input && this.dropdown === name) input.focus();
                });
            },
            isDropdown(name) {
                return this.dropdown === name;
            },
            closeDropdown() {
                this.dropdown = '';
            },
            closeDropdownFor(el) {
                if (this.dropdown === el.dataset.dropdown) {
                    this.dropdown = '';
                }
            },

            /* ---------------------------------------------------- items */

            itemData(event) {
                const el = event.currentTarget.closest('[data-fm-item]');
                return el ? el.dataset : null;
            },
            onItemClick(event) {
                const item = this.itemData(event);
                if (!item) return;

                if (this.config.mode === 'picker') {
                    if (item.kind === 'dir') {
                        this.$wire.navigateTo(item.key);
                    } else {
                        this.$wire.pick(Number(item.key));
                    }
                    return;
                }

                if (event.ctrlKey || event.metaKey) {
                    this.$wire.toggleSelect(item.kind, item.key);
                } else {
                    this.$wire.selectOnly(item.kind, item.key);
                }
            },
            onItemToggle(event) {
                const item = this.itemData(event);
                if (item) this.$wire.toggleSelect(item.kind, item.key);
            },
            onItemOpen(event) {
                const item = this.itemData(event);
                if (!item) return;

                if (item.kind === 'dir') {
                    this.$wire.navigateTo(item.key);
                    return;
                }
                if (this.config.mode === 'picker') {
                    this.$wire.selectFile(Number(item.key));
                    return;
                }
                this.previewItem(item);
            },
            previewItem(item) {
                if (item.preview === 'image') {
                    this.$dispatch('open-lightbox-fm-preview', { url: item.url });
                } else {
                    this.$wire.showDetails(Number(item.key));
                }
            },
            onBlankClick(event) {
                if (this.config.mode !== 'manager' || event.target.closest('[data-fm-item]')) return;
                this.$wire.clearSelection();
            },

            /* ------------------------------------------------- context menu */

            openItemMenu(event) {
                const el = event.currentTarget.closest('[data-fm-item]');
                if (!el) return;
                this.ensureSelected(el);
                this.showMenu(event.clientX, event.clientY, el.dataset);
            },
            openItemMenuFromButton(event) {
                const el = event.currentTarget.closest('[data-fm-item]');
                if (!el) return;
                const rect = event.currentTarget.getBoundingClientRect();
                this.ensureSelected(el);
                this.showMenu(rect.left, rect.bottom + 4, el.dataset);
            },
            openBlankMenu(event) {
                if (this.config.mode !== 'manager' || this.$wire.showTrash || event.target.closest('[data-fm-item]')) return;
                this.showMenu(event.clientX, event.clientY, { kind: 'blank', key: '', name: '', url: '', preview: '' });
            },
            ensureSelected(el) {
                if (!el.classList.contains('is-selected')) {
                    this.$wire.selectOnly(el.dataset.kind, el.dataset.key);
                }
            },
            showMenu(x, y, item) {
                this.menu = {
                    open: true,
                    x,
                    y,
                    kind: item.kind,
                    key: item.key,
                    name: item.name || '',
                    url: item.url || '',
                    preview: item.preview || '',
                };
                this.$nextTick(() => {
                    const el = this.$refs.menu;
                    if (!el) return;
                    this.menu.x = Math.max(8, Math.min(this.menu.x, window.innerWidth - el.offsetWidth - 8));
                    this.menu.y = Math.max(8, Math.min(this.menu.y, window.innerHeight - el.offsetHeight - 8));
                    const first = this.visibleMenuItems()[0];
                    if (first) first.focus();
                });
            },
            closeMenu() {
                this.menu.open = false;
            },
            visibleMenuItems() {
                if (!this.$refs.menu) return [];
                return Array.from(this.$refs.menu.querySelectorAll('.fm-menu-item'))
                    .filter((el) => el.offsetParent !== null && !el.disabled);
            },
            menuFocus(step) {
                const items = this.visibleMenuItems();
                if (!items.length) return;
                const index = items.indexOf(document.activeElement);
                items[(index + step + items.length) % items.length].focus();
            },
            get menuStyle() {
                return 'left:' + this.menu.x + 'px;top:' + this.menu.y + 'px';
            },
            get isFileMenu() {
                return this.menu.kind === 'file';
            },
            get isDirMenu() {
                return this.menu.kind === 'dir';
            },
            get isBlankMenu() {
                return this.menu.kind === 'blank';
            },
            get canPreview() {
                return this.menu.preview !== '';
            },
            get hasClipboard() {
                const clipboard = this.$wire.clipboard;
                return Boolean(clipboard && clipboard.files && clipboard.files.length);
            },

            menuPreview() {
                this.closeMenu();
                this.previewItem(this.menu);
            },
            menuDetails() {
                this.closeMenu();
                this.$wire.showDetails(Number(this.menu.key));
            },
            menuDownload() {
                this.closeMenu();
                const link = document.createElement('a');
                link.href = this.menu.url;
                link.download = this.menu.name;
                document.body.appendChild(link);
                link.click();
                link.remove();
            },
            menuRename() {
                this.closeMenu();
                this.$wire.startRename(Number(this.menu.key));
            },
            menuOpen() {
                this.closeMenu();
                this.$wire.navigateTo(this.menu.key);
            },
            menuCopy() {
                this.closeMenu();
                this.$wire.clipboardCopy();
            },
            menuCut() {
                this.closeMenu();
                this.$wire.clipboardCut();
            },
            menuPaste() {
                this.closeMenu();
                this.$wire.paste(this.menu.kind === 'dir' ? this.menu.key : null);
            },
            menuNewFolder() {
                this.closeMenu();
                this.openNewFolder(this.menu.kind === 'dir' ? this.menu.key : null);
            },
            menuUpload() {
                this.closeMenu();
                if (this.$refs.uploadInput) this.$refs.uploadInput.click();
            },
            menuTrash() {
                this.closeMenu();
                this.confirm('trash', [Number(this.menu.key)], this.menu.name);
            },
            menuRestore() {
                this.closeMenu();
                this.confirm('restore', [Number(this.menu.key)], this.menu.name);
            },
            menuPurge() {
                this.closeMenu();
                this.confirm('purge', [Number(this.menu.key)], this.menu.name);
            },

            newFolderHere() {
                this.openNewFolder(null);
            },
            openNewFolder(parent) {
                this.$wire.set('newFolderParent', parent, false);
                this.$dispatch('open-modal-fm-new-folder');
            },

            /* ------------------------------------------------- confirmations */

            confirmTrashSelected() {
                this.confirm('trashSelected', [], null);
            },
            confirmPurgeSelected() {
                this.confirm('purgeSelected', [], null);
            },
            confirmTrashFrom(event) {
                const data = event.currentTarget.dataset;
                this.confirm('trash', [Number(data.id)], data.name);
            },
            confirm(method, params, name) {
                const text = this.config.confirm[method];
                const message = name !== null
                    ? text.message.replace(':name', name)
                    : text.message.replace(':count', String(this.$wire.selected.length));
                this.$dispatch('confirm-dialog', {
                    title: text.title,
                    message,
                    confirmText: text.confirm,
                    cancelText: this.config.labels.cancel,
                    confirmColor: text.color,
                    icon: text.icon,
                    wireMethod: method,
                    wireParams: params,
                    wireComponent: this.$wire.$id,
                });
            },

            copyUrl(event) {
                const url = event.currentTarget.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    this.$dispatch('notify', {
                        variant: 'success',
                        title: this.config.labels.success,
                        message: this.config.labels.urlCopied,
                    });
                });
            },

            /* ------------------------------------------------------ keyboard */

            onKeydown(event) {
                if (this.config.mode !== 'manager') return;
                const target = event.target;
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable) return;

                const key = event.key;
                const mod = event.ctrlKey || event.metaKey;

                if (key === 'Escape') {
                    this.onEscape();
                    return;
                }
                if (key.startsWith('Arrow') && !this.menu.open) {
                    this.moveFocus(event);
                    return;
                }
                if (mod && ['c', 'x', 'v', 'a'].includes(key.toLowerCase())) {
                    event.preventDefault();
                    const action = { c: 'clipboardCopy', x: 'clipboardCut', a: 'selectAll' }[key.toLowerCase()];
                    if (action) {
                        this.$wire[action]();
                    } else {
                        this.$wire.paste(null);
                    }
                    return;
                }
                if (key === 'Delete') {
                    if (this.$wire.selected.length === 0) return;
                    event.preventDefault();
                    if (this.$wire.showTrash) {
                        this.confirmPurgeSelected();
                    } else {
                        this.confirmTrashSelected();
                    }
                    return;
                }
                const single = this.singleSelectedFile();
                if (key === 'F2' && single) {
                    event.preventDefault();
                    this.$wire.startRename(Number(single.key));
                    return;
                }
                if (key === ' ' && single && single.preview) {
                    event.preventDefault();
                    this.previewItem(single);
                }
            },
            singleSelectedFile() {
                const selected = this.$wire.selected;
                if (selected.length !== 1 || this.$wire.selectedDirs.length !== 0) return null;
                const el = this.$root.querySelector('[data-fm-item][data-kind="file"][data-key="' + selected[0] + '"]');
                return el ? el.dataset : null;
            },
            onEscape() {
                if (this.menu.open) {
                    this.closeMenu();
                } else if (this.dropdown !== '') {
                    this.dropdown = '';
                } else if (this.sidebarOpen) {
                    this.sidebarOpen = false;
                } else if (this.$wire.detailsId !== null) {
                    this.$wire.closeDetails();
                } else {
                    this.$wire.clearSelection();
                }
            },
            moveFocus(event) {
                const items = Array.from(this.$root.querySelectorAll('[data-fm-item]'));
                const index = items.indexOf(document.activeElement);
                if (index === -1) return;
                event.preventDefault();
                const container = items[index].parentElement;
                const columns = this.$wire.viewMode === 'list'
                    ? 1
                    : getComputedStyle(container).gridTemplateColumns.split(' ').length;
                const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -columns, ArrowDown: columns }[event.key] || 0;
                const next = items[index + delta];
                if (next) next.focus();
            },

            /* -------------------------------------------------------- upload */

            onDragEnter() {
                if (!this.config.canUpload || this.$wire.showTrash) return;
                this.dragCounter++;
                this.dragging = true;
            },
            onDragLeave() {
                if (!this.dragging) return;
                this.dragCounter = Math.max(0, this.dragCounter - 1);
                if (this.dragCounter === 0) this.dragging = false;
            },
            onDrop(event) {
                const wasDragging = this.dragging;
                this.dragCounter = 0;
                this.dragging = false;
                if (!wasDragging) return;
                this.handleFiles(Array.from(event.dataTransfer.files));
            },
            onFileInput(event) {
                this.handleFiles(Array.from(event.target.files));
                event.target.value = '';
            },
            handleFiles(files) {
                if (!files.length) return;
                const error = this.validateFiles(files);
                if (error) {
                    this.notifyError(error);
                    return;
                }
                this.uploading = true;
                this.uploadProgress = 0;
                this.$wire.uploadMultiple(
                    'uploadFiles',
                    files,
                    () => {
                        this.uploading = false;
                    },
                    () => {
                        this.uploading = false;
                        this.notifyError(this.config.labels.uploadFailed);
                    },
                    (progressEvent) => {
                        this.uploadProgress = progressEvent.detail.progress;
                    },
                );
            },
            get progressStyle() {
                return 'width:' + this.uploadProgress + '%';
            },
            validateFiles(files) {
                const maxBytes = this.config.maxSizeKb * 1024;
                const maxLabel = this.config.maxSizeKb >= 1024
                    ? (this.config.maxSizeKb / 1024).toFixed(1).replace(/\.0$/, '') + ' MB'
                    : this.config.maxSizeKb + ' KB';
                for (const file of files) {
                    if (!this.fileAllowed(file)) return this.config.labels.uploadInvalidType;
                    if (file.size > maxBytes) return this.config.labels.uploadTooLarge.replace(':max', maxLabel);
                }
                return null;
            },
            fileAllowed(file) {
                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const allowed = this.config.allowedExtensions || [];
                if (allowed.length && !allowed.includes(extension)) return false;

                const accept = this.config.accept;
                if (!accept || accept === '*') return true;
                return accept.split(',').map((part) => part.trim()).some((pattern) => {
                    if (pattern.endsWith('/*')) return file.type.startsWith(pattern.slice(0, -1));
                    if (pattern.startsWith('.')) return file.name.toLowerCase().endsWith(pattern.toLowerCase());
                    return file.type === pattern;
                });
            },
            notifyError(message) {
                this.$dispatch('notify', { variant: 'danger', title: this.config.labels.error, message });
            },

            /* -------------------------------------------------------- picker */

            bindPickerEvents() {
                const open = () => this.$dispatch('open-modal-' + this.config.modalName);
                const openEvent = this.config.pickerKey ? 'fm-picker-open-' + this.config.pickerKey : 'fm-picker-open';
                // Kept so destroy() can unbind them — otherwise every re-init
                // (wire:navigate, re-rendered parent) stacks another listener.
                this.windowListeners = [
                    [openEvent, open],
                    ['fm-picker-close', () => this.closePickerModal()],
                    ['fm:open-picker', (event) => {
                        const detail = event.detail || {};
                        if (detail.key && this.config.pickerKey && detail.key !== this.config.pickerKey) return;
                        if (detail.accept) this.config.accept = detail.accept;
                        open();
                    }],
                ];
                this.windowListeners.forEach(([name, handler]) => window.addEventListener(name, handler));
            },
            destroy() {
                (this.windowListeners || []).forEach(([name, handler]) => window.removeEventListener(name, handler));
            },
            openPickerModal() {
                this.$dispatch('open-modal-' + this.config.modalName);
            },
            closePickerModal() {
                this.$dispatch('close-modal-' + this.config.modalName);
            },
        }));
    };

    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
})();
