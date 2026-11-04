@if (session('success'))
  <div class="alert alert--success">
    <x-icon name="check" size="16" />
    <span>{{ session('success') }}</span>
  </div>
@endif

@if (session('warning'))
  <div class="alert alert--warning">
    <x-icon name="alert" size="16" />
    <span>{{ session('warning') }}</span>
  </div>
@endif

@if (session('error'))
  <div class="alert alert--error">
    <x-icon name="x" size="16" />
    <span>{{ session('error') }}</span>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert--error">
    <x-icon name="x" size="16" />
    @if ($errors->count() === 1)
      <span>{{ $errors->first() }}</span>
    @else
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    @endif
  </div>
@endif
