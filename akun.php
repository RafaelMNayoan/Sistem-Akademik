<?php
require_once '../koneksi.php';
$user = wajib_portal();
if ($user['role'] === 'admin') {
    pindah('../admin/akun.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = $_POST['sandi_lama'] ?? '';
    $baru = $_POST['sandi_baru'] ?? '';
    $nama = post('nama');
    $baris = ambil_satu('SELECT password FROM akun_pengguna WHERE id = ?', [$user['id']]);
    if ($nama === '') {
        pesan('Nama tidak boleh kosong.', 'gagal');
    } elseif (!password_verify($lama, $baris['password'])) {
        pesan('Password lama salah.', 'gagal');
    } elseif ($baru !== '' && strlen($baru) < 8) {
        pesan('Password baru minimal 8 karakter.', 'gagal');
    } else {
        $hash = $baru !== '' ? password_hash($baru, PASSWORD_DEFAULT) : $baris['password'];
        jalankan('UPDATE akun_pengguna SET nama = ?, password = ? WHERE id = ?', [$nama, $hash, $user['id']]);
        if ($user['role'] === 'dosen' && $user['dosen_id']) {
            jalankan('UPDATE dosen SET nama = ? WHERE id = ?', [$nama, $user['dosen_id']]);
        }
        catat_log('akun', 'ubah profil sendiri', $baru !== '' ? 'nama dan password diubah' : 'nama diubah');
        pesan('Akun berhasil diperbarui.');
    }
    pindah('akun.php');
}
$judul = 'Akun Saya';
include '../includes/header.php';
?>
<h1>Akun Saya</h1>
<div class="kartu sempit">
    <form method="post">
        <p><label>Username</label><input type="text" value="<?= e($user['username']) ?>" readonly></p>
        <p><label>Peran</label><input type="text" value="<?= e(nama_peran($user['role'])) ?>" readonly></p>
        <p><label for="nama">Nama</label><input type="text" id="nama" name="nama" value="<?= e($user['nama']) ?>" required></p>
        <p><label for="sandi_lama">Password lama (wajib untuk menyimpan)</label>
            <span class="input-sandi"><input type="password" id="sandi_lama" name="sandi_lama" required><button type="button" onclick="lihatSandi('sandi_lama', this)">Lihat</button></span></p>
        <p><label for="sandi_baru">Password baru (kosongkan jika tidak diganti, minimal 8 karakter)</label>
            <span class="input-sandi"><input type="password" id="sandi_baru" name="sandi_baru" minlength="8"><button type="button" onclick="lihatSandi('sandi_baru', this)">Lihat</button></span></p>
        <button type="submit" class="tombol">Simpan</button>
    </form>
</div>
<?php include '../includes/footer.php'; ?>
