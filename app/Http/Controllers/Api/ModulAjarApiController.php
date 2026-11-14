<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModulAjarResource;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\ModulAjar;
use Illuminate\Http\Request;

/**
 * Rincian modul ajar yang dipilih guru pada sebuah agenda mengajar.
 *
 * Haknya tidak datang dari modulnya, melainkan dari agenda: modul boleh
 * dibaca bila ia dipakai pada setidaknya satu agenda di kelas siswa itu.
 * Modul yang tidak pernah muncul di agenda kelasnya diperlakukan seolah tidak
 * ada — 404, bukan 403. Pesan "Anda tidak berhak" tetap memberi tahu bahwa
 * modul bernomor itu ada, dan nomor bisa dicoba satu per satu.
 */
class ModulAjarApiController extends Controller
{
    public function __invoke(Request $request, ModulAjar $modul): ModulAjarResource
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');

        abort_unless($modul->terpakaiDiKelas($siswa->semuaRombelId()), 404);

        $modul->load('mataPelajaran');

        return new ModulAjarResource($modul);
    }
}
