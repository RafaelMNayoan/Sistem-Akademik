<?php
require_once 'koneksi.php';
require_once __DIR__ . '/includes/ikon.php';
if (!empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') pindah('admin/dashboard.php');
    if ($role === 'mahasiswa') pindah('mahasiswa/dashboard.php');
    if (in_array($role, ['kaprodi','staff','bendahara','dosen'], true)) pindah('portal/dashboard.php');
}
if ((int)ambil_satu('SELECT COUNT(*) AS total FROM admin')['total'] === 0) {
    jalankan('INSERT INTO admin (username, nama, password) VALUES (?, ?, ?)', ['admin', 'Administrator', password_hash('admin123', PASSWORD_DEFAULT)]);
}
$error = '';
$role = post('role') ?: get('role');
$username = post('username');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($role === 'admin') {
        $u = ambil_satu('SELECT id, username, nama, password FROM admin WHERE username = ?', [$username]);
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true); $_SESSION = []; $_SESSION['role']='admin'; $_SESSION['admin_id']=$u['id']; pindah('admin/dashboard.php');
        }
    } elseif ($role === 'mahasiswa') {
        $u = ambil_satu('SELECT nim, nama_lengkap, password, status FROM mahasiswa WHERE nim = ?', [$username]);
        if ($u && password_verify($password, $u['password']) && $u['status'] !== 'Non-Aktif') {
            session_regenerate_id(true); $_SESSION = []; $_SESSION['role']='mahasiswa'; $_SESSION['nim']=$u['nim']; pindah('mahasiswa/dashboard.php');
        }
    } else {
        $u = ambil_satu('SELECT id, username, password, role, status FROM akun_pengguna WHERE username = ? AND role = ?', [$username, $role]);
        if ($u && $u['status'] === 'Aktif' && password_verify($password, $u['password'])) {
            session_regenerate_id(true); $_SESSION = []; $_SESSION['role']=$u['role']; $_SESSION['pengguna_id']=$u['id']; pindah('portal/dashboard.php');
        }
    }
    $error = 'Username/NIM atau password salah, atau akun tidak aktif.';
}
$label = ['admin'=>'Admin Website','kaprodi'=>'Kaprodi','staff'=>'Staff Akademik','bendahara'=>'Bendahara','dosen'=>'Dosen','mahasiswa'=>'Mahasiswa'];
if (!isset($label[$role])) {
    pindah('index.php');
}
$ikonPeran = ['admin' => 'shield', 'kaprodi' => 'clipboard', 'staff' => 'briefcase', 'bendahara' => 'banknote', 'dosen' => 'book-open', 'mahasiswa' => 'graduation'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk SIAKAD</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js"></script>
</head>
<body>
<button type="button" class="tema-tombol tema-melayang" data-tema-toggle aria-label="Ganti tema" title="Ganti tema">
    <span class="ikon-matahari"><?= ikon('sun', 19) ?></span><span class="ikon-bulan"><?= ikon('moon', 19) ?></span>
</button>
<div class="halaman-masuk">
    <div class="kotak-masuk">
        <div class="kepala-masuk">
            <span class="lambang-kecil">SP</span>
            <h1>SIAKAD STMIK Pesat Nabire</h1>
        </div>
        <p class="sub">Masuk sebagai <strong><?= e($label[$role]) ?></strong>.</p>
        <?php if ($error): ?><div class="pesan gagal" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="role" value="<?= e($role) ?>">
            <p>
                <label for="username"><?= $role === 'mahasiswa' ? 'NIM' : 'Username' ?></label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" placeholder="<?= $role === 'mahasiswa' ? 'Masukkan NIM Anda' : 'Masukkan username' ?>" autocomplete="username" required autofocus>
            </p>
            <p>
                <label for="password">Password</label>
                <span class="input-sandi"><input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required><button type="button" onclick="lihatSandi('password', this)">Lihat</button></span>
            </p>
            <button type="submit" class="tombol"><?= ikon($ikonPeran[$role] ?? 'lock', 18) ?> Masuk</button>
        </form>
        <?php if ($role === 'mahasiswa'): ?><p class="tautan">Belum punya akun? <a href="mahasiswa/register.php">Daftar</a></p><?php endif; ?>
        <p class="tautan"><a href="index.php"><?= ikon('arrow-left', 15) ?> Kembali ke beranda</a></p>
    </div>
</div>
</body>
</html>
