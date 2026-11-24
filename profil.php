<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (post('aksi') === 'sandi') {
        $lama = $_POST['sandi_lama'] ?? '';
        $baru = $_POST['sandi_baru'] ?? '';
        if (!password_verify($lama, $user['password'])) {
            pesan('Password lama salah.', 'gagal');
        } elseif (strlen($baru) < 6) {
            pesan('Password baru minimal 6 karakter.', 'gagal');
        } else {
            jalankan('UPDATE mahasiswa SET password = ? WHERE nim = ?',
                     [password_hash($baru, PASSWORD_DEFAULT), $user['nim']]);
            pesan('Password berhasil diganti.');
        }
        pindah('profil.php');
    }

    if (!filter_var(post('email'), FILTER_VALIDATE_EMAIL) || !in_array(post('jenis_kelamin'), ['L', 'P'])
        || !in_array(post('agama'), AGAMA) || post('nama_lengkap') === '') {
        pesan('Nama, email, jenis kelamin, atau agama tidak valid.', 'gagal');
        pindah('profil.php');
    }
    jalankan('UPDATE mahasiswa SET nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?,
              jenis_kelamin = ?, agama = ?, no_handphone = ?, email = ?, nama_ayah = ?,
              nama_ibu = ?, alamat = ? WHERE nim = ?', [
        post('nama_lengkap'), post('tempat_lahir'), post('tanggal_lahir'),
        post('jenis_kelamin'), post('agama'), post('no_handphone'), post('email'),
        post('nama_ayah'), post('nama_ibu'), post('alamat'), $user['nim'],
    ]);
    pesan('Profil berhasil disimpan.');
    pindah('profil.php');
}

$judul = 'Profil';
include '../includes/header.php';
?>
<h1>Profil Mahasiswa</h1>

<div class="dua-kolom">
    <div class="kartu">
        <h2>Data diri</h2>
        <form method="post" class="form-isi">
            <div>
                <label>NIM</label>
                <input type="text" value="<?= e($user['nim']) ?>" readonly>
            </div>
            <div>
                <label>NIK</label>
                <input type="text" value="<?= e($user['nik']) ?>" readonly>
            </div>
            <div class="penuh">
                <label for="nama_lengkap">Nama lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= e($user['nama_lengkap']) ?>" required>
            </div>
            <div>
                <label for="tempat_lahir">Tempat lahir</label>
                <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= e($user['tempat_lahir']) ?>" required>
            </div>
            <div>
                <label for="tanggal_lahir">Tanggal lahir</label>
                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($user['tanggal_lahir']) ?>" required>
            </div>
            <div>
                <label for="jenis_kelamin">Jenis kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <?= opsi(['L' => 'Laki-laki', 'P' => 'Perempuan'], $user['jenis_kelamin']) ?>
                </select>
            </div>
            <div>
                <label for="agama">Agama</label>
                <select id="agama" name="agama"><?= opsi(sama(AGAMA), $user['agama']) ?></select>
            </div>
            <div>
                <label for="no_handphone">No. handphone</label>
                <input type="tel" id="no_handphone" name="no_handphone" value="<?= e($user['no_handphone']) ?>" required>
            </div>
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
            </div>
            <div>
                <label for="nama_ayah">Nama ayah</label>
                <input type="text" id="nama_ayah" name="nama_ayah" value="<?= e($user['nama_ayah']) ?>" required>
            </div>
            <div>
                <label for="nama_ibu">Nama ibu</label>
                <input type="text" id="nama_ibu" name="nama_ibu" value="<?= e($user['nama_ibu']) ?>" required>
            </div>
            <div class="penuh">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3" required><?= e($user['alamat']) ?></textarea>
            </div>
            <div class="penuh baris-tombol">
                <button type="submit" class="tombol">Simpan perubahan</button>
            </div>
        </form>
    </div>

    <div>
        <div class="kartu">
            <h2>Data akademik</h2>
            <dl class="daftar-info">
                <div><dt>Program studi</dt><dd><?= e($user['program_studi']) ?></dd></div>
                <div><dt>Jenjang</dt><dd><?= e($user['jenjang']) ?></dd></div>
                <div><dt>Angkatan</dt><dd><?= e($user['angkatan']) ?></dd></div>
                <div><dt>Semester</dt><dd><?= e($user['semester']) ?></dd></div>
                <div><dt>Status</dt><dd><?= e($user['status']) ?></dd></div>
                <div><dt>Terdaftar</dt><dd><?= tanggal($user['tgl_daftar']) ?></dd></div>
            </dl>
        </div>

        <div class="kartu">
            <h2>Ganti password</h2>
            <form method="post">
                <input type="hidden" name="aksi" value="sandi">
                <p>
                    <label for="sandi_lama">Password lama</label>
                    <span class="input-sandi">
                        <input type="password" id="sandi_lama" name="sandi_lama" required>
                        <button type="button" onclick="lihatSandi('sandi_lama', this)">Lihat</button>
                    </span>
                </p>
                <p>
                    <label for="sandi_baru">Password baru</label>
                    <span class="input-sandi">
                        <input type="password" id="sandi_baru" name="sandi_baru" minlength="6" required>
                        <button type="button" onclick="lihatSandi('sandi_baru', this)">Lihat</button>
                    </span>
                </p>
                <button type="submit" class="tombol">Ganti password</button>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
