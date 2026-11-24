<?php
require_once '../koneksi.php';
$user = wajib_portal('mahasiswa');
$role = $user['role'];
$bp = prodi_batas();
$didDosen = (int) ($user['dosen_id'] ?? 0);
$bisaTulis = bisa('C') || bisa('U');
$tugasPA = in_array($role, ['kaprodi', 'admin'], true); // kaprodi menugaskan dosen PA

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('aksi') === 'pa') {
        $nim = post('nim');
        $dosenId = (int) post('dosen_id') ?: null;
        $m = ambil_satu('SELECT nim, program_studi, dosen_pa_id FROM mahasiswa WHERE nim = ?', [$nim]);
        if (!$tugasPA || !$m || ($bp !== null && $m['program_studi'] !== $bp)) {
            pesan('Anda tidak berhak menetapkan dosen PA untuk mahasiswa ini.', 'gagal');
        } elseif ($dosenId && !ambil_satu("SELECT id FROM dosen WHERE id = ? AND status = 'Aktif'", [$dosenId])) {
            pesan('Dosen tidak ditemukan.', 'gagal');
        } else {
            jalankan('UPDATE mahasiswa SET dosen_pa_id = ? WHERE nim = ?', [$dosenId, $nim]);
            catat_log('mahasiswa', 'tetapkan dosen PA', "nim $nim: dosen_pa_id " . ($m['dosen_pa_id'] ?? '-') . ' -> ' . ($dosenId ?? '-'));
            pesan('Dosen pembimbing akademik ditetapkan.');
        }
        pindah('mahasiswa.php');
    }

    if (!$bisaTulis) { pesan('Anda tidak berhak mengubah data mahasiswa.', 'gagal'); pindah('mahasiswa.php'); }
    $nimLama = post('nim_lama');
    if ($nimLama !== '' ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak untuk aksi ini.', 'gagal'); pindah('mahasiswa.php'); }
    $password = $_POST['password'] ?? '';
    $kolom = ['nama_lengkap' => post('nama_lengkap'), 'alamat' => post('alamat'), 'tempat_lahir' => post('tempat_lahir'), 'tanggal_lahir' => post('tanggal_lahir'),
        'jenis_kelamin' => post('jenis_kelamin'), 'agama' => post('agama'), 'nik' => post('nik'), 'no_handphone' => post('no_handphone'), 'email' => post('email'),
        'angkatan' => (int) post('angkatan'), 'program_studi' => post('program_studi'), 'jenjang' => post('jenjang'), 'nama_ayah' => post('nama_ayah'),
        'nama_ibu' => post('nama_ibu'), 'status' => post('status'), 'semester' => (int) post('semester')];
    $salah = '';
    if ($kolom['nama_lengkap'] === '' || $kolom['alamat'] === '') $salah = 'Nama dan alamat wajib diisi.';
    elseif (!preg_match('/^[0-9]{16}$/', $kolom['nik'])) $salah = 'NIK harus 16 digit angka.';
    elseif (!filter_var($kolom['email'], FILTER_VALIDATE_EMAIL)) $salah = 'Format email tidak valid.';
    elseif (!strtotime($kolom['tanggal_lahir'])) $salah = 'Tanggal lahir tidak valid.';
    elseif (!in_array($kolom['jenis_kelamin'], ['L', 'P'], true) || !in_array($kolom['agama'], AGAMA, true) || !in_array($kolom['program_studi'], PRODI, true)
        || !in_array($kolom['jenjang'], JENJANG, true) || !in_array($kolom['status'], STATUS_MHS, true)) $salah = 'Ada pilihan yang tidak valid.';
    elseif ($kolom['semester'] < 1 || $kolom['semester'] > 14) $salah = 'Semester harus antara 1 sampai 14.';
    elseif ($nimLama === '' && strlen($password) < 6) $salah = 'Password awal mahasiswa baru minimal 6 karakter.';
    if ($salah) { pesan($salah, 'gagal'); pindah('mahasiswa.php' . ($nimLama ? '?edit=' . urlencode($nimLama) : '')); }
    try {
        if ($nimLama === '') {
            $nim = nim_baru();
            jalankan('INSERT INTO mahasiswa (nim, nama_lengkap, alamat, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, nik, no_handphone, email, angkatan, program_studi, jenjang, nama_ayah, nama_ibu, status, semester, password, tgl_daftar)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([$nim], array_values($kolom), [password_hash($password, PASSWORD_DEFAULT), date('Y-m-d')]));
            catat_log('mahasiswa', 'tambah mahasiswa', $nim . ' ' . $kolom['nama_lengkap']);
            pesan('Mahasiswa ditambahkan dengan NIM ' . $nim . '.');
        } else {
            $lama = ambil_satu('SELECT * FROM mahasiswa WHERE nim = ?', [$nimLama]);
            if (!$lama) { pesan('Mahasiswa tidak ditemukan.', 'gagal'); pindah('mahasiswa.php'); }
            jalankan('UPDATE mahasiswa SET nama_lengkap = ?, alamat = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?, agama = ?, nik = ?, no_handphone = ?, email = ?, angkatan = ?, program_studi = ?, jenjang = ?, nama_ayah = ?, nama_ibu = ?, status = ?, semester = ? WHERE nim = ?',
                     array_merge(array_values($kolom), [$nimLama]));
            catat_log('mahasiswa', 'ubah mahasiswa', "nim $nimLama: " . beda($lama, $kolom));
            pesan('Data mahasiswa diperbarui.');
        }
    } catch (PDOException $ex) {
        pesan('Gagal menyimpan: NIK sudah dipakai mahasiswa lain.', 'gagal');
    }
    pindah('mahasiswa.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM mahasiswa WHERE nim = ?', [get('edit')]) : null;
$cari = get('cari');
$param = ["%$cari%", "%$cari%"];
$syarat = '(mh.nim LIKE ? OR mh.nama_lengkap LIKE ?)';
if ($bp !== null) {
    $syarat .= ' AND mh.program_studi = ?';
    $param[] = $bp;
}
if ($role === 'dosen') {
    [$in, $pi] = daftar_in(mk_dosen());
    $syarat .= " AND (mh.dosen_pa_id = ? OR mh.nim IN (SELECT k.nim FROM krs k WHERE k.kode_mk IN ($in)) OR mh.nim IN (SELECT s.nim FROM skripsi s WHERE s.pembimbing_id = ?))";
    $param = array_merge($param, [$didDosen], $pi, [$didDosen]);
}
$daftar = ambil_semua("SELECT mh.*, d.nama AS nama_pa FROM mahasiswa mh LEFT JOIN dosen d ON d.id = mh.dosen_pa_id WHERE $syarat ORDER BY mh.nim", $param);
$lihatBayar = in_array($role, ['bendahara', 'admin'], true);
$pilihanPA = $tugasPA ? daftar_dosen($bp ?? '') : [];

$judul = 'Data Mahasiswa';
include '../includes/header.php';
?>
<h1>Data Mahasiswa</h1>
<?php if ($bisaTulis && ($edit || bisa('C'))): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah mahasiswa ' . e($edit['nim']) : 'Tambah mahasiswa' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="nim_lama" value="<?= $edit ? e($edit['nim']) : '' ?>">
        <div class="penuh"><label for="nama_lengkap">Nama lengkap</label><input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= e($edit['nama_lengkap'] ?? '') ?>" required></div>
        <div><label for="tempat_lahir">Tempat lahir</label><input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= e($edit['tempat_lahir'] ?? '') ?>" required></div>
        <div><label for="tanggal_lahir">Tanggal lahir</label><input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($edit['tanggal_lahir'] ?? '') ?>" required></div>
        <div><label for="jenis_kelamin">Jenis kelamin</label><select id="jenis_kelamin" name="jenis_kelamin"><?= opsi(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['jenis_kelamin'] ?? '') ?></select></div>
        <div><label for="agama">Agama</label><select id="agama" name="agama"><?= opsi(sama(AGAMA), $edit['agama'] ?? '') ?></select></div>
        <div><label for="nik">NIK (16 digit)</label><input type="text" id="nik" name="nik" maxlength="16" value="<?= e($edit['nik'] ?? '') ?>" required></div>
        <div><label for="no_handphone">No. handphone</label><input type="tel" id="no_handphone" name="no_handphone" value="<?= e($edit['no_handphone'] ?? '') ?>" required></div>
        <div class="penuh"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required></div>
        <div><label for="angkatan">Angkatan</label><input type="number" id="angkatan" name="angkatan" min="2000" max="2100" value="<?= e($edit['angkatan'] ?? date('Y')) ?>" required></div>
        <div><label for="semester">Semester</label><input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester'] ?? 1) ?>" required></div>
        <div><label for="program_studi">Program studi</label><select id="program_studi" name="program_studi"><?= opsi(sama(PRODI), $edit['program_studi'] ?? '') ?></select></div>
        <div><label for="jenjang">Jenjang</label><select id="jenjang" name="jenjang"><?= opsi(sama(JENJANG), $edit['jenjang'] ?? 'S1') ?></select></div>
        <div><label for="nama_ayah">Nama ayah</label><input type="text" id="nama_ayah" name="nama_ayah" value="<?= e($edit['nama_ayah'] ?? '') ?>"></div>
        <div><label for="nama_ibu">Nama ibu</label><input type="text" id="nama_ibu" name="nama_ibu" value="<?= e($edit['nama_ibu'] ?? '') ?>"></div>
        <div><label for="status">Status</label><select id="status" name="status"><?= opsi(sama(STATUS_MHS), $edit['status'] ?? 'Aktif') ?></select></div>
        <?php if (!$edit): ?><div><label for="password">Password awal</label><span class="input-sandi"><input type="password" id="password" name="password" minlength="6" required><button type="button" onclick="lihatSandi('password', this)">Lihat</button></span></div><?php endif; ?>
        <div class="penuh"><label for="alamat">Alamat</label><textarea id="alamat" name="alamat" rows="2" required><?= e($edit['alamat'] ?? '') ?></textarea></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah mahasiswa' ?></button><?php if ($edit): ?><a href="mahasiswa.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar mahasiswa (<?= count($daftar) ?>)<?= $bp !== null ? ' - prodi ' . e($bp) : '' ?><?= $role === 'dosen' ? ' - kelas &amp; bimbingan saya' : '' ?></h2>
    <form method="get" class="filter"><div><label for="cari">Cari NIM atau nama</label><input type="text" id="cari" name="cari" value="<?= e($cari) ?>"></div><button type="submit" class="tombol abu">Cari</button></form>
    <?php if (!$daftar): ?><p class="kosong">Tidak ada data mahasiswa.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>NIM</th><th>Nama</th><th>Program studi</th><th>Angkatan</th><th>Sem</th><th>Status</th><?php if ($role !== 'bendahara'): ?><th>Dosen PA</th><?php endif; ?><?php if ($lihatBayar): ?><th>Status bayar</th><?php endif; ?><?php if ($bisaTulis || $tugasPA): ?><th></th><?php endif; ?></tr>
        <?php foreach ($daftar as $m): ?><tr>
            <td><?= e($m['nim']) ?></td><td><?= e($m['nama_lengkap']) ?></td><td><?= e($m['program_studi']) ?> (<?= e($m['jenjang']) ?>)</td><td><?= e($m['angkatan']) ?></td><td><?= e($m['semester']) ?></td><td><?= lencana($m['status']) ?></td>
            <?php if ($role !== 'bendahara'): ?><td><?php if ($tugasPA): ?>
                <form method="post" class="aksi"><input type="hidden" name="aksi" value="pa"><input type="hidden" name="nim" value="<?= e($m['nim']) ?>">
                    <select name="dosen_id"><option value="">-</option><?= opsi($pilihanPA, $m['dosen_pa_id'] ?? '') ?></select><button type="submit" class="tombol kecil">Tetapkan</button></form>
                <?php else: ?><?= e($m['nama_pa'] ?: '-') ?><?php endif; ?></td><?php endif; ?>
            <?php if ($lihatBayar): ?><td><?= jumlah_tunggakan($m['nim']) === 0 ? lencana('Lunas') : lencana('Belum lunas') ?></td><?php endif; ?>
            <?php if ($bisaTulis || $tugasPA): ?><td><?php if (bisa('U')): ?><a href="?edit=<?= e($m['nim']) ?>" class="tombol kecil">Ubah</a><?php endif; ?></td><?php endif; ?>
        </tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
