<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = post('nama');
    $lama = $_POST['sandi_lama'] ?? '';
    $baru = $_POST['sandi_baru'] ?? '';

    if ($nama === '') {
        pesan('Nama tidak boleh kosong.', 'gagal');
    } elseif (!password_verify($lama, $user['password'])) {
        pesan('Password lama salah.', 'gagal');
    } elseif ($baru !== '' && strlen($baru) < 6) {
        pesan('Password baru minimal 6 karakter.', 'gagal');
    } else {
        $hash = $baru !== '' ? password_hash($baru, PASSWORD_DEFAULT) : $user['password'];
        jalankan('UPDATE admin SET nama = ?, password = ? WHERE id = ?', [$nama, $hash, $user['id']]);
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
        <p>
            <label>Username</label>
            <input type="text" value="<?= e($user['username']) ?>" readonly>
        </p>
        <p>
            <label for="nama">Nama</label>
            <input type="text" id="nama" name="nama" value="<?= e($user['nama']) ?>" required>
        </p>
        <p>
            <label for="sandi_lama">Password lama (wajib untuk menyimpan)</label>
            <span class="input-sandi">
                <input type="password" id="sandi_lama" name="sandi_lama" required>
                <button type="button" onclick="lihatSandi('sandi_lama', this)">Lihat</button>
            </span>
        </p>
        <p>
            <label for="sandi_baru">Password baru (kosongkan jika tidak diganti)</label>
            <span class="input-sandi">
                <input type="password" id="sandi_baru" name="sandi_baru" minlength="6">
                <button type="button" onclick="lihatSandi('sandi_baru', this)">Lihat</button>
            </span>
        </p>
        <button type="submit" class="tombol">Simpan</button>
    </form>
</div>
<?php include '../includes/footer.php'; ?>
