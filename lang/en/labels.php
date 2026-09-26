<?php

return [
    // Page
    'file_manager' => 'File Manager',

    // Actions
    'upload' => 'Upload',
    'refresh' => 'Refresh',
    'sort_name' => 'Name',
    'sort_size' => 'Size',
    'sort_type' => 'Type',
    'sort_date' => 'Date',
    'sort_asc' => 'Ascending',
    'sort_desc' => 'Descending',
    'download' => 'Download',
    'drop_to_upload' => 'Drop files here to upload',
    'new_folder' => 'New Folder',
    'rename' => 'Rename',
    'move' => 'Move',
    'copy' => 'Copy',
    'trash' => 'Trash',
    'restore' => 'Restore',
    'purge' => 'Purge',
    'create' => 'Create',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'browse' => 'Browse',
    'clear' => 'Clear',
    'select_all' => 'Select All',
    'preview' => 'Preview',
    'open' => 'Open',
    'delete' => 'Delete',

    // State
    'success' => 'Success',
    'error' => 'Error',
    'no_permission' => 'You do not have permission to perform this action.',
    'uploading' => 'Uploading files...',

    // Messages
    'upload_success' => 'Files uploaded successfully.',
    'upload_failed' => 'Upload failed. Check file type and size.',
    'upload_too_large' => 'File is too large. Maximum allowed size is :max.',
    'upload_invalid_type' => 'File type is not allowed.',
    'trashed_success' => 'File(s) moved to trash.',
    'restore_success' => 'File(s) restored from trash.',
    'purge_success' => 'File(s) permanently deleted.',
    'rename_success' => 'File renamed successfully.',
    'folder_created' => 'Folder created successfully.',

    // Views
    'grid_view' => 'Grid View',
    'list_view' => 'List View',
    'view_trash' => 'View Trash',
    'exit_trash' => 'Exit Trash',

    // Table Columns
    'name' => 'Name',
    'type' => 'Type',
    'size' => 'Size',
    'date' => 'Date',
    'actions' => 'Actions',
    'folders' => 'Folders',

    // Empty States
    'folder_empty' => 'This folder is empty.',
    'trash_empty' => 'Trash is empty.',

    // Search
    'search_placeholder' => 'Search files...',

    // Rename Modal
    'rename_file' => 'Rename File',
    'new_name' => 'New Name',

    // New Folder Modal
    'folder_name' => 'Folder Name',
    'folder_name_placeholder' => 'my-folder',
    'folder_name_help' => 'Use letters, numbers, hyphens, and underscores only.',

    // Confirmation dialogs
    'trash_title' => 'Move to Trash',
    'trash_message' => 'Move ":name" to trash?',
    'yes_trash' => 'Move to Trash',
    'trash_selected_title' => 'Move to Trash',
    'trash_selected_message' => 'Move :count file(s) to trash?',

    'restore_title' => 'Restore File',
    'restore_message' => 'Restore ":name" from trash?',
    'yes_restore' => 'Restore',

    'purge_title' => 'Permanently Delete',
    'purge_message' => 'Permanently delete ":name"? This cannot be undone.',
    'yes_purge' => 'Delete Forever',
    'purge_selected_title' => 'Permanently Delete',
    'purge_selected_message' => 'Permanently delete :count file(s)? This cannot be undone.',

    // File Picker
    'select_file' => 'Select a File',
    'no_file_selected' => 'No file selected',
    'selected_file' => 'Selected file',
    'click_to_select' => 'Click a file to select it, double-click to choose.',
    'invalid_folder' => 'The specified folder is not registered in the file manager configuration.',

    // Redesign (2026-09)
    'cut' => 'Cut',
    'paste' => 'Paste',
    'paste_count' => 'Paste :count file(s)',
    'details' => 'Details',
    'delete_permanently' => 'Delete permanently',
    'coming_soon' => 'Coming soon',
    'clipboard_copy' => '{1} :count file copied to clipboard.|[2,*] :count files copied to clipboard.',
    'clipboard_cut' => '{1} :count file cut to clipboard.|[2,*] :count files cut to clipboard.',
    'paste_success' => '{1} :count file pasted.|[2,*] :count files pasted.',
    'storage' => 'Storage',
    'path' => 'Path',
    'files' => 'Files',
    'folder' => 'Folder',
    'expand' => 'Expand',
    'collapse' => 'Collapse',
    'toggle_folders' => 'Show folders',
    'sort' => 'Sort',
    'n_files' => '{1} :count file|[2,*] :count files',
    'n_folders' => '{1} :count folder|[2,*] :count folders',
    'selection' => 'Selection',
    'select_item' => 'Select :name',
    'more_actions' => 'More actions for :name',
    'close' => 'Close',
    'url' => 'URL',
    'copy_url' => 'Copy URL',
    'url_copied' => 'URL copied to clipboard.',
    'dimensions' => 'Dimensions',
    'created' => 'Created',
    'uploaded_by' => 'Uploaded by',
    'location' => 'Location',
    'in_location' => 'in :path',
    'no_results' => 'No files match ":q".',
    'search_truncated' => 'Showing the first :count results — refine your search to narrow it down.',
    'choose' => 'Choose',
    'not_configured' => 'No storage is configured for the file manager.',
    'not_configured_help' => 'Run php artisan mod-fm:setup to add one.',
    'preview_unavailable' => 'Preview unavailable',
    'zoom_in' => 'Zoom in',
    'zoom_out' => 'Zoom out',
    'previous' => 'Previous',
    'next' => 'Next',
    'page_counter' => ':page / :total',
];
