<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$nim = $user['nim'];
$semester = $user['semester'];

$matkul = mk_diambil($nim, $semester);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tugasId = (int) post('tugas_id');

    $tugas = ambil_satu('SELECT t.* FROM tugas_kuliah t
                         JOIN krs k ON k.kode_mk = t.kode_mk AND k.nim = ? AND k.semester = ?
                         WHERE t.id = ?', [$nim, $semester, $tugasId]);
    if (!$tugas) {
        pesan('Tugas tidak ditemukan.', 'gagal');
        pindah('tugas.php');
    }
    if (strtotime($tugas['batas_waktu']) < time()) {
        pesan('Batas pengumpulan sudah lewat. Hubungi admin jika perlu perpanjangan.', 'gagal');
        pindah('tugas.php');
    }

    $file = simpan_upload('file_jawaban');
    $lama = ambil_satu('SELECT id FROM pengumpulan_tugas WHERE tugas_id = ? AND nim = ?', [$tugasId, $nim]);

    if ($file === false) {
        pesan('File gagal diunggah. Format yang boleh: pdf, doc, docx, ppt, pptx, jpg, png, mp4, zip.', 'gagal');
    } elseif ($file === null && !$lama) {
        pesan('Pilih file jawaban terlebih dahulu.', 'gagal');
    } elseif ($lama) {
        jalankan('UPDATE pengumpulan_tugas SET catatan = ?, waktu_kumpul = ? WHERE id = ?',
                 [post('catatan'), date('Y-m-d H:i:s'), $lama['id']]);
        if ($file) {
            jalankan('UPDATE pengumpulan_tugas SET file_jawaban = ? WHERE id = ?', [$file, $lama['id']]);
        }
        pesan('Jawaban diperbarui.');
    } else {
        jalankan('INSERT INTO pengumpulan_tugas (tugas_id, nim, file_jawaban, catatan, waktu_kumpul) VALUES (?, ?, ?, ?, ?)',
                 [$tugasId, $nim, $file, post('catatan'), date('Y-m-d H:i:s')]);
        pesan('Jawaban berhasil dikumpulkan.');
    }
    pindah('tugas.php');
}

$filter = get('filter');
$daftar = ambil_semua("SELECT t.*, m.nama_mk, p.file_jawaban, p.catatan AS catatan_saya, p.waktu_kumpul, p.nilai
        FROM tugas_kuliah t
        JOIN mata_kuliah m ON m.kode_mk = t.kode_mk
        JOIN krs k ON k.kode_mk = t.kode_mk AND k.nim = ? AND k.semester = ?
        LEFT JOIN pengumpulan_tugas p ON p.tugas_id = t.id AND p.nim = ?
        WHERE (? = '' OR t.kode_mk = ?)
        ORDER BY t.batas_waktu", [$nim, $semester, $nim, $filter, $filter]);

$judul = 'Tugas Kuliah';
include '../includes/header.php';
?>
<h1>Tugas Kuliah</h1>

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
        <p class="kosong">Belum ada tugas dari admin.</p>
    <?php else: ?>
        <?php foreach ($daftar as $t): ?>
            <?php
            $lewat = strtotime($t['batas_waktu']) < time();
            $sudah = !empty($t['waktu_kumpul']);
            ?>
            <div class="item">
                <h3><?= e($t['nama_tugas']) ?></h3>
                <p class="keterangan">
                    <?= e($t['nama_mk']) ?> - batas <?= tanggal_jam($t['batas_waktu']) ?>
                    <?php if ($sudah): ?>
                        <span class="lencana hijau">Sudah dikumpulkan</span>
                    <?php elseif ($lewat): ?>
                        <span class="lencana merah">Terlewat</span>
                    <?php else: ?>
                        <span class="lencana kuning">Belum dikumpulkan</span>
                    <?php endif; ?>
                    <?php if ($t['nilai'] !== null): ?>
                        <span class="lencana biru">Nilai: <?= e($t['nilai']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($t['deskripsi']): ?><p><?= nl2br(e($t['deskripsi'])) ?></p><?php endif; ?>

                <?php if ($sudah): ?>
                    <p class="keterangan">Dikumpulkan <?= tanggal_jam($t['waktu_kumpul']) ?>.
                        <?php if ($t['file_jawaban']): ?><a href="../<?= e($t['file_jawaban']) ?>" target="_blank">Lihat file saya</a><?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if (!$lewat): ?>
                    <form method="post" enctype="multipart/form-data" class="filter">
                        <input type="hidden" name="tugas_id" value="<?= $t['id'] ?>">
                        <div>
                            <label>File jawaban <?= $sudah ? '(kosongkan jika tidak diganti)' : '' ?></label>
                            <input type="file" name="file_jawaban">
                        </div>
                        <div>
                            <label>Catatan</label>
                            <input type="text" name="catatan" value="<?= e($t['catatan_saya'] ?? '') ?>">
                        </div>
                        <button type="submit" class="tombol"><?= $sudah ? 'Perbarui jawaban' : 'Kumpulkan' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
