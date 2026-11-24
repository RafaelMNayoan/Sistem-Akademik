CREATE DATABASE IF NOT EXISTS siakad_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER DATABASE siakad_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siakad_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pengaturan, prodi, tahun_akademik, dosen, pengumuman, kelas_kuliah, skripsi, bimbingan_skripsi, surat,
                     komponen_biaya, pembayaran, beasiswa, log_aktivitas;
DROP TABLE IF EXISTS akun_pengguna, pengumpulan_tugas, tugas_kuliah, materi_kuliah, nilai, presensi,
                     sesi_presensi, krs, tagihan, mata_kuliah, mahasiswa, admin;
DROP TABLE IF EXISTS jadwal_mk, ipk;
DROP VIEW IF EXISTS vw_dashboard_mahasiswa;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(30) NOT NULL UNIQUE,
    nama      VARCHAR(100) NOT NULL,
    password  VARCHAR(255) NOT NULL
);

CREATE TABLE akun_pengguna (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('kaprodi','staff','bendahara','dosen') NOT NULL,
    prodi VARCHAR(100) NULL,
    status ENUM('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mahasiswa (
    nim            VARCHAR(10) PRIMARY KEY,
    nama_lengkap   VARCHAR(100) NOT NULL,
    alamat         TEXT,
    tempat_lahir   VARCHAR(50),
    tanggal_lahir  DATE,
    jenis_kelamin  ENUM('L', 'P') NOT NULL,
    agama          VARCHAR(20),
    nik            VARCHAR(16) UNIQUE,
    no_handphone   VARCHAR(20),
    email          VARCHAR(100),
    angkatan       YEAR,
    program_studi  VARCHAR(100),
    jenjang        ENUM('S1', 'D3'),
    nama_ayah      VARCHAR(100),
    nama_ibu       VARCHAR(100),
    password       VARCHAR(255) NOT NULL,
    status         ENUM('Aktif', 'Cuti', 'Non-Aktif') DEFAULT 'Aktif',
    semester       INT DEFAULT 1,
    tgl_daftar     DATE NOT NULL
);

CREATE TABLE mata_kuliah (
    kode_mk  VARCHAR(20) PRIMARY KEY,
    nama_mk  VARCHAR(150) NOT NULL,
    sks      INT NOT NULL,
    kelas    ENUM('Teori', 'Praktik', 'Kombinasi') DEFAULT 'Teori'
);

CREATE TABLE krs (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nim       VARCHAR(10) NOT NULL,
    semester  INT NOT NULL,
    kode_mk   VARCHAR(20) NOT NULL,
    UNIQUE KEY (nim, semester, kode_mk),
    FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE,
    FOREIGN KEY (kode_mk) REFERENCES mata_kuliah(kode_mk) ON DELETE CASCADE
);

CREATE TABLE sesi_presensi (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    kode_mk    VARCHAR(20) NOT NULL,
    pertemuan  INT NOT NULL,
    tanggal    DATE NOT NULL,
    status     ENUM('Buka', 'Tutup') DEFAULT 'Buka',
    FOREIGN KEY (kode_mk) REFERENCES mata_kuliah(kode_mk) ON DELETE CASCADE
);

CREATE TABLE presensi (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sesi_id    INT NOT NULL,
    nim        VARCHAR(10) NOT NULL,
    jam_masuk  TIME,
    status     ENUM('Hadir', 'Izin', 'Sakit', 'Alpha') DEFAULT 'Hadir',
    catatan    TEXT,
    UNIQUE KEY (sesi_id, nim),
    FOREIGN KEY (sesi_id) REFERENCES sesi_presensi(id) ON DELETE CASCADE,
    FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE
);

CREATE TABLE materi_kuliah (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kode_mk         VARCHAR(20) NOT NULL,
    judul_materi    VARCHAR(200) NOT NULL,
    deskripsi       TEXT,
    file_url        VARCHAR(255),
    tanggal_upload  DATE NOT NULL,
    FOREIGN KEY (kode_mk) REFERENCES mata_kuliah(kode_mk) ON DELETE CASCADE
);

CREATE TABLE tugas_kuliah (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kode_mk      VARCHAR(20) NOT NULL,
    nama_tugas   VARCHAR(200) NOT NULL,
    deskripsi    TEXT,
    batas_waktu  DATETIME NOT NULL,
    FOREIGN KEY (kode_mk) REFERENCES mata_kuliah(kode_mk) ON DELETE CASCADE
);

CREATE TABLE pengumpulan_tugas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tugas_id      INT NOT NULL,
    nim           VARCHAR(10) NOT NULL,
    file_jawaban  VARCHAR(255),
    catatan       TEXT,
    waktu_kumpul  DATETIME NOT NULL,
    nilai         DECIMAL(5,2),
    UNIQUE KEY (tugas_id, nim),
    FOREIGN KEY (tugas_id) REFERENCES tugas_kuliah(id) ON DELETE CASCADE,
    FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE
);

CREATE TABLE tagihan (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nim                  VARCHAR(10) NOT NULL,
    semester             INT NOT NULL,
    kode_tagihan         VARCHAR(50) UNIQUE NOT NULL,
    nama_tagihan         VARCHAR(100) NOT NULL,
    biaya                DECIMAL(15,2) NOT NULL,
    potongan             DECIMAL(15,2) DEFAULT 0,
    total_bayar          DECIMAL(15,2) NOT NULL,
    tanggal_jatuh_tempo  DATE NOT NULL,
    tanggal_bayar        DATE,
    status               ENUM('Belum Dibayar', 'Dibayar', 'Terlambat') DEFAULT 'Belum Dibayar',
    FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE
);

CREATE TABLE nilai (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nim          VARCHAR(10) NOT NULL,
    kode_mk      VARCHAR(20) NOT NULL,
    huruf_grade  ENUM('A', 'B+', 'B', 'C+', 'C', 'D', 'E') NOT NULL,
    UNIQUE KEY (nim, kode_mk),
    FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE,
    FOREIGN KEY (kode_mk) REFERENCES mata_kuliah(kode_mk) ON DELETE CASCADE
);

INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, kelas) VALUES
('IF101', 'Pemrograman Web', 3, 'Kombinasi'),
('IF102', 'Pengembangan Sistem Informasi', 3, 'Teori'),
('IF103', 'Basis Data', 3, 'Kombinasi'),
('IF104', 'Jaringan Komputer', 3, 'Teori'),
('IF105', 'Rekayasa Perangkat Lunak', 3, 'Kombinasi'),
('IF106', 'Pengantar Teknologi Informasi', 2, 'Teori');
