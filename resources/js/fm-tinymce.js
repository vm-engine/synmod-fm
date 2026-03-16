/**
 * TinyMCE integration for the Synapse File Manager.
 *
 * Usage:
 *   import fmTinyMCEConfig from './fm-tinymce';
 *   tinymce.init({ ...fmTinyMCEConfig });
 *
 * Or merge into an existing TinyMCE init:
 *   tinymce.init({
 *     selector: '#editor',
 *     plugins: 'image link',
 *     ...fmTinyMCEConfig,
 *   });
 */

// Listen for the file-selected event dispatched by the FilePicker Livewire component
document.addEventListener('fm:file-selected', (event) => {
    const url = event.detail?.url;
    const callback = window._fmTinyMCECallback;

    if (callback && typeof callback === 'function' && url) {
        callback(url, { title: '' });
        window._fmTinyMCECallback = null;
    }
});

const fmTinyMCEConfig = {
    file_picker_types: 'file image media',

    /**
     * Called by TinyMCE when the user clicks "Insert image" or a link's file
     * picker. We store the callback, dispatch fm:open-picker to open the
     * Livewire FilePicker modal, and wait for fm:file-selected to come back.
     *
     * @param {Function} callback  TinyMCE callback to call with the chosen URL
     * @param {string}   value     Current field value
     * @param {Object}   meta      { filetype: 'image'|'media'|'file' }
     */
    file_picker_callback(callback, value, meta) {
        // Store callback so the event listener above can call it
        window._fmTinyMCECallback = callback;

        const accept = meta.filetype === 'image' ? 'image/*' : '*';

        // Dispatch to Livewire to open the FilePicker component
        if (window.Livewire) {
            Livewire.dispatch('fm:open-picker', { accept });
        } else {
            document.dispatchEvent(new CustomEvent('fm:open-picker', {
                detail: { accept },
                bubbles: true,
            }));
        }
    },
};

export default fmTinyMCEConfig;
