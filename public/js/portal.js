/* Portal Siswa: konfirmasi aksi destruktif & pratinjau gambar sebelum diunggah. */
(function () {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = document.querySelector(input.dataset.preview);
      var file = input.files && input.files[0];
      if (!box || !file || file.type.indexOf('image/') !== 0) return;

      var img = box.querySelector('img');
      if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
      img.dataset.objectUrl = URL.createObjectURL(file);
      img.src = img.dataset.objectUrl;
      box.hidden = false;
      box.classList.add('preview--new');

      var empty = box.parentElement.querySelector('[data-preview-empty]');
      if (empty) empty.hidden = true;
    });
  });

  // Tolak file yang melebihi batas server sebelum terlanjur diunggah.
  document.querySelectorAll('input[type="file"][data-max-bytes]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (file && file.size > Number(input.dataset.maxBytes)) {
        window.alert('Ukuran file terlalu besar. Maksimal ' + input.dataset.maxLabel + '.');
        input.value = '';
      }
    });
  });

  // File besar butuh waktu: kunci tombol simpan & tampilkan status agar tidak diklik dua kali.
  document.querySelectorAll('form[data-uploading]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      var hasFile = Array.prototype.some.call(form.querySelectorAll('input[type="file"]'), function (input) {
        return input.files && input.files.length > 0;
      });
      if (!button || !hasFile) return;

      button.disabled = true;
      button.querySelector('span').textContent = 'Mengunggah… mohon tunggu';
    });
  });
})();
