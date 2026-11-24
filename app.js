/* SIAKAD - skrip tampilan: tema terang/gelap, menu mobile, pencarian tabel, dll. */

/* ---------- Tema (dijalankan langsung agar tidak berkedip) ---------- */
(function () {
    var KUNCI = 'siakad-tema';
    var tema = 'light';
    try {
        var simpan = localStorage.getItem(KUNCI);
        if (simpan === 'dark' || simpan === 'light') { tema = simpan; }
    } catch (e) { /* penyimpanan tidak tersedia */ }
    document.documentElement.setAttribute('data-theme', tema);

    window.gantiTema = function () {
        var sekarang = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var baru = sekarang === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', baru);
        try { localStorage.setItem(KUNCI, baru); } catch (e) { }
        perbaruiTombolTema();
    };
})();

function perbaruiTombolTema() {
    var gelap = document.documentElement.getAttribute('data-theme') === 'dark';
    var tombol = document.querySelectorAll('[data-tema-toggle]');
    for (var i = 0; i < tombol.length; i++) {
        tombol[i].setAttribute('aria-label', gelap ? 'Ganti ke tampilan terang' : 'Ganti ke tampilan gelap');
        tombol[i].setAttribute('title', gelap ? 'Tampilan terang' : 'Tampilan gelap');
        tombol[i].setAttribute('aria-pressed', gelap ? 'true' : 'false');
    }
}

/* ---------- Tampilkan / sembunyikan sandi (fungsi lama dipertahankan) ---------- */
function lihatSandi(idKolom, tombol) {
    var kolom = document.getElementById(idKolom);
    if (kolom.type === 'password') {
        kolom.type = 'text';
        tombol.textContent = 'Sembunyikan';
    } else {
        kolom.type = 'password';
        tombol.textContent = 'Lihat';
    }
}

document.addEventListener('DOMContentLoaded', function () {

    /* Tombol ganti tema */
    perbaruiTombolTema();
    var tombolTema = document.querySelectorAll('[data-tema-toggle]');
    for (var t = 0; t < tombolTema.length; t++) {
        tombolTema[t].addEventListener('click', window.gantiTema);
    }

    /* Tanggal hari ini di header */
    var tgl = document.querySelectorAll('[data-tanggal]');
    if (tgl.length) {
        var teks = '';
        try {
            teks = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        } catch (e) { teks = ''; }
        for (var d = 0; d < tgl.length; d++) { tgl[d].textContent = teks; }
    }

    /* Menu navigasi di layar kecil */
    var menu = document.querySelector('.menu');
    var tombolMenu = document.querySelector('[data-menu-buka]');
    if (menu && tombolMenu) {
        tombolMenu.addEventListener('click', function () {
            var buka = menu.classList.toggle('buka');
            tombolMenu.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    }

    /* Pesan: tombol tutup, dan hilang otomatis untuk pesan kilat (setelah simpan data) */
    var pesan = document.querySelectorAll('.pesan[data-kilat]');
    for (var p = 0; p < pesan.length; p++) {
        (function (kotak) {
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'pesan-tutup';
            x.setAttribute('aria-label', 'Tutup pesan');
            x.innerHTML = '&times;';
            x.addEventListener('click', function () { sembunyi(kotak); });
            kotak.appendChild(x);
            if (kotak.classList.contains('sukses')) {
                setTimeout(function () { sembunyi(kotak); }, 7000);
            }
        })(pesan[p]);
    }
    function sembunyi(kotak) {
        kotak.classList.add('hilang');
        setTimeout(function () { if (kotak.parentNode) { kotak.parentNode.removeChild(kotak); } }, 450);
    }

    /* Kotak cari otomatis untuk tabel yang barisnya banyak */
    var gulir = document.querySelectorAll('main .gulir');
    for (var g = 0; g < gulir.length; g++) {
        (function (pembungkus) {
            var tabel = pembungkus.querySelector('table');
            if (!tabel || tabel.classList.contains('tabel-cetak-krs')) { return; }
            var baris = [];
            var semua = tabel.rows;
            for (var r = 0; r < semua.length; r++) {
                if (semua[r].cells.length && semua[r].cells[0].tagName === 'TD' && semua[r].cells.length > 1) {
                    baris.push(semua[r]);
                }
            }
            if (baris.length < 8) { return; }
            var kotak = document.createElement('div');
            kotak.className = 'cari-tabel tanpa-cetak';
            kotak.innerHTML = '<svg class="ikon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
            var isian = document.createElement('input');
            isian.type = 'search';
            isian.placeholder = 'Cari di tabel ini...';
            isian.setAttribute('aria-label', 'Cari di tabel');
            kotak.appendChild(isian);
            pembungkus.parentNode.insertBefore(kotak, pembungkus);
            isian.addEventListener('input', function () {
                var kata = isian.value.toLowerCase().trim();
                for (var i = 0; i < baris.length; i++) {
                    var cocok = kata === '' || baris[i].textContent.toLowerCase().indexOf(kata) !== -1;
                    baris[i].style.display = cocok ? '' : 'none';
                }
            });
        })(gulir[g]);
    }

    /* Tombol kembali ke atas */
    var atas = document.querySelector('[data-ke-atas]');
    if (atas) {
        var cek = function () {
            if (window.scrollY > 320) { atas.classList.add('tampil'); } else { atas.classList.remove('tampil'); }
        };
        window.addEventListener('scroll', cek, { passive: true });
        atas.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        cek();
    }
});
