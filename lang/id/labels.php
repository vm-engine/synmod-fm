<?php

return [
    // Halaman
    'file_manager' => 'Pengelola File',

    // Aksi
    'upload' => 'Unggah',
    'refresh' => 'Perbarui',
    'sort_name' => 'Nama',
    'sort_size' => 'Ukuran',
    'sort_type' => 'Tipe',
    'sort_date' => 'Tanggal',
    'sort_asc' => 'Naik',
    'sort_desc' => 'Turun',
    'download' => 'Unduh',
    'drop_to_upload' => 'Letakkan file di sini untuk mengunggah',
    'new_folder' => 'Folder Baru',
    'rename' => 'Ganti Nama',
    'move' => 'Pindah',
    'copy' => 'Salin',
    'trash' => 'Sampah',
    'restore' => 'Pulihkan',
    'purge' => 'Hapus Permanen',
    'create' => 'Buat',
    'save' => 'Simpan',
    'cancel' => 'Batal',
    'browse' => 'Jelajahi',
    'clear' => 'Bersihkan',
    'select_all' => 'Pilih Semua',
    'preview' => 'Pratinjau',
    'open' => 'Buka',
    'delete' => 'Hapus',

    // Status
    'success' => 'Berhasil',
    'error' => 'Kesalahan',
    'no_permission' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
    'uploading' => 'Mengunggah file...',

    // Pesan
    'upload_success' => 'File berhasil diunggah.',
    'upload_failed' => 'Unggahan gagal. Periksa tipe dan ukuran file.',
    'upload_too_large' => 'File terlalu besar. Ukuran maksimum yang diizinkan adalah :max.',
    'upload_invalid_type' => 'Tipe file tidak diizinkan.',
    'trashed_success' => 'File dipindahkan ke sampah.',
    'restore_success' => 'File dipulihkan dari sampah.',
    'purge_success' => 'File dihapus secara permanen.',
    'rename_success' => 'File berhasil diganti nama.',
    'folder_created' => 'Folder berhasil dibuat.',

    // Tampilan
    'grid_view' => 'Tampilan Grid',
    'list_view' => 'Tampilan Daftar',
    'view_trash' => 'Lihat Sampah',
    'exit_trash' => 'Keluar dari Sampah',

    // Kolom Tabel
    'name' => 'Nama',
    'type' => 'Tipe',
    'size' => 'Ukuran',
    'date' => 'Tanggal',
    'actions' => 'Aksi',
    'folders' => 'Folder',

    // Status Kosong
    'folder_empty' => 'Folder ini kosong.',
    'trash_empty' => 'Sampah kosong.',

    // Pencarian
    'search_placeholder' => 'Cari file...',

    // Modal Ganti Nama
    'rename_file' => 'Ganti Nama File',
    'new_name' => 'Nama Baru',

    // Modal Folder Baru
    'folder_name' => 'Nama Folder',
    'folder_name_placeholder' => 'folder-saya',
    'folder_name_help' => 'Gunakan huruf, angka, tanda hubung, dan garis bawah saja.',

    // Dialog konfirmasi
    'trash_title' => 'Pindahkan ke Sampah',
    'trash_message' => 'Pindahkan ":name" ke sampah?',
    'yes_trash' => 'Pindahkan ke Sampah',
    'trash_selected_title' => 'Pindahkan ke Sampah',
    'trash_selected_message' => 'Pindahkan :count file ke sampah?',

    'restore_title' => 'Pulihkan File',
    'restore_message' => 'Pulihkan ":name" dari sampah?',
    'yes_restore' => 'Pulihkan',

    'purge_title' => 'Hapus Secara Permanen',
    'purge_message' => 'Hapus ":name" secara permanen? Tindakan ini tidak dapat dibatalkan.',
    'yes_purge' => 'Hapus Selamanya',
    'purge_selected_title' => 'Hapus Secara Permanen',
    'purge_selected_message' => 'Hapus :count file secara permanen? Tindakan ini tidak dapat dibatalkan.',

    // Pemilih File
    'select_file' => 'Pilih File',
    'no_file_selected' => 'Tidak ada file dipilih',
    'selected_file' => 'File dipilih',
    'click_to_select' => 'Klik file untuk memilih, klik dua kali untuk menggunakannya.',
    'invalid_folder' => 'Folder yang ditentukan tidak terdaftar dalam konfigurasi pengelola file.',

    // Desain ulang (2026-09)
    'cut' => 'Potong',
    'paste' => 'Tempel',
    'paste_count' => 'Tempel :count file',
    'details' => 'Detail',
    'delete_permanently' => 'Hapus permanen',
    'coming_soon' => 'Segera hadir',
    'clipboard_copy' => ':count file disalin ke papan klip.',
    'clipboard_cut' => ':count file dipotong ke papan klip.',
    'paste_success' => ':count file ditempel.',
    'storage' => 'Penyimpanan',
    'path' => 'Lokasi',
    'files' => 'File',
    'folder' => 'Folder',
    'expand' => 'Buka',
    'collapse' => 'Tutup',
    'toggle_folders' => 'Tampilkan folder',
    'sort' => 'Urutkan',
    'n_files' => ':count file',
    'n_folders' => ':count folder',
    'selection' => 'Pilihan',
    'select_item' => 'Pilih :name',
    'more_actions' => 'Aksi lain untuk :name',
    'close' => 'Tutup',
    'url' => 'URL',
    'copy_url' => 'Salin URL',
    'url_copied' => 'URL disalin ke papan klip.',
    'dimensions' => 'Dimensi',
    'created' => 'Dibuat',
    'uploaded_by' => 'Diunggah oleh',
    'location' => 'Lokasi',
    'in_location' => 'di :path',
    'no_results' => 'Tidak ada file yang cocok dengan ":q".',
    'choose' => 'Pilih',
    'not_configured' => 'Belum ada penyimpanan yang dikonfigurasi untuk pengelola file.',
    'not_configured_help' => 'Jalankan php artisan mod-fm:setup untuk menambahkannya.',
];
