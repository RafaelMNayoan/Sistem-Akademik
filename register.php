<?php
require_once '../koneksi.php';
require_once __DIR__ . '/../includes/ikon.php';

if (($_SESSION['role'] ?? '') === 'mahasiswa') {
    pindah('dashboard.php');
}

$error = '';
$nimBaru = '';
$isi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isi = array_map('trim', $_POST);

    $wajib = ['nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'nik',
              'no_handphone', 'email', 'angkatan', 'program_studi', 'jenjang',
              'nama_ayah', 'nama_ibu', 'alamat', 'password'];
    $lengkap = true;
    foreach ($wajib as $kolom) {
        if (empty($isi[$kolom])) {
            $lengkap = false;
        }
    }

    if (!$lengkap) {
        $error = 'Semua kolom harus diisi.';
    } elseif (!preg_match('/^[0-9]{16}$/', $isi['nik'])) {
        $error = 'NIK harus 16 digit angka.';
    } elseif (!filter_var($isi['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!in_array($isi['jenis_kelamin'], ['L', 'P']) || !in_array($isi['jenjang'], JENJANG)
              || !in_array($isi['agama'], AGAMA) || !in_array($isi['program_studi'], PRODI)) {
        $error = 'Pilihan jenis kelamin, agama, program studi, atau jenjang tidak valid.';
    } elseif (strlen($isi['password']) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        try {
            $nim = nim_baru();
            jalankan('INSERT INTO mahasiswa
                (nim, nama_lengkap, alamat, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, nik,
                 no_handphone, email, angkatan, program_studi, jenjang, nama_ayah, nama_ibu, password, tgl_daftar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $nim, $isi['nama_lengkap'], $isi['alamat'], $isi['tempat_lahir'], $isi['tanggal_lahir'],
                $isi['jenis_kelamin'], $isi['agama'], $isi['nik'], $isi['no_handphone'], $isi['email'],
                $isi['angkatan'], $isi['program_studi'], $isi['jenjang'], $isi['nama_ayah'], $isi['nama_ibu'],
                password_hash($isi['password'], PASSWORD_DEFAULT),
                date('Y-m-d'),
            ]);
            $nimBaru = $nim;
        } catch (PDOException $ex) {
            $error = $ex->getCode() == 23000 ? 'NIK ini sudah terdaftar.' : 'Pendaftaran gagal, coba lagi.';
        }
    }
}

function isian($kolom) {
    global $isi;
    return e($isi[$kolom] ?? '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Mahasiswa - SIAKAD</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/app.js"></script>
</head>
<body>
<button type="button" class="tema-tombol tema-melayang" data-tema-toggle aria-label="Ganti tema" title="Ganti tema">
    <span class="ikon-matahari"><?= ikon('sun', 19) ?></span><span class="ikon-bulan"><?= ikon('moon', 19) ?></span>
</button>
<div class="halaman-masuk">
    <div class="kotak-masuk lebar">
        <h1>Pendaftaran Mahasiswa Baru</h1>
        <p class="sub">Isi data diri dengan benar. NIM dibuat otomatis.</p>

<?php if ($nimBaru): ?>
        <div class="pesan sukses">
            Pendaftaran berhasil. NIM Anda <strong><?= e($nimBaru) ?></strong>. Catat NIM ini untuk masuk ke portal.
        </div>
        <a href="../login.php?role=mahasiswa" class="tombol">Ke halaman masuk</a>
<?php else: ?>

        <?php if ($error): ?>
            <div class="pesan gagal"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-isi">
            <div class="penuh">
                <label for="nama_lengkap">Nama lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= isian('nama_lengkap') ?>" required>
            </div>
            <div>
                <label for="tempat_lahir">Tempat lahir</label>
                <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= isian('tempat_lahir') ?>" required>
            </div>
            <div>
                <label for="tanggal_lahir">Tanggal lahir</label>
                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= isian('tanggal_lahir') ?>" required>
            </div>
            <div>
                <label for="jenis_kelamin">Jenis kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin" required>
                    <option value="">Pilih</option>
                    <?= opsi(['L' => 'Laki-laki', 'P' => 'Perempuan'], $isi['jenis_kelamin'] ?? '') ?>
                </select>
            </div>
            <div>
                <label for="agama">Agama</label>
                <select id="agama" name="agama" required>
                    <option value="">Pilih</option>
                    <?= opsi(sama(AGAMA), $isi['agama'] ?? '') ?>
                </select>
            </div>
            <div>
                <label for="nik">NIK (16 digit)</label>
                <input type="text" id="nik" name="nik" maxlength="16" value="<?= isian('nik') ?>" required>
            </div>
            <div>
                <label for="no_handphone">No. handphone</label>
                <input type="tel" id="no_handphone" name="no_handphone" value="<?= isian('no_handphone') ?>" required>
            </div>
            <div class="penuh">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= isian('email') ?>" required>
            </div>
            <div>
                <label for="angkatan">Angkatan</label>
                <select id="angkatan" name="angkatan" required>
                    <?php for ($t = date('Y'); $t >= date('Y') - 5; $t--): ?>
                        <option <?= ($isi['angkatan'] ?? '') == $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="program_studi">Program studi</label>
                <select id="program_studi" name="program_studi" required>
                    <option value="">Pilih</option>
                    <?= opsi(sama(PRODI), $isi['program_studi'] ?? '') ?>
                </select>
            </div>
            <div>
                <label for="jenjang">Jenjang</label>
                <select id="jenjang" name="jenjang" required>
                    <?= opsi(['S1' => 'Sarjana (S1)', 'D3' => 'Diploma III (D3)'], $isi['jenjang'] ?? 'S1') ?>
                </select>
            </div>
            <div>
                <label for="nama_ayah">Nama ayah</label>
                <input type="text" id="nama_ayah" name="nama_ayah" value="<?= isian('nama_ayah') ?>" required>
            </div>
            <div>
                <label for="nama_ibu">Nama ibu</label>
                <input type="text" id="nama_ibu" name="nama_ibu" value="<?= isian('nama_ibu') ?>" required>
            </div>
            <div class="penuh">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3" required><?= isian('alamat') ?></textarea>
            </div>
            <div class="penuh">
                <label for="password">Password (minimal 6 karakter)</label>
                <span class="input-sandi">
                    <input type="password" id="password" name="password" minlength="6" required>
                    <button type="button" onclick="lihatSandi('password', this)">Lihat</button>
                </span>
            </div>
            <div class="penuh">
                <button type="submit" class="tombol">Daftar</button>
            </div>
        </form>

        <p class="tautan">Sudah punya akun? <a href="../login.php?role=mahasiswa">Masuk</a></p>
        <p class="tautan"><a href="../index.php"><?= ikon('arrow-left', 15) ?> Kembali ke beranda</a></p>
<?php endif; ?>
    </div>
</div>
</body>
</html>
