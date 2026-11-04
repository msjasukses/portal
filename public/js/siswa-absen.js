/*
 * Absen barcode: memindai QR layar absensi dengan kamera HP, dari dalam portal.
 *
 * Alurnya: tombol "Absen Sekarang" -> kamera menyala -> begitu barcode terbaca
 * kamera dimatikan dan muncul tombol "Absen" yang mengirimkan tokennya.
 *
 * Memakai BarcodeDetector bawaan browser, jadi tidak ada pustaka tambahan.
 * Dua syarat yang tidak bisa dilangkahi dari sisi kode:
 *   - halaman harus HTTPS (atau localhost); di luar itu browser menolak
 *     memberi akses kamera, apa pun izin yang diberikan siswa
 *   - browsernya mendukung BarcodeDetector (Chrome Android; Safari iOS belum)
 * Bila salah satu tidak terpenuhi, pemindai disembunyikan dan siswa diarahkan
 * memindai dengan aplikasi Kamera HP — hasilnya membuka halaman ini dengan
 * token di alamat, lalu absennya diteruskan seperti biasa.
 */
(function () {
  'use strict';

  var kotak = document.querySelector('[data-scan]');
  var halangan = document.querySelector('[data-scan-halangan]');
  if (!kotak) return;

  function tolak(pesan) {
    if (!halangan) return;
    halangan.textContent = pesan;
    halangan.hidden = false;
  }

  if (!window.isSecureContext) {
    tolak('Kamera hanya bisa dibuka lewat alamat HTTPS. Untuk sekarang, pindai barcode dengan aplikasi Kamera HP seperti panduan di bawah.');
    return;
  }

  if (typeof window.BarcodeDetector !== 'function' || !navigator.mediaDevices) {
    tolak('Browser di HP ini belum bisa membaca barcode sendiri. Pindai barcode dengan aplikasi Kamera HP seperti panduan di bawah.');
    return;
  }

  var mulai = kotak.querySelector('[data-scan-mulai]');
  var kamera = kotak.querySelector('[data-scan-kamera]');
  var video = kotak.querySelector('[data-scan-video]');
  var status = kotak.querySelector('[data-scan-status]');
  var tombol = kotak.querySelector('[data-scan-start]');
  var batal = kotak.querySelector('[data-scan-batal]');
  var form = kotak.querySelector('[data-scan-form]');
  var isian = kotak.querySelector('[data-scan-token]');
  var panduan = document.querySelector('[data-scan-panduan]');
  var stream = null;
  var terbaca = false;

  kotak.hidden = false;

  function hentikan() {
    if (stream) {
      stream.getTracks().forEach(function (track) { track.stop(); });
    }
    stream = null;
    kamera.hidden = true;
  }

  function keLangkahAwal() {
    hentikan();
    mulai.hidden = false;
    tombol.disabled = false;
    status.textContent = '';
    if (panduan) panduan.hidden = false;
  }

  function terima(isiBarcode) {
    if (terbaca) return;
    terbaca = true;
    hentikan();
    isian.value = isiBarcode;
    form.hidden = false;
    if (panduan) panduan.hidden = true;
  }

  function pindai(detector) {
    if (terbaca || !stream) return;

    detector.detect(video).then(function (hasil) {
      if (hasil.length > 0 && hasil[0].rawValue) {
        terima(hasil[0].rawValue);
      } else {
        requestAnimationFrame(function () { pindai(detector); });
      }
    }).catch(function () {
      // Frame yang belum siap bukan kegagalan — coba frame berikutnya.
      requestAnimationFrame(function () { pindai(detector); });
    });
  }

  tombol.addEventListener('click', function () {
    tombol.disabled = true;
    status.textContent = 'Meminta izin kamera…';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (media) {
      stream = media;
      video.srcObject = media;
      mulai.hidden = true;
      kamera.hidden = false;
      if (panduan) panduan.hidden = true;

      return video.play().then(function () {
        pindai(new window.BarcodeDetector({ formats: ['qr_code'] }));
      });
    }).catch(function () {
      keLangkahAwal();
      status.classList.add('scan__status--error');
      status.textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser, atau pindai dengan aplikasi Kamera HP.';
    });
  });

  batal.addEventListener('click', keLangkahAwal);
  window.addEventListener('pagehide', hentikan);
})();

/* Hitung mundur sisa berlaku barcode pada halaman konfirmasi. */
(function () {
  'use strict';

  var kotak = document.querySelector('[data-hitung-mundur]');
  if (!kotak) return;

  var sisa = Number(kotak.dataset.hitungMundur);

  var jam = setInterval(function () {
    sisa -= 1;
    if (sisa <= 0) {
      clearInterval(jam);
      kotak.textContent = 'kurang dari 1 detik';
      return;
    }
    kotak.textContent = sisa + ' detik';
  }, 1000);
})();
