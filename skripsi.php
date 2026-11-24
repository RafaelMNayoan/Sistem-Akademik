<?php
require_once '../koneksi.php';
$user = wajib_portal('skripsi');
$role = $user['role'];
$bp = prodi_batas();
$did = (int) ($user['dosen_id'] ?? 0);

function skripsi_dalam_cakupan($s)
{
    global $role, $bp, $did;
    if (!$s) return false;
    if ($role === 'kaprodi') return $s['program_studi'] === $bp;
    if ($role === 'dosen') return $did > 0 && (int) $s['pembimbing_id'] === $did;
    return true;
}
function ambil_skripsi($id)
{
    return ambil_satu('SELECT s.*, mh.nama_lengkap, mh.program_studi FROM skripsi s JOIN mahasiswa mh ON mh.nim = s.nim WHERE s.id = ?', [$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');
    $s = ambil_skripsi($id);
    $balik = 'skripsi.php?lihat=' . $id;
    if (!skripsi_dalam_cakupan($s)) { pesan('Anda tidak berhak memproses skripsi ini.', 'gagal'); pindah('skripsi.php'); }

    if ($aksi === 'setujui' || $aksi === 'tolak') {
        $pemb = (int) post('pembimbing_id');
        if (!bisa('A') || !in_array($role, ['kaprodi', 'admin'], true)) {
            pesan('Hanya Kaprodi yang menyetujui judul dan pembimbing.', 'gagal');
        } elseif ($aksi === 'setujui' && !ambil_satu("SELECT id FROM dosen WHERE id = ? AND status = 'Aktif'", [$pemb])) {
            pesan('Pilih dosen pembimbing.', 'gagal');
        } elseif ($aksi === 'tolak' && post('catatan') === '') {
            pesan('Tuliskan alasan penolakan.', 'gagal');
        } else {
            $baru = $aksi === 'setujui' ? 'Disetujui' : 'Ditolak';
            jalankan('UPDATE skripsi SET status = ?, pembimbing_id = ?, catatan_kaprodi = ? WHERE id = ?', [$baru, $aksi === 'setujui' ? $pemb : null, kosong_ke_null(post('catatan')), $id]);
            catat_log('skripsi', $aksi . ' judul', "id $id nim {$s['nim']}: {$s['status']} -> $baru, pembimbing " . ($aksi === 'setujui' ? $pemb : '-'));
            pesan('Judul skripsi ' . strtolower($baru) . '.');
        }
        pindah($balik);
    }
    $milikDosen = $role === 'dosen' && $did > 0 && (int) $s['pembimbing_id'] === $did;
    if ($aksi === 'tanggapan') {
        if (!bisa('U') || !$milikDosen || !in_array($s['status'], ['Disetujui', 'Layak Sidang'], true) || post('isi') === '') {
            pesan('Catatan bimbingan tidak dapat disimpan.', 'gagal');
        } else {
            jalankan('INSERT INTO bimbingan_skripsi (skripsi_id, waktu, isi, oleh, peran) VALUES (?, ?, ?, ?, ?)', [$id, date('Y-m-d H:i:s'), post('isi'), $user['nama'], 'dosen']);
            catat_log('skripsi', 'catatan bimbingan', "id $id nim {$s['nim']}");
            pesan('Catatan bimbingan disimpan.');
        }
        pindah($balik);
    }
    if ($aksi === 'layak') {
        if (!bisa('A') || !$milikDosen || $s['status'] !== 'Disetujui') {
            pesan('Persetujuan tidak dapat diberikan.', 'gagal');
        } else {
            jalankan("UPDATE skripsi SET status = 'Layak Sidang' WHERE id = ?", [$id]);
            catat_log('skripsi', 'setujui layak sidang', "id $id nim {$s['nim']}");
            pesan('Skripsi dinyatakan layak sidang.');
        }
        pindah($balik);
    }
    if ($aksi === 'nilai') {
        if (!bisa('U') || !$milikDosen || $s['status'] !== 'Layak Sidang' || !in_array(post('nilai_huruf'), GRADE, true)) {
            pesan('Penilaian hanya dapat diberikan pada skripsi berstatus Layak Sidang.', 'gagal');
        } else {
            jalankan("UPDATE skripsi SET nilai_huruf = ?, status = 'Selesai' WHERE id = ?", [post('nilai_huruf'), $id]);
            catat_log('skripsi', 'nilai skripsi', "id $id nim {$s['nim']}: " . post('nilai_huruf'));
            pesan('Nilai skripsi disimpan.');
        }
        pindah($balik);
    }
    pindah('skripsi.php');
}

$syarat = '1 = 1'; $param = [];
if ($role === 'kaprodi') { $syarat = 'mh.program_studi = ?'; $param = [$bp]; }
if ($role === 'dosen') { $syarat = 's.pembimbing_id = ?'; $param = [$did]; }
$daftar = ambil_semua("SELECT s.*, mh.nama_lengkap, mh.program_studi, d.nama AS pembimbing FROM skripsi s JOIN mahasiswa mh ON mh.nim = s.nim LEFT JOIN dosen d ON d.id = s.pembimbing_id WHERE $syarat ORDER BY s.diajukan DESC", $param);
$lihat = get('lihat') !== '' ? ambil_skripsi((int) get('lihat')) : null;
if ($lihat && !skripsi_dalam_cakupan($lihat)) { $lihat = null; }
$log = $lihat ? ambil_semua('SELECT * FROM bimbingan_skripsi WHERE skripsi_id = ? ORDER BY waktu', [$lihat['id']]) : [];
$milikDosen = $lihat && $role === 'dosen' && (int) $lihat['pembimbing_id'] === $did;

$judul = 'Skripsi / Tugas Akhir';
include '../includes/header.php';
?>
<h1>Skripsi / Tugas Akhir</h1>
<?php if ($lihat): ?>
<div class="kartu">
    <h2><?= e($lihat['judul']) ?></h2>
    <p><a href="skripsi.php" class="tombol abu kecil">Kembali</a></p>
    <dl class="daftar-info">
        <div><dt>Mahasiswa</dt><dd><?= e($lihat['nim']) ?> - <?= e($lihat['nama_lengkap']) ?> (<?= e($lihat['program_studi']) ?>)</dd></div>
        <div><dt>Status</dt><dd><?= lencana($lihat['status']) ?></dd></div>
        <div><dt>Pembimbing</dt><dd><?= e($lihat['pembimbing_id'] ? (daftar_dosen()[$lihat['pembimbing_id']] ?? '-') : '-') ?></dd></div>
        <div><dt>Nilai</dt><dd><?= e($lihat['nilai_huruf'] ?: '-') ?></dd></div>
        <div><dt>Abstrak</dt><dd><?= nl2br(e($lihat['abstrak'] ?: '-')) ?></dd></div>
        <?php if ($lihat['catatan_kaprodi']): ?><div><dt>Catatan Kaprodi</dt><dd><?= e($lihat['catatan_kaprodi']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if (bisa('A') && in_array($role, ['kaprodi', 'admin'], true) && $lihat['status'] === 'Diajukan'): ?>
    <h3 style="margin-top:14px">Putusan Kaprodi</h3>
    <form method="post" class="form-isi"><input type="hidden" name="id" value="<?= $lihat['id'] ?>">
        <div><label for="pembimbing_id">Dosen pembimbing</label><select id="pembimbing_id" name="pembimbing_id"><option value="">Pilih dosen</option><?= opsi(daftar_dosen($bp ?? ''), $lihat['pembimbing_id'] ?? '') ?></select></div>
        <div><label for="catatan">Catatan (wajib bila ditolak)</label><input type="text" id="catatan" name="catatan"></div>
        <div class="penuh baris-tombol"><button type="submit" name="aksi" value="setujui" class="tombol hijau">Setujui judul &amp; pembimbing</button><button type="submit" name="aksi" value="tolak" class="tombol merah">Tolak</button></div>
    </form><?php endif; ?>
</div>
<div class="kartu"><h2>Catatan bimbingan</h2>
    <?php if (!$log): ?><p class="kosong">Belum ada catatan bimbingan.</p><?php endif; ?>
    <?php foreach ($log as $l): ?><div class="item"><p class="keterangan"><?= tanggal_jam($l['waktu']) ?> - <?= e($l['oleh']) ?> (<?= e($l['peran']) ?>)</p><p><?= nl2br(e($l['isi'])) ?></p></div><?php endforeach; ?>
    <?php if ($milikDosen && bisa('U') && in_array($lihat['status'], ['Disetujui', 'Layak Sidang'], true)): ?>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="tanggapan"><input type="hidden" name="id" value="<?= $lihat['id'] ?>">
        <div class="penuh"><label for="isi">Tambah catatan / tanggapan bimbingan</label><textarea id="isi" name="isi" rows="3" required></textarea></div>
        <div class="penuh"><button type="submit" class="tombol">Simpan catatan</button></div></form>
    <?php endif; ?>
    <?php if ($milikDosen && bisa('A') && $lihat['status'] === 'Disetujui'): ?>
    <form method="post" style="margin-top:10px"><input type="hidden" name="aksi" value="layak"><input type="hidden" name="id" value="<?= $lihat['id'] ?>"><button type="submit" class="tombol hijau" onclick="return confirm('Nyatakan skripsi ini layak sidang?')">Setujui layak sidang</button></form><?php endif; ?>
    <?php if ($milikDosen && bisa('U') && $lihat['status'] === 'Layak Sidang'): ?>
    <form method="post" class="filter" style="margin-top:10px"><input type="hidden" name="aksi" value="nilai"><input type="hidden" name="id" value="<?= $lihat['id'] ?>">
        <div><label for="nilai_huruf">Nilai akhir skripsi</label><select id="nilai_huruf" name="nilai_huruf"><?= opsi(sama(GRADE), 'A') ?></select></div><button type="submit" class="tombol">Simpan nilai</button></form><?php endif; ?>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar skripsi<?= $role === 'dosen' ? ' bimbingan saya' : ($bp !== null ? ' prodi ' . e($bp) : '') ?></h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada pengajuan skripsi.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Mahasiswa</th><th>Judul</th><th>Pembimbing</th><th>Status</th><th></th></tr>
        <?php foreach ($daftar as $s): ?><tr><td><?= e($s['nim']) ?> - <?= e($s['nama_lengkap']) ?></td><td><?= e($s['judul']) ?></td><td><?= e($s['pembimbing'] ?: '-') ?></td><td><?= lencana($s['status']) ?></td><td><a href="?lihat=<?= $s['id'] ?>" class="tombol kecil">Buka</a></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
