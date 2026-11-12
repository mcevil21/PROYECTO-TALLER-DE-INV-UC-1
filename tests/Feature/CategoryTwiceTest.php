<?php

declare(strict_types=1);

use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Elegir el problema dos veces seguidas no debe dar un error 500.
 *
 * Pasa en la vida real cuando el docente vuelve atras con el navegador y
 * toca otra vez un boton, o cuando toca dos veces. La incidencia ya esta en
 * diagnostico y reiniciarlo era una transicion invalida.
 */
it('elegir el problema por segunda vez devuelve al diagnostico en vez de fallar', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    $site = Site::create(['code' => 'HYO', 'name' => 'Huancayo', 'is_active' => true]);
    $building = Building::create(['site_id' => $site->id, 'code' => 'C', 'name' => 'Pabellón C', 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'label' => 'Piso 3', 'is_active' => true]);
    $room = Room::create(['floor_id' => $floor->id, 'code' => 'C305', 'criticality' => 1, 'is_active' => true]);
    $category = IncidentCategory::where('code', 'PROJECTOR')->firstOrFail();

    $ubicacion = [
        'site_id' => (string) $site->id,
        'building_id' => (string) $building->id,
        'floor_id' => (string) $floor->id,
        'room_id' => (string) $room->id,
    ];

    $this->get(route('teacher.start'))->assertOk();
    $this->post(route('teacher.locate'), $ubicacion)->assertRedirect();
    $this->post(route('teacher.confirm.store'), $ubicacion)->assertRedirect(route('teacher.category'));

    $envio = ['category_id' => (string) $category->id, 'description' => 'No se ve la imagen'];

    $this->post(route('teacher.category.store'), $envio)->assertRedirect();
    $this->post(route('teacher.category.store'), $envio)->assertRedirect(route('teacher.diagnostic'));
});
