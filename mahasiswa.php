<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM mahasiswa WHERE nim = ?', [post('hapus')]);
        pesan('Data mahasiswa dihapus (termasuk KRS, presensi, dan tagihannya).');
        pindah('mahasiswa.php');
    }

    $nimLama = post('nim_lama');
    $password = $_POST['password'] ?? '';

    $data = [
        post('nama_lengkap'), post('alamat'), post('tempat_lahir'), post('tanggal_lahir'),
        post('jenis_kelamin'), post('agama'), post('nik'), post('no_handphone'), post('email'),
        (int) post('angkatan'), post('program_studi'), post('jenjang'), post('nama_ayah'), post('nama_ibu'),
        post('status'), (int) post('semester'),
    ];

    $salah = '';
    if (post('nama_lengkap') === '' || post('alamat') === '') {
        $salah = 'Nama dan alamat wajib diisi.';
    } elseif (!preg_match('/^[0-9]{16}$/', post('nik'))) {
        $salah = 'NIK harus 16 digit angka.';
    } elseif (!filter_var(post('email'), FILTER_VALIDATE_EMAIL)) {
        $salah = 'Format email tidak valid.';
    } elseif (!strtotime(post('tanggal_lahir'))) {
        $salah = 'Tanggal lahir tidak valid.';
    } elseif (!in_array(post('jenis_kelamin'), ['L', 'P']) || !in_array(post('agama'), AGAMA)
              || !in_array(post('program_studi'), PRODI) || !in_array(post('jenjang'), JENJANG)
              || !in_array(post('status'), STATUS_MHS)) {
        $salah = 'Ada pilihan yang tidak valid.';
    } elseif ((int) post('semester') < 1 || (int) post('semester') > 14) {
        $salah = 'Semester harus antara 1 sampai 14.';
    } elseif ($nimLama === '' && strlen($password) < 6) {
        $salah = 'Password mahasiswa baru minimal 6 karakter.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $salah = 'Password minimal 6 karakter.';
    }

    if ($salah) {
        pesan($salah, 'gagal');
        pindah('mahasiswa.php' . ($nimLama ? '?edit=' . urlencode($nimLama) : ''));
    }

    try {
        if ($nimLama === '') {
            $nim = nim_baru();
            jalankan('INSERT INTO mahasiswa (nim, nama_lengkap, alamat, tempat_lahir, tanggal_lahir, jenis_kelamin,
                      agama, nik, no_handphone, email, angkatan, program_studi, jenjang, nama_ayah, nama_ibu,
                      status, semester, password, tgl_daftar)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                     array_merge([$nim], $data, [password_hash($password, PASSWORD_DEFAULT), date('Y-m-d')]));
            pesan('Mahasiswa ditambahkan dengan NIM ' . $nim . '.');
        } else {
            jalankan('UPDATE mahasiswa SET nama_lengkap = ?, alamat = ?, tempat_lahir = ?, tanggal_lahir = ?,
                      jenis_kelamin = ?, agama = ?, nik = ?, no_handphone = ?, email = ?, angkatan = ?,
                      program_studi = ?, jenjang = ?, nama_ayah = ?, nama_ibu = ?, status = ?, semester = ?
                      WHERE nim = ?', array_merge($data, [$nimLama]));
            if ($password !== '') {
                jalankan('UPDATE mahasiswa SET password = ? WHERE nim = ?',
                         [password_hash($password, PASSWORD_DEFAULT), $nimLama]);
            }
            pesan('Data mahasiswa diperbarui.');
        }
    } catch (PDOException $ex) {
        pesan('Gagal menyimpan: NIK sudah dipakai mahasiswa lain.', 'gagal');
    }
    pindah('mahasiswa.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM mahasiswa WHERE nim = ?', [get('edit')]) : null;

$cari = get('cari');
$daftar = ambil_semua('SELECT * FROM mahasiswa WHERE nim LIKE ? OR nama_lengkap LIKE ? ORDER BY nim',
                      ["%$cari%", "%$cari%"]);

$judul = 'Data Mahasiswa';
include '../includes/header.php';
?>
<h1>Data Mahasiswa</h1>

<div class="kartu">
    <h2><?= $edit ? 'Ubah mahasiswa ' . e($edit['nim']) : 'Tambah mahasiswa' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="nim_lama" value="<?= $edit ? e($edit['nim']) : '' ?>">
        <div class="penuh">
            <label for="nama_lengkap">Nama lengkap</label>
            <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= e($edit['nama_lengkap'] ?? '') ?>" required>
        </div>
        <div>
            <label for="tempat_lahir">Tempat lahir</label>
            <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= e($edit['tempat_lahir'] ?? '') ?>" required>
        </div>
        <div>
            <label for="tanggal_lahir">Tanggal lahir</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($edit['tanggal_lahir'] ?? '') ?>" required>
        </div>
        <div>
            <label for="jenis_kelamin">Jenis kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin"><?= opsi(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['jenis_kelamin'] ?? '') ?></select>
        </div>
        <div>
            <label for="agama">Agama</label>
            <select id="agama" name="agama"><?= opsi(sama(AGAMA), $edit['agama'] ?? '') ?></select>
        </div>
        <div>
            <label for="nik">NIK (16 digit)</label>
            <input type="text" id="nik" name="nik" maxlength="16" value="<?= e($edit['nik'] ?? '') ?>" required>
        </div>
        <div>
            <label for="no_handphone">No. handphone</label>
            <input type="tel" id="no_handphone" name="no_handphone" value="<?= e($edit['no_handphone'] ?? '') ?>" required>
        </div>
        <div class="penuh">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required>
        </div>
        <div>
            <label for="angkatan">Angkatan</label>
            <input type="number" id="angkatan" name="angkatan" min="2000" max="2100" value="<?= e($edit['angkatan'] ?? date('Y')) ?>" required>
        </div>
        <div>
            <label for="semester">Semester</label>
            <input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester'] ?? 1) ?>" required>
        </div>
        <div>
            <label for="program_studi">Program studi</label>
            <select id="program_studi" name="program_studi"><?= opsi(sama(PRODI), $edit['program_studi'] ?? '') ?></select>
        </div>
        <div>
            <label for="jenjang">Jenjang</label>
            <select id="jenjang" name="jenjang"><?= opsi(sama(JENJANG), $edit['jenjang'] ?? 'S1') ?></select>
        </div>
        <div>
            <label for="nama_ayah">Nama ayah</label>
            <input type="text" id="nama_ayah" name="nama_ayah" value="<?= e($edit['nama_ayah'] ?? '') ?>">
        </div>
        <div>
            <label for="nama_ibu">Nama ibu</label>
            <input type="text" id="nama_ibu" name="nama_ibu" value="<?= e($edit['nama_ibu'] ?? '') ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status"><?= opsi(sama(STATUS_MHS), $edit['status'] ?? 'Aktif') ?></select>
        </div>
        <div>
            <label for="password"><?= $edit ? 'Reset password (kosongkan jika tidak diganti)' : 'Password awal' ?></label>
            <span class="input-sandi">
                <input type="password" id="password" name="password" minlength="6" <?= $edit ? '' : 'required' ?>>
                <button type="button" onclick="lihatSandi('password', this)">Lihat</button>
            </span>
        </div>
        <div class="penuh">
            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat" rows="2" required><?= e($edit['alamat'] ?? '') ?></textarea>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah mahasiswa' ?></button>
            <?php if ($edit): ?><a href="mahasiswa.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar mahasiswa (<?= count($daftar) ?>)</h2>
    <form method="get" class="filter">
        <div>
            <label for="cari">Cari NIM atau nama</label>
            <input type="text" id="cari" name="cari" value="<?= e($cari) ?>">
        </div>
        <button type="submit" class="tombol abu">Cari</button>
    </form>

    <?php if (!$daftar): ?>
        <p class="kosong">Tidak ada data mahasiswa.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>NIM</th><th>Nama</th><th>Program studi</th><th>Sem</th><th>Status</th><th></th></tr>
                <?php foreach ($daftar as $m): ?>
                    <?php $warna = $m['status'] === 'Aktif' ? 'hijau' : ($m['status'] === 'Cuti' ? 'kuning' : 'abu'); ?>
                    <tr>
                        <td><?= e($m['nim']) ?></td>
                        <td><?= e($m['nama_lengkap']) ?></td>
                        <td><?= e($m['program_studi']) ?> (<?= e($m['jenjang']) ?>)</td>
                        <td><?= e($m['semester']) ?></td>
                        <td><span class="lencana <?= $warna ?>"><?= e($m['status']) ?></span></td>
                        <td>
                            <div class="aksi">
                                <a href="?edit=<?= e($m['nim']) ?>" class="tombol kecil">Ubah</a>
                                <form method="post" onsubmit="return confirm('Hapus mahasiswa ini beserta semua datanya?')">
                                    <input type="hidden" name="hapus" value="<?= e($m['nim']) ?>">
                                    <button type="submit" class="tombol merah kecil">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
