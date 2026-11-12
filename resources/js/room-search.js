/*
 * Sugerencias de aula mientras el docente escribe el codigo.
 *
 * El indice de aulas llega ya dentro del HTML (atributo data-rooms), asi que
 * escribir no hace ninguna peticion al servidor: en la red de un aula, justo
 * cuando algo acaba de fallar, cada ida y vuelta es una espera de mas.
 *
 * El HTML solo declara intencion con atributos data-*: la politica de
 * seguridad de contenido (CSP) del sistema no permite JavaScript incrustado.
 *
 * Si algo falla aqui, el formulario sigue siendo un GET a la busqueda del
 * servidor: el docente nunca queda sin camino.
 */

const MIN_CHARS = 2;
const MAX_RESULTS = 6;

const root = document.querySelector('[data-room-search]');

if (root) {
    let rooms = [];

    try {
        rooms = JSON.parse(root.dataset.rooms || '[]');
    } catch (error) {
        rooms = [];
    }

    const input = root.querySelector('[data-room-input]');
    const form = root.querySelector('[data-room-form]');
    const results = root.querySelector('[data-room-results]');
    const more = root.querySelector('[data-room-more]');
    const empty = root.querySelector('[data-room-empty]');
    const template = root.querySelector('[data-room-template]');

    // Sin espacios y en mayusculas: "c 305" y "C305" son la misma aula.
    const normalize = (text) => text.toUpperCase().replace(/\s+/g, '');

    let visible = [];

    const render = () => {
        const term = normalize(input.value);

        results.replaceChildren();
        more.hidden = true;
        empty.hidden = true;
        visible = [];

        if (term.length < MIN_CHARS) {
            return;
        }

        const matches = rooms.filter((room) => normalize(room.c).startsWith(term));

        visible = matches.slice(0, MAX_RESULTS);

        if (matches.length === 0) {
            empty.hidden = false;

            return;
        }

        visible.forEach((room) => {
            const option = template.content.firstElementChild.cloneNode(true);

            option.href = room.u;
            option.querySelector('[data-room-code]').textContent = room.c;
            option.querySelector('[data-room-place]').textContent = room.p || '';

            results.appendChild(option);
        });

        more.hidden = matches.length <= MAX_RESULTS;
    };

    if (input && form && results && more && empty && template && rooms.length > 0) {
        input.addEventListener('input', render);

        // Con una sola coincidencia, Enter va directo a confirmar. Con mas de
        // una, o con ninguna, se deja el envio normal al servidor.
        form.addEventListener('submit', (event) => {
            if (visible.length === 1) {
                event.preventDefault();
                window.location.assign(visible[0].u);
            }
        });

        // El navegador puede haber rellenado el campo al volver atras.
        render();
    }
}
