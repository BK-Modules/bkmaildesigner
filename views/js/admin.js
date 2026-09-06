/**
 * Pantalla del módulo y editor de bloques.
 *
 * Sin dependencias: el back office de 1.7.6 y el de 9.x no comparten ni versión de jQuery ni
 * componentes, así que las pestañas, el arrastrar y soltar y el texto enriquecido son propios.
 *
 * La vista previa la pinta siempre el servidor con el mismo motor que envía los correos: el
 * editor manda su estado sin guardar y recibe el HTML final. Así lo que se ve es lo que sale.
 */
(function () {
    'use strict';

    /**
     * El back office sirve este fichero en la cabecera, antes del <script> de la plantilla que
     * publica las URLs: se leen al llamar, nunca al cargar.
     */
    function base() {
        return window.bkmdBase || {};
    }

    function post(action, data, cb, asText) {
        var body = new FormData();
        body.append('action', action);
        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (value === undefined || value === null) {
                return;
            }
            if (value instanceof Blob) {
                body.append(key, value);
            } else if (Array.isArray(value) || typeof value === 'object') {
                Object.keys(value).forEach(function (sub) {
                    body.append(key + '[' + sub + ']', value[sub]);
                });
            } else {
                body.append(key, value);
            }
        });
        fetch(base().ajax, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) { return asText ? response.text() : response.json(); })
            .then(cb)
            .catch(function () { cb(null); });
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'text') {
                node.textContent = attrs[key];
            } else if (key === 'html') {
                node.innerHTML = attrs[key];
            } else if (key === 'on') {
                Object.keys(attrs.on).forEach(function (event) { node.addEventListener(event, attrs.on[event]); });
            } else if (attrs[key] !== null && attrs[key] !== undefined && attrs[key] !== false) {
                node.setAttribute(key, attrs[key]);
            }
        });
        (children || []).forEach(function (child) { if (child) { node.appendChild(child); } });
        return node;
    }

    function device(container) {
        var group = container.querySelector('[data-bkmd-device]');
        var frame = container.querySelector('[data-bkmd-frame]');
        if (!group || !frame) { return; }
        group.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-device]');
            if (!button) { return; }
            group.querySelectorAll('button').forEach(function (other) { other.classList.toggle('active', other === button); });
            frame.classList.toggle('is-mobile', button.getAttribute('data-device') === 'mobile');
        });
    }

    /**
     * Pinta en el iframe el HTML que devuelve el servidor. Se escribe con document.write y no con
     * srcdoc porque srcdoc no existe en los navegadores que aún abren un back office de 1.7.
     */
    function paint(frame, html) {
        var iframe = frame.querySelector('iframe');
        if (!iframe) { return; }
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(html || '');
        doc.close();
    }

    // ---- pestañas de la pantalla principal -------------------------------------------------------

    function tabs() {
        var items = [].slice.call(document.querySelectorAll('.bkmd-nav__item'));
        var panes = [].slice.call(document.querySelectorAll('.bkmd .tab-pane'));
        if (!items.length) { return; }

        function show(hash) {
            var found = false;
            items.forEach(function (item) {
                var on = item.getAttribute('href') === hash;
                item.classList.toggle('is-active', on);
                found = found || on;
            });
            if (!found) { return show(items[0].getAttribute('href')); }
            panes.forEach(function (pane) { pane.classList.toggle('active', '#' + pane.id === hash); });
        }

        items.forEach(function (item) {
            item.addEventListener('click', function (event) {
                event.preventDefault();
                var hash = item.getAttribute('href');
                if (window.history.replaceState) {
                    window.history.replaceState(null, '', hash);
                } else {
                    window.location.hash = hash;
                }
                show(hash);
            });
        });
        show(window.location.hash || items[0].getAttribute('href'));
    }

    // ---- listado ---------------------------------------------------------------------------------

    function list() {
        var table = document.querySelector('.bkmd-table');
        if (!table) { return; }
        var search = document.querySelector('[data-bkmd-search]');
        var filter = document.querySelector('[data-bkmd-filter]');
        var origin = document.querySelector('[data-bkmd-origin]');
        // El filtro viaja como "atributo:valor", así que la misma botonera sirve para cualquier
        // atributo de la fila sin dos listas de casos en el código.
        var criteria = '';

        function refresh() {
            var needle = (search && search.value || '').toLowerCase().trim();
            var parts = criteria ? criteria.split(':') : null;
            var source = origin ? origin.value : '';
            var visibleByGroup = {};
            table.querySelectorAll('.bkmd-row').forEach(function (row) {
                var hit = (!needle || row.getAttribute('data-search').toLowerCase().indexOf(needle) !== -1)
                    && (!source || row.getAttribute('data-module') === source)
                    && (!parts || row.getAttribute('data-' + parts[0]) === parts[1]);
                row.classList.toggle('is-hidden', !hit);
                var group = row.getAttribute('data-module');
                visibleByGroup[group] = visibleByGroup[group] || hit;
            });
            table.querySelectorAll('.bkmd-group').forEach(function (row) {
                row.classList.toggle('is-hidden', !visibleByGroup[row.getAttribute('data-group')]);
            });
            refreshSelection();
        }

        if (search) { search.addEventListener('input', refresh); }
        if (origin) { origin.addEventListener('change', refresh); }
        if (filter) {
            filter.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-filter]');
                if (!button) { return; }
                criteria = button.getAttribute('data-filter');
                filter.querySelectorAll('button').forEach(function (other) { other.classList.toggle('active', other === button); });
                refresh();
            });
        }

        // ---- selección y cambio de modo en bloque
        var selbar = document.querySelector('[data-bkmd-selbar]');
        var selcount = document.querySelector('[data-bkmd-selcount]');
        var selall = document.querySelector('[data-bkmd-selall]');

        function picked() {
            return [].slice.call(table.querySelectorAll('.bkmd-row:not(.is-hidden) [data-bkmd-pick]:checked'))
                .map(function (box) { return box.closest('.bkmd-row'); });
        }

        function refreshSelection() {
            var rows = picked();
            var words = window.bkmdSelWords || {};
            selbar.hidden = rows.length === 0;
            selcount.textContent = rows.length === 1
                ? (words.one || '1')
                : (words.many || '%n%').replace('%n%', rows.length);
            if (selall) {
                var visible = table.querySelectorAll('.bkmd-row:not(.is-hidden) [data-bkmd-pick]').length;
                selall.checked = visible > 0 && rows.length === visible;
                selall.indeterminate = rows.length > 0 && rows.length < visible;
            }
        }

        table.addEventListener('change', function (event) {
            if (event.target.closest('[data-bkmd-pick]')) { refreshSelection(); }
        });

        if (selall) {
            selall.addEventListener('change', function () {
                table.querySelectorAll('.bkmd-row:not(.is-hidden) [data-bkmd-pick]').forEach(function (box) {
                    box.checked = selall.checked;
                });
                refreshSelection();
            });
        }

        document.querySelector('[data-bkmd-selnone]').addEventListener('click', function () {
            table.querySelectorAll('[data-bkmd-pick]').forEach(function (box) { box.checked = false; });
            refreshSelection();
        });

        selbar.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bkmd-setmode]');
            if (!button) { return; }
            var rows = picked();
            if (!rows.length) { return; }
            setMode(rows.map(function (row) { return row.getAttribute('data-key'); }), button.getAttribute('data-bkmd-setmode'), button);
        });

        // Restaurar una fila suelta: un clic y esa plantilla vuelve al modo con el que vino
        table.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bkmd-advice]');
            if (!button) { return; }
            var row = button.closest('.bkmd-row');
            setMode([row.getAttribute('data-key')], button.getAttribute('data-bkmd-advice'), button);
        });

        function setMode(keys, mode, button) {
            button.disabled = true;
            post('BkBulkMode', { mode: mode, keys: keys }, function (response) {
                button.disabled = false;
                if (response && response.ok) { window.location.reload(); }
            });
        }

        table.addEventListener('change', function (event) {
            var select = event.target.closest('[data-bkmd-mode]');
            if (!select) { return; }
            var row = select.closest('.bkmd-row');
            var value = select.value;
            var data = {
                bk_module: row.getAttribute('data-module'),
                bk_name: row.getAttribute('data-name')
            };
            if (value === 'original') { data.active = 0; } else { data.mode = value; data.active = 1; }
            select.disabled = true;
            post('BkToggle', data, function (response) {
                select.disabled = false;
                if (response && response.ok) { row.setAttribute('data-state', value); }
            });
        });

        table.addEventListener('click', function (event) {
            var row = event.target.closest('.bkmd-row');
            if (!row) { return; }
            if (event.target.closest('[data-bkmd-preview]')) {
                openPreview(row.getAttribute('data-module'), row.getAttribute('data-name'));
            } else if (event.target.closest('[data-bkmd-row-test]')) {
                openTest(row.getAttribute('data-module'), row.getAttribute('data-name'));
            }
        });

        var check = document.querySelector('[data-bkmd-check]');
        if (check) {
            check.addEventListener('click', function () {
                var box = document.querySelector('[data-bkmd-checkbox]');
                check.disabled = true;
                box.hidden = false;
                box.textContent = '…';
                post('BkCheck', {}, function (response) {
                    check.disabled = false;
                    if (!response || !response.ok) { box.hidden = true; return; }
                    report(box, response.rows);
                });
            });
        }

        /**
         * Marca cada fila con la lectura que el modo con marca hace de su plantilla y resume
         * cuántas se reconocen por su marcación y cuántas dependen de la lectura genérica.
         */
        function report(box, rows) {
            var known = 0, generic = 0, none = [];
            rows.forEach(function (row) {
                var kind = row.strategy === 'none' ? 'none' : (row.strategy === 'generic' ? 'generic' : 'known');
                if (kind === 'known') { known += 1; } else if (kind === 'generic') { generic += 1; } else { none.push(row.key); }
            });
            box.className = 'bkmd-checkbox' + (none.length ? ' is-warn' : '');
            box.innerHTML = '';
            box.appendChild(el('div', { text: bkmdCheckWords.summary.replace('%known%', known).replace('%generic%', generic).replace('%none%', none.length) }));
            if (none.length) {
                var list = el('ul', {});
                none.forEach(function (key) { list.appendChild(el('li', { text: key })); });
                box.appendChild(list);
            }
        }

        // Una acción en bloque toca las 51 plantillas de golpe: nunca se dispara con un solo clic
        document.querySelectorAll('[data-bkmd-bulk]').forEach(function (button) {
            button.addEventListener('click', function () {
                var what = button.getAttribute('data-bkmd-bulk');
                if (!window.confirm((window.bkmdBulkWords || {})[what] || '')) { return; }
                button.disabled = true;
                if (what === 'restore') {
                    var all = [].slice.call(table.querySelectorAll('.bkmd-row')).map(function (row) {
                        return row.getAttribute('data-key');
                    });
                    setMode(all, 'restore', button);

                    return;
                }
                post('BkBulk', {}, function () { window.location.reload(); });
            });
        });
    }

    /**
     * Vista previa de una plantilla desde el listado, con selector de idioma.
     */
    function openPreview(module, name) {
        var modal = document.querySelector('[data-bkmd-modal]');
        if (!modal) { return; }
        var frame = modal.querySelector('[data-bkmd-frame]');
        var langs = modal.querySelector('[data-bkmd-langs]');
        var title = modal.querySelector('[data-bkmd-modal-title]');
        var current = (window.bkmdLangs || [])[0];
        title.textContent = (module ? module + ' / ' : '') + name;

        function load(lang) {
            paint(frame, '');
            post('BkPreview', { bk_module: module, bk_name: name, id_lang: lang.id_lang }, function (html) {
                paint(frame, html || '');
            }, true);
        }

        langs.innerHTML = '';
        (window.bkmdLangs || []).forEach(function (lang, index) {
            var button = el('button', {
                type: 'button',
                'class': 'btn btn-default' + (index === 0 ? ' active' : ''),
                text: lang.iso_code.toUpperCase(),
                on: {
                    click: function () {
                        langs.querySelectorAll('button').forEach(function (other) { other.classList.toggle('active', other === button); });
                        load(lang);
                    }
                }
            });
            langs.appendChild(button);
        });

        modal.hidden = false;
        if (current) { load(current); }
    }

    /**
     * Envío de prueba de una plantilla desde el listado, sin pasar por el editor.
     */
    function openTest(module, name) {
        var modal = document.querySelector('[data-bkmd-test-modal]');
        var send = modal.querySelector('[data-bkmd-test-send]');
        var status = modal.querySelector('[data-bkmd-test-status]');
        var what = modal.querySelector('[data-bkmd-test-what]');
        var toggle = modal.querySelector('[data-bkmd-adv-toggle]');
        var body = modal.querySelector('[data-bkmd-adv-body]');
        var list = modal.querySelector('[data-bkmd-adv-samples]');
        if (!modal || !send) { return; }

        what.textContent = (module ? module + ' / ' : '') + name;
        status.textContent = '';
        status.className = 'bkmd-test-status';
        body.hidden = true;
        toggle.classList.remove('is-open');
        list.innerHTML = '';
        modal.hidden = false;

        // Las variables se piden la primera vez que se abren las opciones: son de esta plantilla
        var loaded = false;
        toggle.onclick = function () {
            body.hidden = !body.hidden;
            toggle.classList.toggle('is-open', !body.hidden);
            if (body.hidden || loaded) { return; }
            loaded = true;
            list.textContent = '…';
            post('BkSamples', { bk_module: module, bk_name: name }, function (response) {
                list.innerHTML = '';
                if (!response || !response.ok) { return; }
                response.groups.forEach(function (group) {
                    list.appendChild(el('div', { 'class': 'bkmd-vargroup__hd', text: response.names[group.key] || group.key }));
                    group.rows.forEach(function (row) {
                        var field = el('div', { 'class': 'bkmd-sample' + (row.unknown ? ' is-unknown' : '') }, [
                            el('label', {}, [el('code', { text: '{' + row.name + '}' })])
                        ]);
                        if (row.html) {
                            field.appendChild(el('span', { 'class': 'bkmd-sample__html', text: (window.bkmdSampleWords || {}).html || '' }));
                        } else {
                            field.appendChild(el('input', {
                                type: 'text', 'class': 'form-control', value: row.value,
                                'data-bkmd-adv-sample': row.name
                            }));
                        }
                        list.appendChild(field);
                    });
                });
            });
        };

        send.onclick = function () {
            var email = document.getElementById('bkmd-test-email').value;
            var lang = document.getElementById('bkmd-test-lang').value;
            send.disabled = true;
            status.className = 'bkmd-test-status';
            status.textContent = '…';

            var samples = {};
            list.querySelectorAll('[data-bkmd-adv-sample]').forEach(function (input) {
                samples[input.getAttribute('data-bkmd-adv-sample')] = input.value;
            });

            var fire = function () {
                post('BkSendTest', { bk_module: module, bk_name: name, id_lang: lang, email: email }, function (response) {
                    send.disabled = false;
                    var ok = response && response.ok;
                    status.className = 'bkmd-test-status ' + (ok ? 'is-ok' : 'is-error');
                    status.textContent = ok ? '✓ ' + email : '✕';
                });
            };

            // Lo que se haya tocado se guarda antes de enviar: el correo de prueba lo usa
            if (Object.keys(samples).length) {
                post('BkSaveSamples', { samples: samples }, fire);
            } else {
                fire();
            }
        };
    }

    function modals() {
        document.querySelectorAll('[data-bkmd-modal], [data-bkmd-test-modal]').forEach(function (modal) {
            device(modal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.closest('[data-bkmd-modal-close], [data-bkmd-test-close]')) {
                    modal.hidden = true;
                }
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.bkmd-modal').forEach(function (modal) { modal.hidden = true; });
            }
        });
    }

    // ---- diseño del layout -----------------------------------------------------------------------

    function layout() {
        var form = document.getElementById('bkmd-layout-form');
        if (!form) { return; }

        var frame = form.querySelector('[data-bkmd-frame]');
        var picker = form.querySelector('[data-bkmd-preview-tpl]');
        device(form.querySelector('.bkmd-layout__preview'));

        subnav(form);
        themes(document.getElementById('bkmd-tab-layout'));

        form.querySelectorAll('[data-bkmd-links]').forEach(linkEditor);
        form.querySelectorAll('[data-bkmd-social]').forEach(socialEditor);

        // El anclaje no basta: las pestañas son propias y se abren con un clic en su botón
        var goSettings = form.querySelector('[data-bkmd-goto-settings]');
        if (goSettings) {
            goSettings.addEventListener('click', function (event) {
                event.preventDefault();
                var tab = document.querySelector('.bkmd-nav__item[href="#bkmd-tab-settings"]');
                if (tab) {
                    tab.click();
                    tab.scrollIntoView({ block: 'start' });
                }
            });
        }
        var logoField = form.querySelector('#bkmd-logo-url');
        if (logoField) {
            logoField.parentNode.insertBefore(uploadButton(function (url) {
                logoField.value = url;
                logoField.dispatchEvent(new Event('input', { bubbles: true }));
            }), logoField.nextSibling);
        }

        // Las pestañas de idioma gobiernan todos los paneles del formulario a la vez
        form.querySelectorAll('[data-bkmd-langtabs]').forEach(function (group) {
            group.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-lang]');
                if (!button) { return; }
                var lang = button.getAttribute('data-lang');
                form.querySelectorAll('[data-bkmd-langtabs] button').forEach(function (other) {
                    other.classList.toggle('active', other.getAttribute('data-lang') === lang);
                });
                form.querySelectorAll('.bkmd-langpane').forEach(function (pane) {
                    pane.hidden = pane.getAttribute('data-lang') !== lang;
                });
                refresh();
            });
        });

        var timer = null;
        function refresh() {
            if (timer) { window.clearTimeout(timer); }
            timer = window.setTimeout(function () {
                var active = form.querySelector('[data-bkmd-langtabs] button.active');
                var idLang = active ? active.getAttribute('data-lang') : '';
                var key = (picker && picker.value || '/').split('/');
                var data = new FormData(form);
                data.append('action', 'BkPreview');
                data.append('bk_module', key[0]);
                data.append('bk_name', key[1]);
                data.append('mark', '1');
                if (idLang) { data.append('id_lang', idLang); }
                fetch(base().ajax, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (response) { return response.text(); })
                    .then(function (html) { paint(frame, html); zones(frame, form); })
                    .catch(function () {});
            }, 350);
        }

        // Guardar sin recargar: la vista previa se queda donde está y se sigue trabajando
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = form.querySelector('button[type="submit"]');
            var status = form.querySelector('[data-bkmd-layout-status]');
            var words = window.bkmdLayoutWords || {};
            var data = new FormData(form);
            data.append('action', 'BkSaveLayout');
            button.disabled = true;
            status.className = 'bkmd-editor__status';
            status.textContent = words.saving || '';
            fetch(base().ajax, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (out) {
                    button.disabled = false;
                    var ok = out && out.ok;
                    status.className = 'bkmd-editor__status ' + (ok ? 'is-ok' : 'is-error');
                    status.textContent = ok ? words.saved : words.error;
                })
                .catch(function () {
                    button.disabled = false;
                    status.className = 'bkmd-editor__status is-error';
                    status.textContent = words.error || '';
                });
        });

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        // Los segmentos son radios dentro de botones: hay que marcar el botón activo a mano
        form.addEventListener('change', function (event) {
            var radio = event.target.closest('.bkmd-seg input[type="radio"]');
            if (!radio) { return; }
            form.querySelectorAll('input[name="' + radio.name + '"]').forEach(function (other) {
                other.closest('.btn').classList.toggle('active', other === radio);
            });
        });
        refresh();
    }

    /**
     * Pestañas del panel de ajustes del diseño. La vista previa se queda siempre a la vista; lo
     * que cambia es qué grupo de mandos se está tocando.
     */
    function subnav(form) {
        var nav = form.querySelector('[data-bkmd-subnav]');
        if (!nav) { return; }
        var panes = [].slice.call(form.querySelectorAll('.bkmd-subpane'));

        nav.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-pane]');
            if (!button) { return; }
            var name = button.getAttribute('data-pane');
            nav.querySelectorAll('button').forEach(function (other) {
                other.classList.toggle('is-active', other === button);
            });
            panes.forEach(function (pane) { pane.hidden = pane.getAttribute('data-pane') !== name; });
        });
    }

    /**
     * Galería de temas: cada tarjeta pinta un correo real de la tienda con ese tema, pedido al
     * mismo motor que envía. Se cargan al abrir la pestaña, no al cargar la página: son cinco
     * renderizados completos y en la pestaña de correos no se ven.
     */
    function themes(pane) {
        var gallery = pane.querySelector('[data-bkmd-themes]');
        if (!gallery) { return; }
        var key = (gallery.getAttribute('data-tpl') || '/').split('/');
        var loaded = false;

        function fill() {
            if (loaded) { return; }
            loaded = true;
            gallery.querySelectorAll('.bkmd-theme').forEach(function (card) {
                var frame = card.querySelector('iframe');
                post('BkPreview', {
                    bk_module: key[0],
                    bk_name: key[1],
                    bk_theme: card.getAttribute('data-theme')
                }, function (html) {
                    var doc = frame.contentDocument || frame.contentWindow.document;
                    doc.open();
                    doc.write(html || '');
                    doc.close();
                }, true);
            });
        }

        gallery.addEventListener('click', function (event) {
            var card = event.target.closest('.bkmd-theme');
            if (!card || card.classList.contains('is-busy')) { return; }
            card.classList.add('is-busy');
            post('BkApplyTheme', { bk_theme: card.getAttribute('data-theme') }, function (response) {
                if (response && response.ok) {
                    window.location.hash = '#bkmd-tab-layout';
                    window.location.reload();
                } else {
                    card.classList.remove('is-busy');
                }
            });
        });

        // La pestaña de diseño puede venir abierta en el ancla, o abrirse después
        if (document.getElementById('bkmd-tab-layout').classList.contains('active')) { fill(); }
        document.querySelectorAll('.bkmd-nav__item[href="#bkmd-tab-layout"]').forEach(function (item) {
            item.addEventListener('click', fill);
        });
    }

    /**
     * La vista previa lleva al ajuste: cada zona del correo se marca al pasar por encima y un
     * clic abre la pestaña que la gobierna. Se navega mirando el correo, no leyendo pestañas.
     */
    function zones(frame, form) {
        var iframe = frame.querySelector('iframe');
        var doc = iframe && (iframe.contentDocument || iframe.contentWindow.document);
        if (!doc || !doc.body) { return; }

        var css = doc.createElement('style');
        css.textContent = '[data-bk-zone]{cursor:pointer;position:relative}'
            + '[data-bk-zone]:hover{outline:2px solid rgba(0,118,158,.65);outline-offset:-2px}';
        doc.body.appendChild(css);

        doc.body.addEventListener('click', function (event) {
            var cell = event.target.closest ? event.target.closest('[data-bk-zone]') : null;
            if (!cell) { return; }
            event.preventDefault();
            var button = form.querySelector('[data-bkmd-subnav] [data-pane="' + cell.getAttribute('data-bk-zone') + '"]');
            if (button) {
                button.click();
                button.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    /**
     * Editor de una lista de enlaces (etiqueta + destino) que se guarda como JSON en un campo oculto.
     */
    function linkEditor(container) {
        var field = container.getAttribute('data-bkmd-links');
        var words = window.bkmdLinkWords || {};
        var items = [];
        try { items = JSON.parse(container.getAttribute('data-links') || '[]') || []; } catch (e) { items = []; }
        var hidden = el('input', { type: 'hidden', name: field });
        var rows = el('div', { 'class': 'bkmd-links__rows' });
        var foot = el('div', { 'class': 'bkmd-links__foot' });

        // Los destinos habituales se eligen por su nombre; la URL a mano es la última opción, no
        // la primera, porque el 90 % de los enlaces de un correo son páginas de la propia tienda.
        var destinations = [
            { value: '{shop_url}', label: words.home },
            { value: '{my_account_url}', label: words.account },
            { value: '{history_url}', label: words.orders },
            { value: '{guest_tracking_url}', label: words.tracking },
            { value: '{contact_url}', label: words.contact },
            { value: '', label: words.custom }
        ];

        function sync() {
            hidden.value = JSON.stringify(items);
            container.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function isPreset(url) {
            return destinations.some(function (d) { return d.value !== '' && d.value === url; });
        }

        function draw() {
            rows.innerHTML = '';
            foot.innerHTML = '';

            if (!items.length) {
                rows.appendChild(el('div', { 'class': 'bkmd-links__empty', text: words.empty }));
            }

            items.forEach(function (item, index) {
                var custom = !isPreset(item.url);
                var url = el('input', {
                    type: 'text', 'class': 'form-control', value: item.url || '', placeholder: 'https://',
                    hidden: custom ? null : 'hidden',
                    on: { input: function (e) { items[index].url = e.target.value; sync(); } }
                });
                var select = el('select', {
                    'class': 'form-control',
                    on: {
                        change: function (e) {
                            var picked = e.target.value;
                            items[index].url = picked;
                            url.hidden = picked !== '';
                            url.value = picked === '' ? '' : picked;
                            if (picked === '') { url.focus(); }
                            sync();
                        }
                    }
                });
                destinations.forEach(function (d) {
                    var option = el('option', { value: d.value, text: d.label });
                    if ((custom && d.value === '') || (!custom && d.value === item.url)) { option.selected = true; }
                    select.appendChild(option);
                });

                rows.appendChild(el('div', { 'class': 'bkmd-link-row' }, [
                    el('input', {
                        type: 'text', 'class': 'form-control', value: item.label || '', placeholder: words.label_hint,
                        on: { input: function (e) { items[index].label = e.target.value; sync(); } }
                    }),
                    el('div', { 'class': 'bkmd-link-row__dest' }, [select, url]),
                    el('button', {
                        type: 'button', 'class': 'btn btn-default bkmd-link-row__remove', title: words.remove,
                        html: '<i class="icon-trash"></i>',
                        on: { click: function () { items.splice(index, 1); draw(); sync(); } }
                    })
                ]));
            });

            if (items.length < 6) {
                foot.appendChild(el('button', {
                    type: 'button', 'class': 'btn btn-default btn-sm',
                    html: '<i class="icon-plus"></i> ' + (words.add || '+'),
                    on: { click: function () { items.push({ label: '', url: '{shop_url}' }); draw(); sync(); } }
                }));
            }
        }

        container.appendChild(el('div', { 'class': 'bkmd-links__head' }, [
            el('span', { text: words.col_label }),
            el('span', { text: words.col_dest }),
            el('span', {})
        ]));
        container.appendChild(rows);
        container.appendChild(foot);
        container.appendChild(hidden);
        draw();
        hidden.value = JSON.stringify(items);
    }

    /**
     * Botón que sube una imagen y devuelve su dirección: el comerciante no tiene por qué saber
     * dónde alojar un icono ni pegar una URL.
     *
     * @param {function} onDone recibe la URL de la imagen subida
     */
    function uploadButton(onDone) {
        var words = window.bkmdUploadWords || {};
        var picker = el('input', { type: 'file', accept: 'image/*', hidden: 'hidden' });
        var button = el('button', {
            type: 'button', 'class': 'btn btn-default bkmd-upload',
            html: '<i class="icon-upload"></i> ' + (words.pick || '…'),
            on: { click: function () { picker.click(); } }
        });
        picker.addEventListener('change', function () {
            if (!picker.files || !picker.files[0]) { return; }
            button.disabled = true;
            button.innerHTML = words.sending || '…';
            post('BkUpload', { file: picker.files[0] }, function (response) {
                button.disabled = false;
                button.innerHTML = '<i class="icon-upload"></i> ' + (words.pick || '…');
                picker.value = '';
                if (response && response.ok) {
                    onDone(response.url);
                } else {
                    window.alert((response && response.error) || words.failed || '');
                }
            });
        });

        return el('span', { 'class': 'bkmd-upload__wrap' }, [button, picker]);
    }

    /**
     * Editor de las redes sociales: red, dirección y, para una red propia, su imagen. Se guarda
     * como JSON en un campo oculto, igual que los enlaces.
     */
    function socialEditor(container) {
        var field = container.getAttribute('data-bkmd-social');
        var words = window.bkmdSocialWords || {};
        var networks = {};
        var items = [];
        try { networks = JSON.parse(container.getAttribute('data-networks') || '{}') || {}; } catch (e) { networks = {}; }
        try { items = JSON.parse(container.getAttribute('data-rows') || '[]') || []; } catch (e) { items = []; }
        if (!Array.isArray(items)) { items = []; }
        var hidden = el('input', { type: 'hidden', name: field });
        var rows = el('div', { 'class': 'bkmd-links__rows' });
        var foot = el('div', { 'class': 'bkmd-links__foot' });

        function sync() {
            hidden.value = JSON.stringify(items);
            container.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function draw() {
            rows.innerHTML = '';
            foot.innerHTML = '';
            if (!items.length) {
                rows.appendChild(el('div', { 'class': 'bkmd-links__empty', text: words.empty }));
            }

            items.forEach(function (item, index) {
                var own = item.network === 'custom';
                var select = el('select', {
                    'class': 'form-control',
                    on: { change: function (e) { items[index].network = e.target.value; draw(); sync(); } }
                });
                Object.keys(networks).forEach(function (key) {
                    var option = el('option', { value: key, text: networks[key].name });
                    if (key === item.network) { option.selected = true; }
                    select.appendChild(option);
                });
                var other = el('option', { value: 'custom', text: words.custom });
                if (own) { other.selected = true; }
                select.appendChild(other);

                var preview = (own && item.icon)
                    ? el('img', { 'class': 'bkmd-social__mark', src: item.icon, alt: '' })
                    : (own
                        ? el('span', { 'class': 'bkmd-social__mark bkmd-social__mark--own' })
                        : el('img', { 'class': 'bkmd-social__mark', src: words.base + item.network + '-c.png', alt: '' }));

                var head = el('div', { 'class': 'bkmd-social-row' + (own ? ' is-own' : '') }, [
                    preview,
                    select,
                    el('div', { 'class': 'bkmd-social-row__fields' }, [
                        el('input', {
                            type: 'url', 'class': 'form-control', value: item.url || '', placeholder: words.url,
                            on: { input: function (e) { items[index].url = e.target.value; sync(); } }
                        })
                    ]),
                    el('button', {
                        type: 'button', 'class': 'btn btn-default bkmd-link-row__remove', title: words.remove,
                        html: '<i class="icon-trash"></i>',
                        on: { click: function () { items.splice(index, 1); draw(); sync(); } }
                    })
                ]);
                rows.appendChild(head);

                if (!own) { return; }

                // Una red propia necesita su imagen, su nombre y decidir si va sobre un color:
                // con tres campos sueltos y sin etiqueta no se entendía cuál era cuál.
                var iconField = el('input', {
                    type: 'url', 'class': 'form-control', value: item.icon || '', placeholder: 'https://',
                    on: { input: function (e) { items[index].icon = e.target.value; preview.src = e.target.value; sync(); } }
                });
                var colorInput = el('input', {
                    type: 'color', value: item.color || '#000000',
                    on: { input: function (e) { items[index].color = e.target.value; sync(); } }
                });
                var colorCheck = el('input', {
                    type: 'checkbox',
                    on: {
                        change: function (e) {
                            colorInput.disabled = !e.target.checked;
                            items[index].color = e.target.checked ? colorInput.value : '';
                            sync();
                        }
                    }
                });
                colorCheck.checked = !!item.color;
                colorInput.disabled = !item.color;

                rows.appendChild(el('div', { 'class': 'bkmd-social-own' }, [
                    el('label', {}, [
                        el('span', { text: words.icon }),
                        el('div', { 'class': 'bkmd-withupload' }, [
                            iconField,
                            uploadButton(function (url) { items[index].icon = url; iconField.value = url; preview.src = url; sync(); })
                        ])
                    ]),
                    el('label', {}, [
                        el('span', { text: words.label }),
                        el('input', {
                            type: 'text', 'class': 'form-control', value: item.label || '', placeholder: 'Web',
                            on: { input: function (e) { items[index].label = e.target.value; sync(); } }
                        })
                    ]),
                    el('label', { 'class': 'bkmd-social-own__color' }, [
                        el('span', { text: words.color }),
                        el('span', { 'class': 'bkmd-social-own__pick' }, [colorCheck, colorInput,
                            el('span', { 'class': 'bkmd-social-own__hint', text: words.no_color })])
                    ]),
                    el('p', { 'class': 'bkmd-social-own__note', text: words.icon_hint })
                ]));
            });

            if (items.length < 10) {
                var used = items.map(function (i) { return i.network; });
                var free = Object.keys(networks).filter(function (k) { return used.indexOf(k) === -1; });
                foot.appendChild(el('button', {
                    type: 'button', 'class': 'btn btn-default btn-sm',
                    html: '<i class="icon-plus"></i> ' + (words.add || '+'),
                    on: {
                        click: function () {
                            items.push({ network: free.length ? free[0] : 'custom', url: '', icon: '', label: '' });
                            draw(); sync();
                        }
                    }
                }));
            }
        }

        container.appendChild(rows);
        container.appendChild(foot);
        container.appendChild(hidden);
        draw();
        hidden.value = JSON.stringify(items);
    }

    // ---- editor de plantilla ---------------------------------------------------------------------

    var BLOCKS = {
        heading: { fields: ['text', 'size', 'align'], summary: 'text' },
        text: { fields: ['html', 'align', 'tone'], summary: 'html' },
        button: { fields: ['text', 'url', 'align', 'style'], summary: 'text' },
        box: { fields: ['html', 'tone'], summary: 'html' },
        divider: { fields: [], summary: null },
        spacer: { fields: ['height'], summary: null },
        image: { fields: ['src', 'alt', 'url', 'width', 'align'], summary: 'alt' },
        media: { fields: ['src', 'alt', 'url', 'html', 'side', 'ratio'], summary: 'html' },
        hero: { fields: ['src', 'heading', 'html', 'btn_text', 'btn_url', 'align'], summary: 'heading' },
        social: { fields: ['align'], summary: null },
        columns: { fields: ['left', 'right'], summary: 'left' },
        order: { fields: [], summary: null },
        addresses: { fields: [], summary: null },
        html: { fields: ['rawhtml'], summary: 'html' }
    };

    function editor() {
        var root = document.querySelector('[data-bkmd-editor]');
        if (!root || !window.bkmdEditor) { return; }
        var cfg = window.bkmdEditor;
        var i18n = cfg.i18n;
        var frame = root.querySelector('[data-bkmd-frame]');
        var status = root.querySelector('[data-bkmd-status]');
        var blocklist = root.querySelector('[data-bkmd-blocklist]');
        var palette = root.querySelector('[data-bkmd-palette]');
        var props = root.querySelector('[data-bkmd-props]');
        var propsTitle = root.querySelector('[data-bkmd-props-title]');
        var subject = root.querySelector('[data-bkmd-subject]');
        var inherit = root.querySelector('[data-bkmd-inherit]');
        var copyRef = root.querySelector('[data-bkmd-copy-ref]');
        var langTabs = root.querySelector('[data-bkmd-editor-langs]');
        var previewType = 'html';
        var selected = -1;
        var dirty = false;

        var state = { mode: cfg.active ? cfg.mode : 'original', langs: {} };
        var refLang = null;
        cfg.languages.forEach(function (lang) {
            state.langs[lang.id] = { subject: lang.subject || '', blocks: (lang.blocks || []).slice() };
            if (lang.is_ref) { refLang = lang; }
        });
        var current = cfg.languages.filter(function (lang) { return lang.id === cfg.context_lang; })[0] || cfg.languages[0];

        blocklist.setAttribute('data-empty', i18n.empty);
        device(root);
        root.setAttribute('data-mode', state.mode);

        function mark(dirtyNow, message, level) {
            dirty = dirtyNow;
            status.className = 'bkmd-editor__status' + (level ? ' is-' + level : '');
            status.textContent = message || (dirtyNow ? i18n.unsaved : '');
        }

        // ---- idiomas
        function drawLangs() {
            langTabs.innerHTML = '';
            cfg.languages.forEach(function (lang) {
                var own = state.langs[lang.id].blocks.length > 0;
                var button = el('button', {
                    type: 'button',
                    'class': 'btn btn-default btn-xs' + (lang.id === current.id ? ' active' : '') + (!own && state.mode === 'designed' ? ' is-inherited' : ''),
                    html: lang.iso.toUpperCase() + (lang.is_ref ? '<span class="bkmd-ref">ref</span>' : ''),
                    on: { click: function () { current = lang; drawLangs(); load(); } }
                });
                langTabs.appendChild(button);
            });
        }

        function load() {
            subject.value = state.langs[current.id].subject;
            subject.placeholder = current.original_title || '';
            selected = -1;
            drawBlocks();
            drawProps();
            preview();
            var own = state.langs[current.id].blocks.length > 0;
            var canInherit = refLang && refLang.id !== current.id && state.langs[refLang.id].blocks.length > 0;
            inherit.hidden = !(state.mode === 'designed' && !own && canInherit);
            if (!inherit.hidden) {
                inherit.textContent = i18n.inherits.replace('%s', refLang.name);
            }
            if (copyRef) {
                copyRef.hidden = !(state.mode === 'designed' && !own && canInherit);
            }
        }

        // ---- bloques
        function blocks() { return state.langs[current.id].blocks; }

        function summaryOf(block) {
            var meta = BLOCKS[block.type];
            if (!meta || !meta.summary) { return ''; }
            var raw = block[meta.summary] || '';
            var text = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            return text.length > 60 ? text.slice(0, 60) + '…' : text;
        }

        function drawBlocks() {
            blocklist.innerHTML = '';
            blocklist.classList.toggle('is-empty', blocks().length === 0);
            blocks().forEach(function (block, index) {
                var item = el('li', {
                    'class': 'bkmd-blockitem' + (index === selected ? ' is-selected' : ''),
                    draggable: 'true',
                    'data-index': index
                }, [
                    el('span', { 'class': 'bkmd-blockitem__handle', html: '&#8942;&#8942;' }),
                    el('span', { 'class': 'bkmd-blockitem__type', text: i18n[block.type] || block.type }),
                    el('span', { 'class': 'bkmd-blockitem__summary', text: summaryOf(block) })
                ]);
                item.addEventListener('click', function () { selected = index; drawBlocks(); drawProps(); highlight(); });
                item.addEventListener('dragstart', function (event) {
                    event.dataTransfer.setData('text/plain', String(index));
                    item.classList.add('is-dragging');
                });
                item.addEventListener('dragend', function () { item.classList.remove('is-dragging'); });
                item.addEventListener('dragover', function (event) { event.preventDefault(); });
                item.addEventListener('drop', function (event) {
                    event.preventDefault();
                    var from = parseInt(event.dataTransfer.getData('text/plain'), 10);
                    if (isNaN(from) || from === index) { return; }
                    var moved = blocks().splice(from, 1)[0];
                    blocks().splice(index, 0, moved);
                    selected = index;
                    changed();
                });
                blocklist.appendChild(item);
            });
        }

        function changed() {
            mark(true);
            drawBlocks();
            drawProps();
            preview();
        }

        function add(type) {
            var block = { type: type };
            if (type === 'heading') { block.text = i18n.new_heading; block.size = 'h1'; block.align = 'left'; }
            if (type === 'text') { block.html = '<p>' + i18n.new_text + '</p>'; block.align = 'left'; block.tone = 'normal'; }
            if (type === 'button') { block.text = i18n.new_button; block.url = '{my_account_url}'; block.align = 'left'; block.style = ''; }
            if (type === 'box') { block.html = '<p>' + i18n.new_text + '</p>'; block.tone = 'neutral'; }
            if (type === 'spacer') { block.height = 16; }
            if (type === 'image') { block.src = ''; block.alt = ''; block.url = ''; block.width = 536; block.align = 'center'; }
            if (type === 'media') { block.src = ''; block.alt = ''; block.url = ''; block.html = '<p>' + i18n.new_text + '</p>'; block.side = 'left'; block.ratio = '40'; }
            if (type === 'social') { block.align = 'center'; }
            if (type === 'hero') {
                block.src = ''; block.url = ''; block.heading = i18n.new_heading;
                block.html = '<p>' + i18n.new_text + '</p>'; block.btn_text = i18n.new_button; block.btn_url = '{shop_url}';
                block.align = 'center'; block.height = 240; block.veil = 35; block.color = '';
            }
            if (type === 'columns') {
                block.count = '2'; block.left = '<p>' + i18n.new_text + '</p>'; block.right = '<p>' + i18n.new_text + '</p>';
                block.third = ''; block.ratio = '50'; block.align = 'left'; block.valign = 'top';
            }
            if (type === 'order') { block.labels = {}; block.source = 'products'; block.discount_source = 'discounts'; block.totals = true; block.layout = ''; block.show_image = ''; block.show_reference = ''; block.show_options = ''; block.show_unit = ''; block.image_size = 0; block.totals_mode = { subtotal: 'always', shipping: 'always', discounts: 'auto', tax: 'auto', total_paid: 'always' }; }
            if (type === 'addresses') { block.labels = {}; }
            if (type === 'html') { block.html = ''; }
            var at = selected >= 0 ? selected + 1 : blocks().length;
            blocks().splice(at, 0, block);
            selected = at;
            changed();
        }

        Object.keys(BLOCKS).forEach(function (type) {
            palette.appendChild(el('button', {
                type: 'button', text: i18n[type] || type,
                on: { click: function () { add(type); } }
            }));
        });

        // ---- propiedades del bloque activo
        function field(label, control) {
            return el('div', { 'class': 'bkmd-field' }, [el('label', { text: label }), control]);
        }

        /** Separador con título: divide el panel entre lo que dice y cómo se ve */
        function section(text) {
            return el('div', { 'class': 'bkmd-props__section', text: text });
        }

        /** Un campo de imagen con su botón de subida al lado */
        function withUpload(control, onUrl) {
            return el('div', { 'class': 'bkmd-withupload' }, [control, uploadButton(function (url) {
                control.value = url;
                onUrl(url);
            })]);
        }

        function input(value, onInput, type) {
            return el('input', {
                type: type || 'text', 'class': 'form-control', value: value === undefined ? '' : value,
                on: { input: function (event) { onInput(event.target.value); } }
            });
        }

        /**
         * Campo con su propio insertador de variables. Buscarlas en el panel de la izquierda
         * obliga a saber dónde está el cursor; aquí se elige la variable del campo que se está
         * escribiendo y entra donde estaba el cursor.
         */
        function withVars(control) {
            var target = control.tagName === 'INPUT' || control.tagName === 'TEXTAREA'
                ? control
                : control.querySelector('.bkmd-rte__area');
            var list = el('div', { 'class': 'bkmd-varmenu', hidden: 'hidden' });

            cfg.groups.forEach(function (group) {
                list.appendChild(el('div', { 'class': 'bkmd-varmenu__hd', text: cfg.group_names[group.key] || group.key }));
                group.rows.forEach(function (row) {
                    list.appendChild(el('button', {
                        type: 'button', text: '{' + row.name + '}',
                        title: row.html ? '' : row.value,
                        on: {
                            click: function () {
                                insertInto(target, '{' + row.name + '}');
                                list.hidden = true;
                            }
                        }
                    }));
                });
            });

            var toggle = el('button', {
                type: 'button', 'class': 'bkmd-varbtn', text: '{ }', title: i18n.var_hint,
                on: {
                    click: function () { list.hidden = !list.hidden; }
                }
            });

            return el('div', { 'class': 'bkmd-withvars' }, [control, toggle, list]);
        }

        /**
         * Inserta el texto donde está el cursor, tanto en un campo normal como en el área de
         * texto enriquecido.
         */
        function insertInto(target, text) {
            if (!target) { return; }
            target.focus();
            if (target.isContentEditable) {
                document.execCommand('insertText', false, text);
            } else {
                var start = target.selectionStart || 0;
                var end = target.selectionEnd || 0;
                target.value = target.value.slice(0, start) + text + target.value.slice(end);
                target.selectionStart = target.selectionEnd = start + text.length;
            }
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }

        /**
         * Color con su interruptor: apagado significa «el del tema», que es lo que hace que un
         * correo siga cambiando al cambiar de tema salvo donde se ha decidido otra cosa.
         */
        function colorField(label, value, onChange) {
            var input = el('input', {
                type: 'color', value: value || '#000000',
                on: { input: function (e) { onChange(e.target.value); } }
            });
            var check = el('input', {
                type: 'checkbox',
                on: {
                    change: function (e) {
                        input.disabled = !e.target.checked;
                        onChange(e.target.checked ? input.value : '');
                    }
                }
            });
            check.checked = !!value;
            input.disabled = !value;

            var hint = el('span', { 'class': 'bkmd-colorfield__hint', text: i18n.opt_theme });
            check.addEventListener('change', function () { hint.hidden = check.checked; });
            hint.hidden = !!value;

            return el('div', { 'class': 'bkmd-field bkmd-colorfield' }, [
                el('label', {}, [check, el('span', { text: label })]),
                hint,
                input
            ]);
        }

        function numberField(label, value, unit, onChange, min, max) {
            var input = el('input', {
                type: 'number', 'class': 'form-control', value: value || '',
                min: min === undefined ? 0 : min, max: max === undefined ? 200 : max,
                placeholder: (window.bkmdEditor.i18n || {}).auto,
                on: { input: function (e) { onChange(parseInt(e.target.value, 10) || 0); } }
            });

            return el('div', { 'class': 'bkmd-field' }, [
                el('label', { text: label }),
                el('div', { 'class': 'bkmd-unit' }, [input, el('span', { 'class': 'bkmd-unit__suffix', text: unit })])
            ]);
        }

        /**
         * Aire y fondo: los tiene cualquier bloque, así que van juntos y plegados al final.
         */
        function commonFields(block) {
            var body = el('div', { 'class': 'bkmd-common__body' }, [
                el('div', { 'class': 'bkmd-grid2' }, [
                    numberField(i18n.label_pad_top, block.pad_top, 'px', function (v) { block.pad_top = v; mark(true); preview(); }, 0, 80),
                    numberField(i18n.label_pad_bottom, block.pad_bottom, 'px', function (v) { block.pad_bottom = v; mark(true); preview(); }, 0, 80)
                ]),
                colorField(i18n.label_background, block.background, function (v) { block.background = v; mark(true); preview(); }),
                el('label', { 'class': 'bkmd-check' }, [
                    el('input', {
                        type: 'checkbox', checked: block.bleed ? 'checked' : null,
                        on: { change: function (event) { block.bleed = event.target.checked ? 1 : 0; mark(true); preview(); } }
                    }),
                    el('span', { text: i18n.label_bleed })
                ])
            ]);
            body.hidden = true;
            var toggle = el('button', {
                type: 'button', 'class': 'bkmd-common__toggle', text: i18n.label_common,
                on: { click: function () { body.hidden = !body.hidden; } }
            });

            return el('div', { 'class': 'bkmd-common' }, [toggle, body]);
        }

        function seg(options, value, onPick) {
            var group = el('div', { 'class': 'btn-group' });
            options.forEach(function (option) {
                group.appendChild(el('button', {
                    type: 'button', 'class': 'btn btn-default' + (option.value === value ? ' active' : ''),
                    text: option.label,
                    on: { click: function () { onPick(option.value); } }
                }));
            });
            return group;
        }

        /**
         * Área de texto enriquecido: negrita, cursiva, enlace y lista. El HTML resultante lo limpia
         * el servidor, así que aquí basta con no complicarlo.
         */
        /**
         * Editor de texto con su código a la vista.
         *
         * El botón `</>` cambia el área por el HTML en crudo y al volver lo aplica: quien sepa
         * HTML escribe una tabla o un div con su estilo sin salir del bloque, y quien no, ni se
         * entera de que está ahí.
         */
        function rich(value, onChange) {
            var area = el('div', { 'class': 'bkmd-rte__area', contenteditable: 'true', html: value || '' });
            var code = el('textarea', { 'class': 'bkmd-rte__code form-control', spellcheck: 'false' });
            code.hidden = true;
            code.value = value || '';

            area.addEventListener('input', function () { onChange(area.innerHTML); });
            area.addEventListener('focus', function () { lastArea = area; });
            code.addEventListener('input', function () { onChange(code.value); });

            function tool(title, html, action) {
                return el('button', { type: 'button', title: title, html: html, on: { click: action } });
            }

            var tools = [
                tool(i18n.bold, '<b>B</b>', function () { exec('bold', area, onChange); }),
                tool(i18n.italic, '<i>I</i>', function () { exec('italic', area, onChange); }),
                tool(i18n.underline, '<u>U</u>', function () { exec('underline', area, onChange); }),
                tool(i18n.list, '<i class="icon-list-ul"></i>', function () { exec('insertUnorderedList', area, onChange); }),
                tool(i18n.numbered, '<i class="icon-list-ol"></i>', function () { exec('insertOrderedList', area, onChange); }),
                tool(i18n.link, '<i class="icon-link"></i>', function () {
                    var url = window.prompt(i18n.link_prompt, 'https://');
                    if (url) { exec('createLink', area, onChange, url); }
                }),
                tool(i18n.unlink, '<i class="icon-unlink"></i>', function () { exec('unlink', area, onChange); }),
                tool(i18n.clear_format, '<i class="icon-eraser"></i>', function () { exec('removeFormat', area, onChange); })
            ];

            var source = el('button', {
                type: 'button', 'class': 'bkmd-rte__source', title: i18n.label_source_code, html: '&lt;/&gt;',
                on: {
                    click: function () {
                        var toCode = !area.hidden;
                        if (toCode) {
                            code.value = area.innerHTML;
                        } else {
                            area.innerHTML = code.value;
                        }
                        area.hidden = toCode;
                        code.hidden = !toCode;
                        tools.forEach(function (button) { button.disabled = toCode; });
                        source.title = toCode ? i18n.label_wysiwyg : i18n.label_source_code;
                        source.classList.toggle('is-active', toCode);
                        onChange(toCode ? code.value : area.innerHTML);
                        (toCode ? code : area).focus();
                    }
                }
            });

            var bar = el('div', { 'class': 'bkmd-rte__bar' }, tools.concat([
                el('span', { 'class': 'bkmd-rte__spacer' }),
                source
            ]));

            return el('div', { 'class': 'bkmd-rte' }, [bar, area, code]);
        }

        var lastArea = null;
        function exec(command, area, onChange, argument) {
            area.focus();
            document.execCommand(command, false, argument || null);
            onChange(area.innerHTML);
        }

        function drawProps() {
            props.innerHTML = '';
            var block = blocks()[selected];
            if (!block) {
                propsTitle.textContent = i18n.heading ? '—' : '';
                props.appendChild(el('p', { 'class': 'help-block', text: i18n.empty }));
                return;
            }
            propsTitle.textContent = i18n[block.type] || block.type;
            var set = function (key) {
                return function (value) { block[key] = value; mark(true); drawBlocks(); preview(); };
            };

            if (block.type === 'heading') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_text, withVars(input(block.text, set('text')))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_size, seg([{ value: 'h1', label: i18n.h1 }, { value: 'h2', label: i18n.h2 }], block.size, function (v) { block.size = v; changed(); })));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(numberField(i18n.label_font_size, block.font_size, 'px', function (v) { block.font_size = v; mark(true); preview(); }, 12, 44));
                props.appendChild(colorField(i18n.label_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
            } else if (block.type === 'text') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_text, withVars(rich(block.html, set('html')))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_tone, seg([{ value: 'normal', label: i18n.normal }, { value: 'muted', label: i18n.muted }], block.tone, function (v) { block.tone = v; changed(); })));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(numberField(i18n.label_font_size, block.font_size, 'px', function (v) { block.font_size = v; mark(true); preview(); }, 10, 26));
                props.appendChild(colorField(i18n.label_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
            } else if (block.type === 'button') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_text, withVars(input(block.text, set('text')))));
                props.appendChild(field(i18n.label_url, withVars(input(block.url, set('url')))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_style, seg([
                    { value: '', label: i18n.opt_theme },
                    { value: 'solid', label: i18n.solid },
                    { value: 'outline', label: i18n.outline }
                ], block.style || '', function (v) { block.style = v; changed(); })));
                props.appendChild(field(i18n.label_button_size, seg([
                    { value: '', label: i18n.auto }, { value: 's', label: 'S' }, { value: 'm', label: 'M' }, { value: 'l', label: 'L' }
                ], block.size || '', function (v) { block.size = v; changed(); })));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(colorField(i18n.label_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
                props.appendChild(check(i18n.label_full, block.full, function (v) { block.full = v; changed(); }));
            } else if (block.type === 'box') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_text, withVars(rich(block.html, set('html')))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(field(i18n.label_tone, seg([
                    { value: 'neutral', label: i18n.neutral }, { value: 'primary', label: i18n.primary },
                    { value: 'success', label: i18n.success }, { value: 'warning', label: i18n.warning }
                ], block.tone, function (v) { block.tone = v; changed(); })));
                props.appendChild(colorField(i18n.label_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
            } else if (block.type === 'divider') {
                props.appendChild(field(i18n.label_style, seg([
                    { value: '', label: i18n.auto }, { value: 'solid', label: i18n.rule_solid },
                    { value: 'dotted', label: i18n.rule_dotted }, { value: 'thick', label: i18n.rule_thick }
                ], block.style || '', function (v) { block.style = v; changed(); })));
                props.appendChild(numberField(i18n.label_width_pct, block.width, '%', function (v) { block.width = v; mark(true); preview(); }, 10, 100));
                props.appendChild(colorField(i18n.label_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
            } else if (block.type === 'spacer') {
                props.appendChild(field(i18n.label_height, input(block.height, function (v) { block.height = parseInt(v, 10) || 16; mark(true); preview(); }, 'number')));
            } else if (block.type === 'image') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_src, withUpload(input(block.src, set('src')), function (url) { block.src = url; changed(); })));
                props.appendChild(field(i18n.label_alt, input(block.alt, set('alt'))));
                props.appendChild(field(i18n.label_url, input(block.url, set('url'))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_width, input(block.width, function (v) { block.width = parseInt(v, 10) || 536; mark(true); preview(); }, 'number')));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(numberField(i18n.label_radius, block.radius, 'px', function (v) { block.radius = v; mark(true); preview(); }, 0, 40));
            } else if (block.type === 'media') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_src, withUpload(input(block.src, set('src')), function (url) { block.src = url; changed(); })));
                props.appendChild(field(i18n.label_alt, input(block.alt, set('alt'))));
                props.appendChild(field(i18n.label_url, input(block.url, set('url'))));
                props.appendChild(field(i18n.label_text, withVars(rich(block.html, set('html')))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_side, seg([
                    { value: 'left', label: i18n.side_left }, { value: 'right', label: i18n.side_right }
                ], block.side || 'left', function (v) { block.side = v; changed(); })));
                props.appendChild(field(i18n.label_ratio, seg([
                    { value: '30', label: '30 %' }, { value: '40', label: '40 %' }, { value: '50', label: '50 %' }
                ], block.ratio || '40', function (v) { block.ratio = v; changed(); })));
                props.appendChild(field(i18n.label_valign, seg([
                    { value: 'top', label: i18n.valign_top }, { value: 'middle', label: i18n.valign_middle }
                ], block.valign || 'top', function (v) { block.valign = v; changed(); })));
            } else if (block.type === 'hero') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_src, withUpload(input(block.src, set('src')), function (url) { block.src = url; changed(); })));
                props.appendChild(field(i18n.label_heading, input(block.heading, set('heading'))));
                props.appendChild(field(i18n.label_text, withVars(rich(block.html, set('html')))));
                props.appendChild(field(i18n.label_btn_text, input(block.btn_text, set('btn_text'))));
                props.appendChild(field(i18n.label_btn_url, withVars(input(block.btn_url, set('btn_url')))));
                props.appendChild(field(i18n.label_url, input(block.url, set('url'))));
                props.appendChild(section(i18n.group_look));
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(numberField(i18n.label_hero_height, block.height, 'px', function (v) { block.height = v; mark(true); preview(); }, 80, 520));
                props.appendChild(numberField(i18n.label_veil, block.veil, '%', function (v) { block.veil = v; mark(true); preview(); }, 0, 90));
                props.appendChild(colorField(i18n.label_text_color, block.color, function (v) { block.color = v; mark(true); preview(); }));
                props.appendChild(el('p', { 'class': 'bkmd-props__hint', text: i18n.hero_hint }));
            } else if (block.type === 'social') {
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(el('p', { 'class': 'bkmd-props__hint', text: i18n.social_hint }));
            } else if (block.type === 'columns') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_columns_count, seg([
                    { value: '2', label: '2' }, { value: '3', label: '3' }
                ], block.count || '2', function (v) { block.count = v; changed(); })));
                props.appendChild(field(i18n.label_left, withVars(rich(block.left, set('left')))));
                props.appendChild(field(i18n.label_right, withVars(rich(block.right, set('right')))));
                if ((block.count || '2') === '3') {
                    props.appendChild(field(i18n.label_third, withVars(rich(block.third, set('third')))));
                    props.appendChild(section(i18n.group_look));
                } else {
                    props.appendChild(section(i18n.group_look));
                    props.appendChild(field(i18n.label_ratio, seg([
                        { value: '50', label: '50 / 50' }, { value: '60', label: '60 / 40' }, { value: '40', label: '40 / 60' }
                    ], block.ratio || '50', function (v) { block.ratio = v; changed(); })));
                }
                props.appendChild(field(i18n.label_align, alignSeg(block, 'align')));
                props.appendChild(field(i18n.label_valign, seg([
                    { value: 'top', label: i18n.valign_top }, { value: 'middle', label: i18n.valign_middle }
                ], block.valign || 'top', function (v) { block.valign = v; changed(); })));
            } else if (block.type === 'order' || block.type === 'addresses') {
                if (block.type === 'order') {
                    props.appendChild(section(i18n.group_look));
                    props.appendChild(field(i18n.label_order_layout, seg([
                        { value: '', label: i18n.opt_theme },
                        { value: 'stacked', label: i18n.opt_stacked },
                        { value: 'table', label: i18n.opt_table }
                    ], block.layout || '', function (v) { block.layout = v; changed(); })));
                    // Cada dato de la línea: lo que diga el tema, o sí o no solo para este correo
                    [
                        ['show_image', i18n.label_line_photo],
                        ['show_reference', i18n.label_line_reference],
                        ['show_options', i18n.label_line_options],
                        ['show_unit', i18n.label_line_unit]
                    ].forEach(function (pair) {
                        var key = pair[0];
                        props.appendChild(field(pair[1], seg([
                            { value: '', label: i18n.opt_theme },
                            { value: 1, label: i18n.yes },
                            { value: 0, label: i18n.no }
                        ], block[key] === undefined || block[key] === '' ? '' : block[key], function (v) {
                            block[key] = v; changed();
                        })));
                    });
                    props.appendChild(numberField(i18n.label_line_size, block.image_size || 0, 'px', function (v) {
                        block.image_size = v; mark(true); preview();
                    }, 0, 120));
                    props.appendChild(field(i18n.label_totals, seg(
                        [{ value: true, label: i18n.yes }, { value: false, label: i18n.no }],
                        block.totals !== false,
                        function (v) { block.totals = v; changed(); }
                    )));
                    // Cada línea de totales: siempre, solo cuando trae importe, o nunca
                    if (block.totals !== false) {
                        var modes = block.totals_mode || (block.totals_mode = {});
                        [
                            ['subtotal', 'always'], ['shipping', 'always'], ['discounts', 'auto'],
                            ['tax', 'auto'], ['total_paid', 'always']
                        ].forEach(function (pair) {
                            var key = pair[0];
                            props.appendChild(field(cfg.labels[key], seg([
                                { value: 'always', label: i18n.total_always },
                                { value: 'auto', label: i18n.total_auto },
                                { value: 'never', label: i18n.total_never }
                            ], modes[key] || pair[1], function (v) { modes[key] = v; changed(); })));
                        });
                        props.appendChild(el('p', { 'class': 'bkmd-props__hint', text: i18n.totals_hint }));
                    }
                }
                var labels = block.labels || (block.labels = {});
                // Las cabeceras de columna solo existen en la presentación de cinco columnas
                var stacked = block.type === 'order'
                    && (block.layout || cfg.order_layout || 'stacked') === 'stacked';
                var wanted = block.type === 'order'
                    ? (stacked ? [] : ['reference', 'product', 'unit_price', 'quantity', 'total'])
                        .concat(block.totals === false ? [] : ['subtotal', 'shipping', 'free_shipping', 'discounts', 'tax', 'total_paid'])
                    : ['delivery', 'invoice'];
                // Once campos de texto seguidos son un muro: se guardan detrás de un desplegable
                var wordsBody = el('div', { 'class': 'bkmd-common__body' });
                wordsBody.hidden = true;
                wanted.forEach(function (key) {
                    if (cfg.labels[key] === undefined) { return; }
                    var caption = key === 'free_shipping' ? i18n.label_free_shipping : cfg.labels[key];
                    wordsBody.appendChild(field(caption, input(labels[key] === undefined ? cfg.labels[key] : labels[key], function (v) {
                        labels[key] = v; mark(true); preview();
                    })));
                });
                if (wanted.length) {
                    props.appendChild(el('div', { 'class': 'bkmd-common' }, [
                        el('button', {
                            type: 'button', 'class': 'bkmd-common__toggle', text: i18n.label_words,
                            on: { click: function () { wordsBody.hidden = !wordsBody.hidden; } }
                        }),
                        wordsBody
                    ]));
                }
                if (block.type === 'order') {
                    // La tabla de líneas llega en una variable, y no siempre se llama {products}:
                    // hace falta a veces, se entiende pocas, y por eso va plegada al final.
                    var advBody = el('div', { 'class': 'bkmd-common__body' }, [
                        field(i18n.label_source, input(block.source || 'products', set('source'))),
                        el('p', { 'class': 'bkmd-props__hint', text: i18n.source_hint })
                    ]);
                    advBody.hidden = true;
                    props.appendChild(el('div', { 'class': 'bkmd-common' }, [
                        el('button', {
                            type: 'button', 'class': 'bkmd-common__toggle', text: i18n.label_advanced,
                            on: { click: function () { advBody.hidden = !advBody.hidden; } }
                        }),
                        advBody
                    ]));
                }
            } else if (block.type === 'html') {
                props.appendChild(section(i18n.group_content));
                props.appendChild(field(i18n.label_html, el('textarea', {
                    'class': 'form-control', rows: '8',
                    on: { input: function (event) { block.html = event.target.value; mark(true); drawBlocks(); preview(); } }
                })));
                props.querySelector('textarea').value = block.html || '';
            }

            if (block.type !== 'spacer') {
                props.appendChild(commonFields(block));
            }

            props.appendChild(el('div', { 'class': 'bkmd-props__actions' }, [
                el('button', {
                    type: 'button', 'class': 'btn btn-default btn-xs', text: i18n.duplicate,
                    on: { click: function () { blocks().splice(selected + 1, 0, JSON.parse(JSON.stringify(block))); selected += 1; changed(); } }
                }),
                el('button', {
                    type: 'button', 'class': 'btn btn-default btn-xs', text: i18n.up,
                    on: { click: function () { if (selected > 0) { var m = blocks().splice(selected, 1)[0]; blocks().splice(selected - 1, 0, m); selected -= 1; changed(); } } }
                }),
                el('button', {
                    type: 'button', 'class': 'btn btn-default btn-xs', text: i18n.down,
                    on: { click: function () { if (selected < blocks().length - 1) { var m = blocks().splice(selected, 1)[0]; blocks().splice(selected + 1, 0, m); selected += 1; changed(); } } }
                }),
                el('button', {
                    type: 'button', 'class': 'btn btn-link btn-xs', text: i18n.remove,
                    on: { click: function () { blocks().splice(selected, 1); selected = -1; changed(); } }
                })
            ]));
        }

        function check(label, value, onChange) {
            var box = el('input', {
                type: 'checkbox',
                on: { change: function (e) { onChange(e.target.checked); } }
            });
            box.checked = !!value;

            return el('div', { 'class': 'bkmd-field bkmd-field--check' }, [
                el('label', {}, [box, el('span', { text: label })])
            ]);
        }

        // Centrar vale para cualquier bloque: un titular, un párrafo o una caja se centran igual
        // que un botón, y esconder la opción en unos sí y en otros no no lo entiende nadie.
        function alignSeg(block, key) {
            return seg([
                { value: 'left', label: i18n.align_left },
                { value: 'center', label: i18n.align_center },
                { value: 'right', label: i18n.align_right }
            ], block[key] || 'left', function (v) { block[key] = v; changed(); });
        }

        // ---- variables: se insertan donde estaba el cursor
        root.querySelectorAll('[data-bkmd-vars] .bkmd-var').forEach(function (button) {
            button.addEventListener('mousedown', function (event) { event.preventDefault(); });
            button.addEventListener('click', function () {
                var token = '{' + button.getAttribute('data-var') + '}';
                var active = document.activeElement;
                if (active === subject || (active && active.tagName === 'INPUT' && props.contains(active)) || (active && active.tagName === 'TEXTAREA' && props.contains(active))) {
                    var start = active.selectionStart || 0;
                    var end = active.selectionEnd || 0;
                    active.value = active.value.slice(0, start) + token + active.value.slice(end);
                    active.selectionStart = active.selectionEnd = start + token.length;
                    active.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }
                if (lastArea && props.contains(lastArea)) {
                    lastArea.focus();
                    document.execCommand('insertText', false, token);
                    lastArea.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        // ---- variables añadidas a mano
        var addToggle = root.querySelector('[data-bkmd-addvar-toggle]');
        if (addToggle) {
            var addForm = root.querySelector('[data-bkmd-addvar-form]');
            addToggle.addEventListener('click', function () {
                addForm.hidden = !addForm.hidden;
                if (!addForm.hidden) { addForm.querySelector('[data-bkmd-addvar-name]').focus(); }
            });
            root.querySelector('[data-bkmd-addvar-save]').addEventListener('click', function () {
                var name = addForm.querySelector('[data-bkmd-addvar-name]').value;
                var value = addForm.querySelector('[data-bkmd-addvar-value]').value;
                if (!name.trim()) { return; }
                post('BkCustomVar', { name: name, value: value }, function (response) {
                    if (response && response.ok) { window.location.reload(); }
                });
            });
        }
        root.querySelectorAll('[data-bkmd-delvar]').forEach(function (button) {
            button.addEventListener('click', function () {
                post('BkCustomVar', { name: button.getAttribute('data-bkmd-delvar'), remove: 1 }, function (response) {
                    if (response && response.ok) { window.location.reload(); }
                });
            });
        });

        // ---- modo
        root.querySelectorAll('[data-bkmd-mode] button').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-mode') === state.mode);
            button.addEventListener('click', function () {
                state.mode = button.getAttribute('data-mode');
                root.setAttribute('data-mode', state.mode);
                root.querySelectorAll('[data-bkmd-mode] button').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                mark(true);
                drawLangs();
                load();
            });
        });

        // El asunto también admite variables. Se anota dónde estaba antes de envolverlo: al
        // envolverlo se mueve, y su posición original deja de existir.
        (function () {
            var holder = subject.parentNode;
            var after = subject.nextSibling;
            holder.insertBefore(withVars(subject), after);
        }());

        subject.addEventListener('input', function () {
            state.langs[current.id].subject = subject.value;
            mark(true);
            preview();
        });

        root.querySelectorAll('[data-bkmd-type] button').forEach(function (button) {
            button.addEventListener('click', function () {
                previewType = button.getAttribute('data-type');
                root.querySelectorAll('[data-bkmd-type] button').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                preview();
            });
        });

        // ---- valores de prueba: se guardan al salir del campo y la vista previa los recoge
        var sampleTimer = null;
        root.querySelectorAll('[data-bkmd-sample]').forEach(function (input) {
            input.addEventListener('change', function () {
                var payload = {};
                payload[input.getAttribute('data-bkmd-sample')] = input.value;
                if (sampleTimer) { window.clearTimeout(sampleTimer); }
                sampleTimer = window.setTimeout(function () {
                    post('BkSaveSamples', { samples: payload }, function () { preview(); });
                }, 200);
            });
        });

        // ---- vista previa
        var timer = null;
        function preview() {
            if (timer) { window.clearTimeout(timer); }
            timer = window.setTimeout(function () {
                post('BkPreview', {
                    bk_module: cfg.module,
                    bk_name: cfg.name,
                    id_lang: current.id,
                    mode: state.mode,
                    blocks: JSON.stringify(blocks()),
                    subject: state.langs[current.id].subject,
                    type: previewType,
                    mark: previewType === 'html' && state.mode === 'designed' ? 1 : 0
                }, function (html) {
                    paint(frame, html || '');
                    if (previewType === 'html' && state.mode === 'designed') { makeClickable(); }
                }, true);
            }, 300);
        }

        /**
         * El correo de la vista previa es el sitio natural para elegir qué se edita: se pincha el
         * bloque en el correo y se abren sus propiedades, sin buscarlo en la lista.
         */
        function makeClickable() {
            var iframe = frame.querySelector('iframe');
            var doc = iframe.contentDocument || iframe.contentWindow.document;
            if (!doc || !doc.body) { return; }

            var style = doc.createElement('style');
            style.textContent = '[data-bk-i]{cursor:pointer;position:relative}'
                + '[data-bk-i]:hover>td{outline:2px dashed #25b9d7;outline-offset:-2px}'
                + '[data-bk-i].bk-picked>td{outline:2px solid #25b9d7;outline-offset:-2px;background-color:rgba(37,185,215,.06)}';
            doc.body.appendChild(style);

            doc.body.addEventListener('click', function (event) {
                var row = event.target.closest ? event.target.closest('[data-bk-i]') : null;
                if (!row) { return; }
                event.preventDefault();
                selected = parseInt(row.getAttribute('data-bk-i'), 10);
                drawBlocks();
                drawProps();
                highlight();
            });

            highlight();
        }

        function highlight() {
            var iframe = frame.querySelector('iframe');
            var doc = iframe.contentDocument || iframe.contentWindow.document;
            if (!doc || !doc.body) { return; }
            doc.querySelectorAll('[data-bk-i]').forEach(function (row) {
                row.classList.toggle('bk-picked', parseInt(row.getAttribute('data-bk-i'), 10) === selected);
            });
        }

        // ---- guardar, preset, copiar y reiniciar
        function save(after) {
            var payload = {
                bk_module: cfg.module,
                bk_name: cfg.name,
                mode: state.mode === 'original' ? 'wrapped' : state.mode,
                active: state.mode === 'original' ? 0 : 1,
                subject: {},
                blocks: {}
            };
            cfg.languages.forEach(function (lang) {
                payload.subject[lang.id] = state.langs[lang.id].subject;
                payload.blocks[lang.id] = JSON.stringify(state.langs[lang.id].blocks);
            });
            if (state.mode === 'designed' && blocks().length === 0) {
                mark(true, i18n.no_blocks, 'error');
                return;
            }
            post('BkSaveTemplate', payload, function (response) {
                if (response && response.ok) {
                    mark(false, i18n.saved, 'ok');
                    if (after) { after(); }
                } else {
                    mark(true, i18n.save_error, 'error');
                }
            });
        }

        root.querySelector('[data-bkmd-save]').addEventListener('click', function () { save(); });

        var presetButton = root.querySelector('[data-bkmd-preset]');
        if (presetButton) {
            presetButton.addEventListener('click', function () {
                if (!window.confirm(i18n.confirm_preset)) { return; }
                post('BkApplyPreset', { bk_module: cfg.module, bk_name: cfg.name }, function (response) {
                    if (response && response.ok) { window.location.reload(); }
                });
            });
        }

        if (copyRef) {
            copyRef.addEventListener('click', function () {
                state.langs[current.id].blocks = JSON.parse(JSON.stringify(state.langs[refLang.id].blocks));
                if (!state.langs[current.id].subject) {
                    state.langs[current.id].subject = state.langs[refLang.id].subject;
                }
                mark(true);
                drawLangs();
                load();
            });
        }

        root.querySelector('[data-bkmd-reset]').addEventListener('click', function () {
            if (!window.confirm(i18n.confirm_reset)) { return; }
            post('BkResetTemplate', { bk_module: cfg.module, bk_name: cfg.name }, function () { window.location.reload(); });
        });

        // ---- envío de prueba
        var testModal = document.querySelector('[data-bkmd-test-modal]');
        root.querySelector('[data-bkmd-test]').addEventListener('click', function () { testModal.hidden = false; });
        testModal.querySelector('[data-bkmd-test-send]').addEventListener('click', function () {
            var email = document.getElementById('bkmd-test-email').value;
            var send = function () {
                post('BkSendTest', { bk_module: cfg.module, bk_name: cfg.name, id_lang: current.id, email: email }, function (response) {
                    testModal.hidden = true;
                    if (response && response.ok) {
                        mark(false, i18n.sent.replace('%s', email), 'ok');
                    } else {
                        mark(dirty, i18n.send_error, 'error');
                    }
                });
            };
            if (dirty) { save(send); } else { send(); }
        });

        window.addEventListener('beforeunload', function (event) {
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        drawLangs();
        load();
    }

    document.addEventListener('DOMContentLoaded', function () {
        tabs();
        list();
        modals();
        layout();
        editor();
        if (window.jQuery && jQuery.fn.chosen) {
            jQuery('.bkmd-chosen').chosen({ disable_search_threshold: 8, width: '100%' });
        }
    });
}());
