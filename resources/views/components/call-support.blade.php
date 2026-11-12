@props(['variant' => 'button'])

{{-- Boton «Llamar a soporte». Un enlace tel: abre el marcador del celular con
     el numero ya escrito; el navegador exige que el docente lo toque, asi que
     no se puede marcar solo. Si no hay numero configurado no muestra nada. --}}
@php
    $digits = preg_replace('/\D+/', '', (string) config('incidencias.support.phone'));
    $country = preg_replace('/\D+/', '', (string) config('incidencias.support.country_code', '+51'));

    // Si el numero ya trae el codigo de pais (51970...), no se duplica.
    $local = ($country !== '' && strlen($digits) > 9 && str_starts_with($digits, $country))
        ? substr($digits, strlen($country))
        : $digits;

    $international = '+'.$country.$local;
    $pretty = trim(chunk_split($local, 3, ' '));
@endphp

@if ($local !== '')
    @if ($variant === 'link')
        <a href="tel:{{ $international }}"
           class="block text-center text-base text-on-surface-variant underline underline-offset-4">
            Llamar a soporte · {{ $pretty }}
        </a>
    @else
        <a href="tel:{{ $international }}"
           {{ $attributes->merge(['class' => 'mt-3 flex min-h-[64px] w-full items-center justify-center gap-3 rounded-xl border-2 border-primary px-5 py-4 text-lg font-semibold text-on-surface hover:bg-surface-high focus-ring']) }}>
            <span aria-hidden="true" class="material-symbols-outlined text-[28px] text-accent">call</span>
            <span>Llamar a soporte <span class="font-mono">{{ $pretty }}</span></span>
        </a>
    @endif
@endif
