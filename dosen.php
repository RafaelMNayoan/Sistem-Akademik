<?php
require_once '../koneksi.php';
$user = wajib_portal('dosen');
$role = $user['role'];
$bp = prodi_batas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus'])) {
        if (!bisa('D')) { pesan('Anda tidak berhak menghapus data dosen.', 'gagal'); pindah('dosen.php'); }
        $d = ambil_satu('SELECT * FROM dosen WHERE id = ?', [(int) $_POST['hapus']]);
        if ($d) {
            jalankan('UPDATE kelas_kuliah SET dosen_id = NULL WHERE dosen_id = ?', [$d['id']]);
            jalankan('UPDATE mahasiswa SET dosen_pa_id = NULL WHERE dosen_pa_id = ?', [$d['id']]);
            jalankan('UPDATE skripsi SET pembimbing_id = NULL WHERE pembimbing_id = ?', [$d['id']]);
            jalankan('DELETE FROM dosen WHERE id = ?', [$d['id']]);
            catat_log('dosen', 'hapus dosen', $d['nip'] . ' ' . $d['nama']);
            pesan('Data dosen dihapus.');
        }
        pindah('dosen.php');
    }

    $id = (int) post('id');
    $data = ['nip' => post('nip'), 'nama' => post('nama'), 'prodi' => kosong_ke_null(post('prodi')), 'email' => kosong_ke_null(post('email')),
             'no_hp' => kosong_ke_null(post('no_hp')), 'status' => post('status'), 'akun_id' => (int) post('akun_id') ?: null];
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak menyimpan data dosen.', 'gagal'); pindah('dosen.php'); }
    if ($data['nip'] === '' || $data['nama'] === '' || !in_array($data['status'], ['Aktif', 'Non-Aktif'], true)
        || ($data['prodi'] !== null && !in_array($data['prodi'], PRODI, true))
        || ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) {
        pesan('NIP, nama, status, prodi, dan email harus diisi dengan benar.', 'gagal');
        pindah('dosen.php' . ($id ? '?edit=' . $id : ''));
    }
    // Buat akun login baru sekaligus bila username diisi (hanya saat menambah dosen).
    $userBaru = post('username');
    if (!$id && !$data['akun_id'] && $userBaru !== '') {
        $pw = $_POST['password'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $userBaru) || strlen($pw) < 8) {
            pesan('Username 3-30 karakter (huruf/angka) dan password minimal 8 karakter.', 'gagal');
            pindah('dosen.php');
        }
        if (ambil_satu('SELECT id FROM akun_pengguna WHERE username = ?', [$userBaru]) || ambil_satu('SELECT id FROM admin WHERE username = ?', [$userBaru])) {
            pesan('Username sudah dipakai.', 'gagal');
            pindah('dosen.php');
        }
        jalankan("INSERT INTO akun_pengguna (username, nama, password, role, prodi, status) VALUES (?, ?, ?, 'dosen', ?, 'Aktif')", [$userBaru, $data['nama'], password_hash($pw, PASSWORD_DEFAULT), $data['prodi']]);
        $data['akun_id'] = (int) ambil_satu('SELECT id FROM akun_pengguna WHERE username = ?', [$userBaru])['id'];
        catat_log('akun', 'buat akun dosen', $userBaru);
    }
    if ($data['akun_id'] && !ambil_satu("SELECT id FROM akun_pengguna WHERE id = ? AND role = 'dosen'", [$data['akun_id']])) {
        pesan('Akun yang dipilih bukan akun berperan Dosen.', 'gagal');
        pindah('dosen.php');
    }
    if ($data['akun_id'] && ambil_satu('SELECT id FROM dosen WHERE akun_id = ? AND id <> ?', [$data['akun_id'], $id])) {
        pesan('Akun itu sudah terhubung ke dosen lain.', 'gagal');
        pindah('dosen.php');
    }
    if (ambil_satu('SELECT id FROM dosen WHERE nip = ? AND id <> ?', [$data['nip'], $id])) {
        pesan('NIP sudah dipakai dosen lain.', 'gagal');
        pindah('dosen.php');
    }
    if ($id) {
        $lama = ambil_satu('SELECT * FROM dosen WHERE id = ?', [$id]);
        jalankan('UPDATE dosen SET nip = ?, nama = ?, prodi = ?, email = ?, no_hp = ?, status = ?, akun_id = ? WHERE id = ?', array_merge(array_values($data), [$id]));
        catat_log('dosen', 'ubah dosen', beda($lama, $data));
        pesan('Data dosen diperbarui.');
    } else {
        jalankan('INSERT INTO dosen (nip, nama, prodi, email, no_hp, status, akun_id) VALUES (?, ?, ?, ?, ?, ?, ?)', array_values($data));
        catat_log('dosen', 'tambah dosen', $data['nip'] . ' ' . $data['nama']);
        pesan('Dosen ditambahkan.');
    }
    pindah('dosen.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM dosen WHERE id = ?', [(int) get('edit')]) : null;

if ($role === 'dosen') {
    $daftar = ambil_semua('SELECT * FROM dosen WHERE id = ?', [$user['dosen_id'] ?? 0]);
} elseif ($bp !== null) {
    $daftar = ambil_semua('SELECT * FROM dosen WHERE prodi = ? ORDER BY nama', [$bp]);
} else {
    $daftar = ambil_semua('SELECT * FROM dosen ORDER BY nama');
}
$akunBebas = [];
foreach (ambil_semua("SELECT a.id, a.username, a.nama FROM akun_pengguna a WHERE a.role = 'dosen' AND (NOT EXISTS (SELECT 1 FROM dosen d WHERE d.akun_id = a.id) OR a.id = ?) ORDER BY a.nama", [$edit['akun_id'] ?? 0]) as $a) {
    $akunBebas[$a['id']] = $a['nama'] . ' (' . $a['username'] . ')';
}

$judul = 'Data Dosen';
include '../includes/header.php';
?>
<h1>Data Dosen</h1>
<?php if (bisa('C') || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah data dosen' : 'Tambah dosen' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="nip">NIP / NIDN</label><input type="text" id="nip" name="nip" value="<?= e($edit['nip'] ?? '') ?>" required></div>
        <div><label for="nama">Nama lengkap</label><input type="text" id="nama" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></div>
        <div><label for="prodi">Program studi</label><select id="prodi" name="prodi"><option value="">-</option><?= opsi(sama(PRODI), $edit['prodi'] ?? '') ?></select></div>
        <div><label for="status">Status</label><select id="status" name="status"><?= opsi(sama(['Aktif', 'Non-Aktif']), $edit['status'] ?? 'Aktif') ?></select></div>
        <div><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
        <div><label for="no_hp">No. HP</label><input type="text" id="no_hp" name="no_hp" value="<?= e($edit['no_hp'] ?? '') ?>"></div>
        <div class="penuh"><label for="akun_id">Atau pilih akun dosen yang sudah ada</label><select id="akun_id" name="akun_id"><option value="">Belum dihubungkan</option><?= opsi($akunBebas, $edit['akun_id'] ?? '') ?></select></div>
        <?php if (!$edit): ?>
        <div><label for="username">Username login (isi untuk membuat akun baru)</label><input type="text" id="username" name="username" autocomplete="off"></div>
        <div><label for="password">Password (min. 8 karakter)</label><input type="password" id="password" name="password" autocomplete="new-password"></div>
        <?php endif; ?>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah dosen' ?></button><?php if ($edit): ?><a href="dosen.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar dosen (<?= count($daftar) ?>)<?= $bp !== null ? ' - prodi ' . e($bp) : '' ?></h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada data dosen.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>NIP</th><th>Nama</th><th>Prodi</th><th>Email</th><th>No. HP</th><th>Status</th><?php if (bisa('U') || bisa('D')): ?><th></th><?php endif; ?></tr>
        <?php foreach ($daftar as $d): ?><tr><td><?= e($d['nip']) ?></td><td><?= e($d['nama']) ?></td><td><?= e($d['prodi'] ?: '-') ?></td><td><?= e($d['email'] ?: '-') ?></td><td><?= e($d['no_hp'] ?: '-') ?></td><td><?= lencana($d['status']) ?></td>
        <?php if (bisa('U') || bisa('D')): ?><td><div class="aksi">
            <?php if (bisa('U')): ?><a href="?edit=<?= $d['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
            <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus data dosen ini?')"><input type="hidden" name="hapus" value="<?= $d['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
        </div></td><?php endif; ?></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
