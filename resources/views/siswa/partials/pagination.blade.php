{{-- Navigasi halaman untuk tabel Portal Siswa (tanpa Tailwind/Bootstrap). --}}
@if ($paginator->hasPages())
  <nav class="pager" aria-label="Navigasi halaman">
    <span>Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data</span>
    <div class="pager__links">
      @if ($paginator->onFirstPage())
        <span class="is-disabled">&lsaquo;</span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya">&lsaquo;</a>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="is-disabled">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="is-current" aria-current="page">{{ $page }}</span>
            @else
              <a href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya">&rsaquo;</a>
      @else
        <span class="is-disabled">&rsaquo;</span>
      @endif
    </div>
  </nav>
@endif
