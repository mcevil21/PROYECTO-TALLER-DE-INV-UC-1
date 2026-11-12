<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('el boton de llamada marca el numero configurado con el codigo de pais', function () {
    config(['incidencias.support.phone' => '970018008', 'incidencias.support.country_code' => '+51']);

    $html = Blade::render('<x-call-support />');

    expect($html)
        ->toContain('href="tel:+51970018008"')
        ->toContain('970 018 008')
        ->toContain('Llamar a soporte');
});

it('no duplica el codigo de pais si el numero ya lo trae', function () {
    config(['incidencias.support.phone' => '51970018008', 'incidencias.support.country_code' => '+51']);

    expect(Blade::render('<x-call-support />'))->toContain('href="tel:+51970018008"');
});

it('la variante de enlace tambien marca el numero', function () {
    config(['incidencias.support.phone' => '970018008']);

    expect(Blade::render('<x-call-support variant="link" />'))
        ->toContain('href="tel:+51970018008"');
});

it('sin numero configurado no muestra ningun boton', function () {
    // El sistema debe funcionar igual sin esta configuracion.
    config(['incidencias.support.phone' => null]);

    expect(trim(Blade::render('<x-call-support />')))->toBe('')
        ->and(trim(Blade::render('<x-call-support variant="link" />')))->toBe('');
});
