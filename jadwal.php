<?php
require_once '../koneksi.php';
$user = wajib_portal('jadwal');
$role = $user['role'];
$bp = prodi_batas();
$kodeProdi = $bp !== null ? mk_prodi($bp) : null; // mata kuliah yang boleh diputuskan Kaprodi
$taAktif = tahun_aktif();

function kelas_dalam_cakupan($k)
{
    global $kodeProdi;
    return $kodeProdi === null || ($k && in_array($k['kode_mk'], $kodeProdi, true));
}

function bentrok($data, $selfId)
{
    $baris = ambil_semua("SELECT k.*, m.nama_mk FROM kelas_kuliah k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk
        WHERE k.tahun_akademik = ? AND k.hari = ? AND k.status <> 'Ditolak' AND k.id <> ? AND k.jam_mulai < ? AND k.jam_selesai > ?",
        [$data['tahun_akademik'], $data['hari'], $selfId, $data['jam_selesai'], $data['jam_mulai']]);
    foreach ($baris as $b) {
        if ($b['ruang'] === $data['ruang']) return 'Ruang ' . $b['ruang'] . ' sudah dipakai ' . $b['nama_mk'] . ' pada jam tersebut.';
        if ($data['dosen_id'] && (int) $b['dosen_id'] === (int) $data['dosen_id']) return 'Dosen yang sama sudah mengajar ' . $b['nama_mk'] . ' pada jam tersebut.';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');
    $k = $id ? ambil_satu('SELECT * FROM kelas_kuliah WHERE id = ?', [$id]) : null;

    if ($aksi === 'hapus') {
        if (!bisa('D') || !$k) { pesan('Anda tidak berhak menghapus jadwal.', 'gagal'); pindah('jadwal.php'); }
        jalankan('DELETE FROM kelas_kuliah WHERE id = ?', [$id]);
        catat_log('jadwal', 'hapus jadwal', $k['kode_mk'] . ' ' . $k['hari'] . ' ' . $k['jam_mulai']);
        pesan('Jadwal dihapus.');
        pindah('jadwal.php');
    }

    if ($aksi === 'setujui' || $aksi === 'tolak') {
        if (!bisa('A') || !$k || !kelas_dalam_cakupan($k)) {
            pesan('Anda tidak berhak memutuskan jadwal ini.', 'gagal');
        } elseif ($aksi === 'setujui' && empty($k['dosen_id'])) {
            pesan('Tetapkan dosen pengampu sebelum menyetujui jadwal.', 'gagal');
        } elseif ($aksi === 'tolak' && post('catatan') === '') {
            pesan('Tuliskan alasan penolakan pada kolom catatan.', 'gagal');
        } else {
            $baru = $aksi === 'setujui' ? 'Disetujui' : 'Ditolak';
            jalankan('UPDATE kelas_kuliah SET status = ?, catatan = ?, diubah_oleh = ? WHERE id = ?', [$baru, kosong_ke_null(post('catatan')), $user['username'], $id]);
            catat_log('jadwal', $aksi . ' jadwal', "id $id {$k['kode_mk']}: {$k['status']} -> $baru");
            pesan('Jadwal ' . strtolower($baru) . '.');
        }
        pindah('jadwal.php');
    }

    if ($aksi === 'pengampu') {
        $dosenId = (int) post('dosen_id') ?: null;
        if (!bisa('A') || !$k || !kelas_dalam_cakupan($k) || ($dosenId && !ambil_satu("SELECT id FROM dosen WHERE id = ? AND status = 'Aktif'", [$dosenId]))) {
            pesan('Penugasan dosen pengampu tidak dapat dilakukan.', 'gagal');
        } else {
            $data = $k;
            $data['dosen_id'] = $dosenId;
            if ($dosenId && ($msg = bentrok($data, $id))) {
                pesan($msg, 'gagal');
            } else {
                jalankan('UPDATE kelas_kuliah SET dosen_id = ?, diubah_oleh = ? WHERE id = ?', [$dosenId, $user['username'], $id]);
                catat_log('jadwal', 'tetapkan dosen pengampu', "id $id {$k['kode_mk']}: dosen " . ($k['dosen_id'] ?? '-') . ' -> ' . ($dosenId ?? '-'));
                pesan('Dosen pengampu ditetapkan.');
            }
        }
        pindah('jadwal.php');
    }

    // simpan (tambah / ubah) oleh Staff dan Admin
    $data = ['kode_mk' => post('kode_mk'), 'dosen_id' => (int) post('dosen_id') ?: null, 'tahun_akademik' => post('tahun_akademik'),
             'hari' => post('hari'), 'jam_mulai' => post('jam_mulai'), 'jam_selesai' => post('jam_selesai'), 'ruang' => post('ruang')];
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak menyimpan jadwal.', 'gagal'); pindah('jadwal.php'); }
    $taValid = array_map(fn($t) => $t['nama'] . ' ' . $t['semester'], ambil_semua('SELECT nama, semester FROM tahun_akademik'));
    if (!ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$data['kode_mk']]) || !in_array($data['tahun_akademik'], $taValid, true)
        || !in_array($data['hari'], HARI_KULIAH, true) || !jam_valid($data['jam_mulai']) || !jam_valid($data['jam_selesai'])
        || $data['jam_selesai'] <= $data['jam_mulai'] || $data['ruang'] === ''
        || ($data['dosen_id'] && !ambil_satu("SELECT id FROM dosen WHERE id = ? AND status = 'Aktif'", [$data['dosen_id']]))) {
        pesan('Mata kuliah, tahun akademik, hari, jam (selesai harus setelah mulai), ruang, dan dosen harus valid.', 'gagal');
        pindah('jadwal.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($msg = bentrok($data, $id)) {
        pesan('Jadwal bentrok: ' . $msg, 'gagal');
        pindah('jadwal.php' . ($id ? '?edit=' . $id : ''));
    }
    $status = $role === 'admin' ? 'Disetujui' : 'Diajukan';
    if ($id) {
        if (!$k) { pindah('jadwal.php'); }
        jalankan('UPDATE kelas_kuliah SET kode_mk = ?, dosen_id = ?, tahun_akademik = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, ruang = ?, status = ?, diubah_oleh = ? WHERE id = ?',
                 array_merge(array_values($data), [$status, $user['username'], $id]));
        catat_log('jadwal', 'ubah jadwal', "id $id: " . beda($k, $data));
        pesan($role === 'admin' ? 'Jadwal diperbarui.' : 'Jadwal diperbarui dan diajukan ulang ke Kaprodi.');
    } else {
        jalankan('INSERT INTO kelas_kuliah (kode_mk, dosen_id, tahun_akademik, hari, jam_mulai, jam_selesai, ruang, status, diubah_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                 array_merge(array_values($data), [$status, $user['username']]));
        catat_log('jadwal', 'tambah jadwal', $data['kode_mk'] . ' ' . $data['hari'] . ' ' . $data['jam_mulai']);
        pesan($role === 'admin' ? 'Jadwal ditambahkan.' : 'Jadwal ditambahkan dan menunggu persetujuan Kaprodi.');
    }
    pindah('jadwal.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM kelas_kuliah WHERE id = ?', [(int) get('edit')]) : null;
$fTa = get('ta') !== '' ? get('ta') : $taAktif;
$fStatus = get('status');
$syarat = "(? = '' OR k.tahun_akademik = ?) AND (? = '' OR k.status = ?)";
$param = [$fTa === 'semua' ? '' : $fTa, $fTa === 'semua' ? '' : $fTa, $fStatus, $fStatus];
if ($role === 'dosen') {
    $syarat .= " AND k.status = 'Disetujui'"; // dosen melihat jadwal resmi
}
$daftar = ambil_semua("SELECT k.*, m.nama_mk, m.sks, d.nama AS nama_dosen FROM kelas_kuliah k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk LEFT JOIN dosen d ON d.id = k.dosen_id
    WHERE $syarat ORDER BY k.tahun_akademik DESC, CASE k.hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 ELSE 6 END, k.jam_mulai", $param);
$daftarTa = ambil_semua('SELECT nama, semester FROM tahun_akademik ORDER BY nama DESC, semester DESC');
$opsiTa = [];
foreach ($daftarTa as $t) { $opsiTa[$t['nama'] . ' ' . $t['semester']] = $t['nama'] . ' ' . $t['semester']; }
$pilihanDosen = daftar_dosen();
$formTulis = bisa('C') || $edit;

$judul = 'Jadwal Kuliah';
include '../includes/header.php';
?>
<h1>Jadwal Kuliah</h1>
<?php if ($formTulis): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah jadwal' : 'Tambah jadwal' ?></h2>
    <?php if ($role === 'staff'): ?><p class="keterangan">Jadwal yang Anda simpan berstatus "Diajukan" dan baru berlaku setelah disetujui Kaprodi.</p><?php endif; ?>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="kode_mk">Mata kuliah</label><select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul(), $edit['kode_mk'] ?? '') ?></select></div>
        <div><label for="dosen_id">Dosen pengampu</label><select id="dosen_id" name="dosen_id"><option value="">Belum ditentukan</option><?= opsi($pilihanDosen, $edit['dosen_id'] ?? '') ?></select></div>
        <div><label for="tahun_akademik">Tahun akademik</label><select id="tahun_akademik" name="tahun_akademik"><?= opsi($opsiTa, $edit['tahun_akademik'] ?? $taAktif) ?></select></div>
        <div><label for="hari">Hari</label><select id="hari" name="hari"><?= opsi(sama(HARI_KULIAH), $edit['hari'] ?? 'Senin') ?></select></div>
        <div><label for="jam_mulai">Jam mulai</label><input type="time" id="jam_mulai" name="jam_mulai" value="<?= e($edit['jam_mulai'] ?? '08:00') ?>" required></div>
        <div><label for="jam_selesai">Jam selesai</label><input type="time" id="jam_selesai" name="jam_selesai" value="<?= e($edit['jam_selesai'] ?? '10:00') ?>" required></div>
        <div class="penuh"><label for="ruang">Ruang</label><input type="text" id="ruang" name="ruang" value="<?= e($edit['ruang'] ?? '') ?>" required></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah jadwal' ?></button><?php if ($edit): ?><a href="jadwal.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar jadwal</h2>
    <form method="get" class="filter">
        <div><label for="ta">Tahun akademik</label><select id="ta" name="ta"><option value="semua">Semua</option><?= opsi($opsiTa, $fTa) ?></select></div>
        <?php if ($role !== 'dosen'): ?><div><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><?= opsi(sama(['Diajukan', 'Disetujui', 'Ditolak']), $fStatus) ?></select></div><?php endif; ?>
        <button type="submit" class="tombol abu">Saring</button>
    </form>
    <?php if (!$daftar): ?><p class="kosong">Belum ada jadwal.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>Hari</th><th>Jam</th><th>Mata kuliah</th><th>Dosen</th><th>Ruang</th><th>Tahun akademik</th><th>Status</th><th></th></tr>
        <?php foreach ($daftar as $k): $saya = $role === 'dosen' && (int) $k['dosen_id'] === (int) ($user['dosen_id'] ?? 0); $diCakupan = kelas_dalam_cakupan($k); ?>
        <tr>
            <td><?= e($k['hari']) ?></td><td><?= e($k['jam_mulai']) ?>-<?= e($k['jam_selesai']) ?></td>
            <td><?= e($k['kode_mk']) ?> - <?= e($k['nama_mk']) ?> (<?= (int) $k['sks'] ?> SKS)</td>
            <td><?php if (bisa('A') && $role === 'kaprodi' && $diCakupan): ?>
                <form method="post" class="aksi"><input type="hidden" name="aksi" value="pengampu"><input type="hidden" name="id" value="<?= $k['id'] ?>"><select name="dosen_id"><option value="">-</option><?= opsi(daftar_dosen($bp ?? ''), $k['dosen_id'] ?? '') ?></select><button type="submit" class="tombol kecil">Tetapkan</button></form>
                <?php else: ?><?= e($k['nama_dosen'] ?: '-') ?><?= $saya ? ' <strong>(saya)</strong>' : '' ?><?php endif; ?></td>
            <td><?= e($k['ruang']) ?></td><td><?= e($k['tahun_akademik']) ?></td>
            <td><?= lencana($k['status']) ?><?php if ($k['catatan']): ?><br><span class="keterangan"><?= e($k['catatan']) ?></span><?php endif; ?></td>
            <td><div class="aksi">
                <?php if (bisa('U')): ?><a href="?edit=<?= $k['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
                <?php if (bisa('A') && $diCakupan && $k['status'] === 'Diajukan'): ?>
                    <form method="post" class="aksi"><input type="hidden" name="id" value="<?= $k['id'] ?>"><input type="text" name="catatan" placeholder="Catatan (wajib bila ditolak)">
                        <button type="submit" name="aksi" value="setujui" class="tombol hijau kecil">Setujui</button><button type="submit" name="aksi" value="tolak" class="tombol merah kecil">Tolak</button></form>
                <?php endif; ?>
                <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus jadwal ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
            </div></td>
        </tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
