@extends('teacher.layout')
@section('title', '¿En qué aula estás?')
@section('step', 'Ubica tu aula')

@section('content')
    <h1 class="mb-2 text-[28px] font-semibold leading-9 tracking-[-0.02em]">¿En qué aula estás?</h1>
    <p class="mb-6 text-base text-on-surface-variant">
        Escribe el código que está en la puerta.
    </p>

    @if (session('error'))
        <div class="mb-5 glass border-danger/70 bg-danger-container/70 px-4 py-3">
            <p class="text-base text-on-danger-container">{{ session('error') }}</p>
        </div>
    @endif

    {{-- El índice de aulas viaja en un atributo y lo filtra el navegador al
         escribir (resources/js/room-search.js). Sin JavaScript el formulario
         sigue funcionando: envía el código a la búsqueda del servidor. --}}
    <div data-room-search
         data-rooms="{{ json_encode($index->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">

        <form method="GET" action="{{ route('teacher.search') }}" class="space-y-3" data-room-form>
            <input name="q" autofocus autocomplete="off" inputmode="text" autocapitalize="characters"
                   placeholder="Por ejemplo: C305" aria-label="Código del aula" data-room-input
                   class="block w-full rounded-xl border-2 border-outline-variant px-5 py-4 text-center font-mono text-2xl uppercase tracking-wide focus:border-primary focus:ring-0">

            <button type="submit"
                    class="flex min-h-[60px] w-full items-center justify-center btn-primary focus-ring px-5 py-4 text-lg font-semibold hover:opacity-90">
                Buscar
            </button>
        </form>

        <div class="mt-6 space-y-3" data-room-results aria-live="polite"></div>

        <p hidden data-room-more class="mt-3 text-sm text-on-surface-variant">
            Hay más aulas con ese inicio. Sigue escribiendo para acotar.
        </p>

        <p hidden data-room-empty
           class="mt-6 rounded-lg border-2 border-warn bg-warn-container px-4 py-3 text-base text-on-warn-container">
            No encontramos ningún aula con ese código. Revisa que esté bien escrito
            o elige tu aula paso a paso.
        </p>

        {{-- Molde de cada sugerencia: reutiliza el mismo botón grande del resto
             del flujo, así que no hay estilos nuevos. --}}
        <template data-room-template>
            <x-big-option href="#">
                <span class="font-mono font-semibold" data-room-code></span>
                <span class="block text-sm font-normal text-on-surface-variant" data-room-place></span>
            </x-big-option>
        </template>
    </div>
@endsection

@section('back')
    <a href="{{ route('teacher.browse') }}"
       class="block text-center text-base text-on-surface-variant underline underline-offset-4">
        No sé el código: elegir paso a paso
    </a>
@endsection
