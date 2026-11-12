# ADR-002 — Pantalla de inicio por código de aula

**Fecha:** 2026-11-11
**Estado:** propuesta (pendiente de medir)
**Contexto:** adaptación de la interfaz del docente (taller de investigación, 10.º ciclo).

## Qué cambia

La pantalla de `/reportar` ya no pide sede, pabellón, piso y aula en cuatro listas.
Ahora pide **un solo dato: el código del aula** (el que está en la puerta). Mientras
el docente escribe, aparecen las coincidencias y, al tocar una, pasa a la
confirmación explícita de siempre.

La cascada anterior se conserva en `/reportar/lista` (ruta `teacher.browse`) como
alternativa para quien no sabe su código.

## Por qué

El código de un aula ya identifica su sede, su pabellón y su piso. Pedirlos aparte
obliga a contestar tres preguntas cuya respuesta está en la cuarta. El objetivo es
reducir el tiempo hasta confirmar la ubicación y el abandono en ese paso.

## Qué no cambia

- La confirmación explícita del aula y la revalidación completa en el servidor.
- El diseño visual: mismos componentes, colores y formato de celular.
- La política de seguridad de contenido: el comportamiento vive en
  `resources/js/room-search.js` con atributos `data-*`, sin JavaScript incrustado.
- Sin JavaScript el formulario sigue funcionando (GET a `teacher.search`).

## Hipótesis a contrastar

Escribir el código del aula reduce el tiempo hasta confirmar la ubicación y la tasa
de abandono frente a la selección en cascada.

## Pendiente

Registrar el momento en que el docente abre la pantalla de inicio, para poder medir
el tiempo previo a la confirmación (hoy el reloj arranca al confirmar el aula).
