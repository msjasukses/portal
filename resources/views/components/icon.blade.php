@props(['name', 'size' => 24, 'class' => ''])
{!! \App\Support\Icon::svg($name, (int) $size, $class) !!}
