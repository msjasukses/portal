<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaResource;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\AgendaMengajar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Agenda mengajar di kelas siswa (data aplikasi Kurikulum), lengkap dengan
 * status kehadiran siswa pada tiap jam pelajaran.
 */
class AgendaApiController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
        ], [], [
            'bulan' => 'Bulan',
        ]);

        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $bulan = $request->filled('bulan') ? CarbonImmutable::createFromFormat('!Y-m', $request->query('bulan')) : null;

        $agenda = AgendaMengajar::with(['guru', 'mataPelajaran'])
            ->untukKelas($siswa->rombelAktif()?->id)
            ->when($bulan, fn ($query) => $query->whereBetween('waktu_pengisian', [$bulan->startOfMonth(), $bulan->endOfMonth()]))
            ->latest('waktu_pengisian')
            ->paginate(15)
            ->withQueryString();

        return AgendaResource::collection($agenda);
    }
}
