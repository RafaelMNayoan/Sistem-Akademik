<?php
require_once '../koneksi.php';
$user = wajib_portal();
$modul = get('modul');
if (!isset(matriks_hak_akses()[$user['role']][$modul])) {
    wajib_portal($modul); // menampilkan halaman 403
}
pindah(alamat_modul($user['role'], $modul));
