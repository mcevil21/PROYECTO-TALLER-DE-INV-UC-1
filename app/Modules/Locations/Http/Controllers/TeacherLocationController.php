<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Http\Controllers\TeacherIncidentController;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Services\CascadeValidator;
use App\Modules\Locations\Services\LocationCatalogService;
use App\Shared\Exceptions\InvalidLocationException;
use App\Shared\Support\IpHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Seleccion de ubicacion del docente tras escanear el QR generico.
 *
 * Publico y sin autenticacion: el docente escanea y empieza (plan 16.1).
 *
 * Flujo (plan 7.1):
 *   sede -> pabellon -> piso -> aula -> CONFIRMACION EXPLICITA
 *
 * La seleccion viaja en la URL, no en la sesion. Es una decision
 * deliberada: asi el boton "atras" del navegador funciona como el docente
 * espera, y si se equivoca puede retroceder un paso sin reiniciar todo.
 * Guardarla en sesion habria hecho que "atras" mostrara una pantalla que ya
 * no corresponde al estado real.
 *
 * Nada de lo que llega por la URL se da por bueno: la cadena completa se
 * revalida contra el catalogo antes de confirmar.
 */
class TeacherLocationController extends Controller
{
    public function __construct(
        private readonly LocationCatalogService $catalog,
        private readonly CascadeValidator $validator,
        private readonly IncidentService $incidents,
        private readonly IpHasher $hasher,
    ) {}

    /**
     * Entrada del QR: el docente escribe el codigo de su aula.
     *
     * POR QUE EL CODIGO Y NO CUATRO LISTAS
     *
     * El codigo esta escrito en la puerta del aula y lo identifica por si
     * solo: la sede, el pabellon y el piso salen de el. Pedirlos aparte es
     * hacerle contestar tres preguntas cuya respuesta ya esta en la cuarta.
     * Mientras escribe aparecen las coincidencias, y al tocar una pasa a la
     * confirmacion explicita de siempre.
     *
     * SIN DEPENDER DE JAVASCRIPT PARA LO QUE IMPORTA
     *
     * El formulario envia el codigo a la busqueda del servidor, que ya
     * existia. Lo que hace el navegador es adelantar las sugerencias; quien
     * decide si el aula existe sigue siendo el servidor, que revalida toda
     * la cadena al confirmar.
     */
    public function start(): View|RedirectResponse
    {
        if ($this->catalog->sites()->isEmpty()) {
            return view('teacher.unavailable');
        }

        // El indice viaja entero en el HTML y el navegador filtra mientras el
        // docente escribe: es el mismo criterio de la cascada (cero idas y
        // vueltas al servidor en una red de aula que puede estar saturada).
        // Solo lleva lo imprescindible para ofrecer y confirmar un aula.
        $index = $this->catalog->allRooms()
            ->load('floor.building')
            ->map(function (Room $room): ?array {
                $floor = $room->floor;
                $building = $floor?->building;

                // Un aula sin piso o sin pabellon no se puede confirmar: no se
                // ofrece, en lugar de ofrecerla y fallar al tocarla.
                if ($floor === null || $building === null) {
                    return null;
                }

                return [
                    'c' => $room->code,
                    'p' => $building->name,
                    'u' => route('teacher.confirm', [
                        'site' => $building->site_id,
                        'building' => $building->id,
                        'floor' => $floor->id,
                        'room' => $room->id,
                    ]),
                ];
            })
            ->filter()
            ->values();

        return view('teacher.start', ['index' => $index]);
    }

    /**
     * Elegir el aula paso a paso: sede, pabellon, piso y aula.
     *
     * Era la pantalla de inicio. Ahora es la alternativa para quien no sabe
     * el codigo de su aula. Se conserva entera porque tambien es la red de
     * seguridad si el navegador del docente no ejecuta el filtrado de la
     * pantalla de inicio.
     */
    public function browse(): View|RedirectResponse
    {
        $sites = $this->catalog->sites();

        if ($sites->isEmpty()) {
            return view('teacher.unavailable');
        }

        return view('teacher.locate', [
            'sites' => $sites,
            'buildings' => $this->catalog->allBuildings(),
            'floors' => $this->catalog->allFloors(),
            'rooms' => $this->catalog->allRooms(),
        ]);
    }

    /**
     * Recibe la eleccion de la pantalla unica y la manda a confirmar.
     *
     * No confirma nada aqui: la pantalla de confirmacion existe porque un
     * ticket con el aula equivocada es un dato falso dentro de la
     * investigacion Y un tecnico caminando al sitio incorrecto. Vale la pena
     * la pantalla de mas.
     */
    public function locate(Request $request): RedirectResponse
    {
        $request->validate([
            'site_id' => ['required', 'integer'],
            'building_id' => ['required', 'integer'],
            'floor_id' => ['required', 'integer'],
            'room_id' => ['required', 'integer'],
        ], [
            'room_id.required' => 'Elige el aula donde estás antes de continuar.',
            'floor_id.required' => 'Elige el piso donde estás.',
            'building_id.required' => 'Elige el pabellón donde estás.',
            'site_id.required' => 'Elige la sede donde estás.',
        ]);

        try {
            $this->validator->validate(
                $request->integer('site_id'),
                $request->integer('building_id'),
                $request->integer('floor_id'),
                $request->integer('room_id'),
            );
        } catch (InvalidLocationException $e) {
            return redirect()->route('teacher.start')->with('error', $e->teacherMessage());
        }

        return redirect()->route('teacher.confirm', [
            'site' => $request->integer('site_id'),
            'building' => $request->integer('building_id'),
            'floor' => $request->integer('floor_id'),
            'room' => $request->integer('room_id'),
        ]);
    }

    /**
     * Recorrido por pasos, que sigue existiendo.
     *
     * Lo usa el buscador por codigo y es la red de seguridad si el navegador
     * del docente no ejecuta el filtrado de la pantalla unica.
     */
    public function sitesStep(): View|RedirectResponse
    {
        $sites = $this->catalog->sites();

        if ($sites->isEmpty()) {
            return view('teacher.unavailable');
        }

        // Con una sola sede no hay nada que preguntar: el docente se ahorra
        // un toque y la pantalla no llega a mostrarse (plan OS-1).
        if (($only = $this->catalog->autoSelect($sites)) !== null) {
            return $this->skipTo(route('teacher.buildings', ['site' => $only->id]));
        }

        return view('teacher.sites', compact('sites'));
    }

    public function buildings(int $site): View|RedirectResponse
    {
        $buildings = $this->catalog->buildingsOf($site);

        if ($buildings->isEmpty()) {
            return view('teacher.unavailable');
        }

        if (($only = $this->catalog->autoSelect($buildings)) !== null) {
            return $this->skipTo(route('teacher.floors', ['site' => $site, 'building' => $only->id]));
        }

        return view('teacher.buildings', compact('site', 'buildings'));
    }

    public function floors(int $site, int $building): View|RedirectResponse
    {
        $floors = $this->catalog->floorsOf($building);

        if ($floors->isEmpty()) {
            return view('teacher.unavailable');
        }

        if (($only = $this->catalog->autoSelect($floors)) !== null) {
            return $this->skipTo(route('teacher.rooms', [
                'site' => $site, 'building' => $building, 'floor' => $only->id,
            ]));
        }

        return view('teacher.floors', compact('site', 'building', 'floors'));
    }

    /**
     * Redireccion por OMISION DE NIVEL (cuando solo hay una opcion).
     *
     * Reenvia los mensajes flash en lugar de consumirlos. Sin esto ocurria
     * un fallo real, detectado probando en el navegador y no por las
     * pruebas: al rechazar una ubicacion invalida se redirige al inicio con
     * un mensaje de error, pero si el inicio omite un nivel y vuelve a
     * redirigir, ese salto se come el mensaje. El docente acababa de vuelta
     * en la primera pantalla sin ninguna explicacion de por que.
     *
     * El plan lo prohibe expresamente: un rechazo nunca debe dejar al
     * docente sin saber que pasó (degradacion segura, 16.4).
     */
    private function skipTo(string $url): RedirectResponse
    {
        session()->reflash();

        return redirect()->to($url);
    }

    public function rooms(int $site, int $building, int $floor): View
    {
        return view('teacher.rooms', [
            'site' => $site,
            'building' => $building,
            'floor' => $floor,
            'rooms' => $this->catalog->roomsOf($floor),
        ]);
    }

    /**
     * Pantalla de confirmacion explicita, obligatoria antes de reportar.
     *
     * Aqui se valida la cadena COMPLETA por primera vez. Hasta este punto
     * el docente solo ha navegado; a partir de aqui el sistema se
     * compromete con una ubicacion.
     */
    public function confirm(int $site, int $building, int $floor, int $room): View|RedirectResponse
    {
        try {
            $validated = $this->validator->validate($site, $building, $floor, $room);
        } catch (InvalidLocationException $e) {
            return redirect()->route('teacher.start')->with('error', $e->teacherMessage());
        }

        return view('teacher.confirm', ['room' => $validated]);
    }

    /**
     * El docente confirma. Se REVALIDA todo aunque ya se valido al mostrar
     * la pantalla: entre una peticion y otra el aula pudo desactivarse, y
     * sobre todo, este POST es alcanzable directamente sin pasar por la
     * pantalla anterior.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'site_id' => ['required', 'integer'],
            'building_id' => ['required', 'integer'],
            'floor_id' => ['required', 'integer'],
            'room_id' => ['required', 'integer'],
        ]);

        // La regla 'integer' COMPRUEBA que el valor sea numerico, pero no lo
        // convierte: un formulario HTML envia "36", no 36. Con strict_types
        // eso revienta al llegar al validador. Se usa $request->integer()
        // para obtener el tipo de verdad.
        try {
            $room = $this->validator->validate(
                $request->integer('site_id'),
                $request->integer('building_id'),
                $request->integer('floor_id'),
                $request->integer('room_id'),
            );
        } catch (InvalidLocationException $e) {
            return redirect()->route('teacher.start')->with('error', $e->teacherMessage());
        }

        // Recordar la ultima ubicacion en ESTE dispositivo. Se OFRECE como
        // atajo la proxima vez, nunca se aplica sola: el docente puede estar
        // en otra aula y darlo por hecho generaria tickets con la ubicacion
        // equivocada, que es peor que no tener atajo (plan CU-D-04b).
        $request->session()->put('teacher.last_room_id', $room->id);

        // Identificador opaco del dispositivo, sin datos personales. Sirve
        // para el limite antiabuso por dispositivo y para reconocer al
        // mismo telefono entre visitas.
        $deviceKey = $request->session()->get('teacher.device_key');

        if (! is_string($deviceKey)) {
            $deviceKey = Str::random(32);
            $request->session()->put('teacher.device_key', $deviceKey);
        }

        // Nace el BORRADOR. Todavia NO es un ticket: soporte no lo ve y no
        // se notifica a nadie. Existe para poder medir abandonos y el
        // tiempo real desde el inicio del reporte (plan 7.1).
        $incident = $this->incidents->createDraft(
            $room,
            $deviceKey,
            $this->hasher->hash($request->ip()),
        );

        $request->session()->put(TeacherIncidentController::sessionKey(), $incident->uuid);

        /*
         * Aulas que no se autoatienden: directo a soporte, sin preguntar el
         * problema y sin diagnostico.
         *
         * Hoy son las HYFLEX, cuyas averias son de software y quedan fuera
         * del alcance del proyecto. Hacerle recorrer al docente la lista de
         * equipos para acabar igualmente en el boton de soporte seria
         * hacerle perder el tiempo con una pregunta cuya respuesta ya se
         * conoce.
         *
         * Se le lleva a la pantalla de solicitud, no se le manda a otro
         * canal: el ticket tiene que quedar registrado. Un problema que el
         * sistema no puede resolver sigue siendo un problema que la
         * investigacion necesita contar.
         */
        if (! $room->self_service) {
            return redirect()->route('teacher.escalate');
        }

        return redirect()->route('teacher.category');
    }

    /**
     * Busqueda directa por codigo: via alternativa a la cascada para quien
     * ya sabe en que aula esta (plan CU-D-04).
     */
    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('teacher.search', [
            'term' => $term,
            'results' => $term === '' ? collect() : $this->catalog->searchByCode($term),
        ]);
    }
}
