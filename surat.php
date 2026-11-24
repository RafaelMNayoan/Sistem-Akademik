<?php
require_once '../koneksi.php';
$user = wajib_portal('surat');
$role = $user['role'];
$bp = prodi_batas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');
    $s = $id ? ambil_satu('SELECT * FROM surat WHERE id = ?', [$id]) : null;
    $dalam = $s && ($bp === null || $s['prodi'] === $bp) && ($role !== 'dosen' || ($s['pemohon_tipe'] === 'dosen' && $s['pemohon_ref'] === (string) $user['id']));

    if ($aksi === 'buat') {
        if (!bisa('C')) { pesan('Anda tidak berhak membuat surat.', 'gagal'); pindah('surat.php'); }
        $keperluan = post('keperluan');
        if ($role === 'dosen') {
            $d = ambil_satu('SELECT prodi FROM dosen WHERE id = ?', [$user['dosen_id'] ?? 0]);
            $tipe = 'dosen'; $ref = (string) $user['id']; $nama = $user['nama']; $prodi = $d['prodi'] ?? null;
            $jenisOk = in_array(post('jenis'), JENIS_SURAT_DOSEN, true);
        } else {
            $m = ambil_satu('SELECT nim, nama_lengkap, program_studi FROM mahasiswa WHERE nim = ?', [post('nim')]);
            $tipe = 'mahasiswa'; $ref = $m['nim'] ?? ''; $nama = $m['nama_lengkap'] ?? ''; $prodi = $m['program_studi'] ?? null;
            $jenisOk = $m && in_array(post('jenis'), JENIS_SURAT_MHS, true);
        }
        if (!$jenisOk || $keperluan === '') {
            pesan('Pilih jenis surat dan mahasiswa, lalu isi keperluan.', 'gagal');
        } else {
            jalankan('INSERT INTO surat (jenis, pemohon_tipe, pemohon_ref, pemohon_nama, prodi, keperluan, status, dibuat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                     [post('jenis'), $tipe, $ref, $nama, $prodi, $keperluan, 'Diajukan', date('Y-m-d H:i:s')]);
            catat_log('surat', 'buat surat', post('jenis') . " untuk $nama");
            pesan('Surat diajukan.');
        }
        pindah('surat.php');
    }
    if (!$dalam) { pesan('Anda tidak berhak memproses surat ini.', 'gagal'); pindah('surat.php'); }

    if ($aksi === 'proses') {
        if (!bisa('U') || $s['status'] !== 'Diajukan' || post('nomor_surat') === '') {
            pesan('Isi nomor surat untuk memproses surat yang berstatus Diajukan.', 'gagal');
        } else {
            jalankan("UPDATE surat SET status = 'Diproses', nomor_surat = ?, catatan = ?, diproses_oleh = ? WHERE id = ?", [post('nomor_surat'), kosong_ke_null(post('catatan')), $user['nama'], $id]);
            catat_log('surat', 'proses surat', "id $id nomor " . post('nomor_surat'));
            pesan('Surat diproses dan menunggu persetujuan Kaprodi.');
        }
    } elseif ($aksi === 'selesai') {
        if (!bisa('U') || $s['status'] !== 'Disetujui') {
            pesan('Hanya surat yang sudah disetujui yang bisa diselesaikan.', 'gagal');
        } else {
            jalankan("UPDATE surat SET status = 'Selesai' WHERE id = ?", [$id]);
            catat_log('surat', 'selesaikan surat', "id $id");
            pesan('Surat ditandai selesai.');
        }
    } elseif ($aksi === 'setujui' || $aksi === 'tolak') {
        if (!bisa('A') || !in_array($s['status'], ['Diajukan', 'Diproses'], true)) {
            pesan('Surat ini tidak dapat diputuskan.', 'gagal');
        } elseif ($aksi === 'tolak' && post('catatan') === '') {
            pesan('Tuliskan alasan penolakan.', 'gagal');
        } elseif ($aksi === 'setujui' && empty($s['nomor_surat']) && $role !== 'admin') {
            pesan('Surat harus diproses Staff (diberi nomor) sebelum disetujui.', 'gagal');
        } else {
            $baru = $aksi === 'setujui' ? 'Disetujui' : 'Ditolak';
            jalankan('UPDATE surat SET status = ?, catatan = ?, disetujui_oleh = ? WHERE id = ?', [$baru, kosong_ke_null(post('catatan')), $user['nama'], $id]);
            catat_log('surat', $aksi . ' surat', "id $id: {$s['status']} -> $baru");
            pesan('Surat ' . strtolower($baru) . '.');
        }
    } elseif ($aksi === 'hapus' && bisa('D')) {
        jalankan('DELETE FROM surat WHERE id = ?', [$id]);
        catat_log('surat', 'hapus surat', "id $id {$s['jenis']}");
        pesan('Surat dihapus.');
    }
    pindah('surat.php');
}

$syarat = '1 = 1'; $param = [];
if ($bp !== null) { $syarat = 'prodi = ?'; $param = [$bp]; }
if ($role === 'dosen') { $syarat = "pemohon_tipe = 'dosen' AND pemohon_ref = ?"; $param = [(string) $user['id']]; }
$daftar = ambil_semua("SELECT * FROM surat WHERE $syarat ORDER BY dibuat DESC, id DESC LIMIT 200", $param);

$judul = 'Surat & Dokumen';
include '../includes/header.php';
?>
<h1>Surat & Dokumen</h1>
<?php if (bisa('C')): ?>
<div class="kartu">
    <h2><?= $role === 'dosen' ? 'Ajukan surat' : 'Buat surat untuk mahasiswa' ?></h2>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="buat">
        <?php if ($role !== 'dosen'): ?><div><label for="nim">Mahasiswa</label><select id="nim" name="nim" required><?= opsi(daftar_mahasiswa()) ?></select></div><?php endif; ?>
        <div><label for="jenis">Jenis surat</label><select id="jenis" name="jenis"><?= opsi(sama($role === 'dosen' ? JENIS_SURAT_DOSEN : JENIS_SURAT_MHS)) ?></select></div>
        <div class="penuh"><label for="keperluan">Keperluan</label><textarea id="keperluan" name="keperluan" rows="2" required></textarea></div>
        <div class="penuh"><button type="submit" class="tombol">Ajukan</button></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar surat<?= $bp !== null ? ' prodi ' . e($bp) : '' ?></h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada surat.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Tanggal</th><th>Pemohon</th><th>Jenis</th><th>Keperluan</th><th>Nomor</th><th>Status</th><th></th></tr>
    <?php foreach ($daftar as $s): ?><tr><td><?= tanggal($s['dibuat']) ?></td><td><?= e($s['pemohon_nama']) ?><br><span class="keterangan"><?= e($s['pemohon_tipe'] === 'mahasiswa' ? $s['pemohon_ref'] : 'Dosen') ?></span></td><td><?= e($s['jenis']) ?></td><td><?= e($s['keperluan']) ?></td><td><?= e($s['nomor_surat'] ?: '-') ?></td>
        <td><?= lencana($s['status']) ?><?php if ($s['catatan']): ?><br><span class="keterangan"><?= e($s['catatan']) ?></span><?php endif; ?></td>
        <td><div class="aksi">
            <?php if (bisa('U') && $s['status'] === 'Diajukan' && $role !== 'admin' || ($role === 'admin' && $s['status'] === 'Diajukan')): ?>
                <form method="post" class="aksi"><input type="hidden" name="aksi" value="proses"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="text" name="nomor_surat" placeholder="Nomor surat" required><button type="submit" class="tombol kecil">Proses</button></form>
            <?php endif; ?>
            <?php if (bisa('A') && in_array($s['status'], ['Diajukan', 'Diproses'], true) && ($s['status'] === 'Diproses' || $role === 'admin')): ?>
                <form method="post" class="aksi"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="text" name="catatan" placeholder="Catatan (wajib bila ditolak)">
                    <button type="submit" name="aksi" value="setujui" class="tombol hijau kecil">Setujui</button><button type="submit" name="aksi" value="tolak" class="tombol merah kecil">Tolak</button></form>
            <?php endif; ?>
            <?php if (bisa('U') && $s['status'] === 'Disetujui'): ?><form method="post"><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" class="tombol hijau kecil">Tandai selesai</button></form><?php endif; ?>
            <?php if (in_array($s['status'], ['Disetujui', 'Selesai'], true)): ?><a href="cetak.php?jenis=surat&amp;id=<?= $s['id'] ?>" target="_blank" class="tombol abu kecil">Cetak</a><?php endif; ?>
            <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus surat ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
        </div></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
