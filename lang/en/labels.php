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
    'move_success' => 'File(s) moved/copied successfully.',

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

    // Selection
    'items_selected' => 'item(s) selected',

    // Rename Modal
    'rename_file' => 'Rename File',
    'new_name' => 'New Name',

    // New Folder Modal
    'folder_name' => 'Folder Name',
    'folder_name_placeholder' => 'my-folder',
    'folder_name_help' => 'Use letters, numbers, hyphens, and underscores only.',

    // Move / Copy Modal
    'move_files' => 'Move Files',
    'copy_files' => 'Copy Files',
    'target_folder' => 'Target Folder',
    'target_sub_path' => 'Sub-path (optional)',
    'target_sub_path_placeholder' => 'documents/reports',
    'target_sub_path_help' => 'Leave empty to move to folder root.',

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
    'click_to_select' => 'Click a file to select it.',
    'invalid_folder' => 'The specified folder is not registered in the file manager configuration.',
];
