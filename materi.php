<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();

$matkul = mk_diambil($user['nim'], $user['semester']);
$kodeKrs = array_column($matkul, 'kode_mk');

$filter = get('filter');
$daftar = [];

if ($kodeKrs) {
    $tanda = implode(',', array_fill(0, count($kodeKrs), '?'));
    $sql = "SELECT t.*, m.nama_mk FROM materi_kuliah t
            JOIN mata_kuliah m ON m.kode_mk = t.kode_mk
            WHERE t.kode_mk IN ($tanda)";
    $param = $kodeKrs;
    if ($filter !== '') {
        $sql .= ' AND t.kode_mk = ?';
        $param[] = $filter;
    }
    $daftar = ambil_semua($sql . ' ORDER BY t.id DESC', $param);
}

$judul = 'Materi Kuliah';
include '../includes/header.php';
?>
<h1>Materi Kuliah</h1>

<div class="kartu">
    <form method="get" class="filter">
        <div>
            <label for="filter">Saring mata kuliah</label>
            <select id="filter" name="filter">
                <option value="">Semua</option>
                <?php foreach ($matkul as $mk): ?>
                    <option value="<?= e($mk['kode_mk']) ?>" <?= $filter === $mk['kode_mk'] ? 'selected' : '' ?>><?= e($mk['nama_mk']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>
</div>

<div class="kartu">
    <?php if (!$matkul): ?>
        <p class="kosong">Belum ada mata kuliah di KRS. <a href="krs.php">Isi KRS dulu</a>.</p>
    <?php elseif (!$daftar): ?>
        <p class="kosong">Belum ada materi dari admin.</p>
    <?php else: ?>
        <?php foreach ($daftar as $m): ?>
            <div class="item">
                <h3><?= e($m['judul_materi']) ?></h3>
                <p class="keterangan"><?= e($m['nama_mk']) ?> - diunggah <?= tanggal($m['tanggal_upload']) ?></p>
                <?php if ($m['deskripsi']): ?><p><?= nl2br(e($m['deskripsi'])) ?></p><?php endif; ?>
                <?php if ($m['file_url']): ?>
                    <div class="aksi"><a href="../<?= e($m['file_url']) ?>" target="_blank" class="tombol kecil">Buka file</a></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
