(function () {
    'use strict';

    function escapeHtml(text) {
        if (!text && text !== 0) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var settings = window.AnchorsSinIALinkbuilder || {};
        var container = document.getElementById('sai-linkbuilder-app');
        if (!container || !settings.restUrl) {
            return;
        }

        var perPage = parseInt(settings.perPage, 10) || 50;
        var i18n = settings.i18n || {};

        var searchDefault = function () {
            return {
                keyword: '',
                includeBody: false,
                page: 1,
                items: [],
                total: 0,
                totalPages: 0,
                hasSearched: false,
            };
        };

        var uiState = {
            currentStep: getStepFromUrl(),
            loading: false,
            notice: '',
            noticeType: 'success',
            appState: null,
            canonicalInput: '',
            madreSelection: null,
            madreAnchorsPreview: [],
            hijasSelection: [],
            nietasSelection: [],
            searches: {
                madre: searchDefault(),
                hijas: searchDefault(),
                nietas: searchDefault(),
            },
        };

        var eventsBound = false;

        function getStepFromUrl() {
            var params = new URLSearchParams(window.location.search);
            var step = parseInt(params.get('step'), 10);
            if (!step || step < 1 || step > 4) {
                step = 1;
            }
            return step;
        }

        function updateUrlStep(step) {
            var url = new URL(window.location.href);
            url.searchParams.set('step', step);
            window.history.replaceState({}, '', url.toString());
        }

        function setNotice(message, type) {
            uiState.notice = message || '';
            uiState.noticeType = type || 'success';
        }

        function clearNotice() {
            setNotice('', 'success');
        }

        function apiFetch(path, options) {
            options = options || {};
            options.headers = options.headers || {};
            options.headers['X-WP-Nonce'] = settings.nonce || '';
            if (!options.headers['Content-Type'] && options.method && options.method.toUpperCase() === 'POST' && options.body) {
                options.headers['Content-Type'] = 'application/json';
            }
            return fetch(settings.restUrl + path, options).then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        var message = data && data.message ? data.message : (i18n.copyError || 'Error');
                        throw new Error(message);
                    });
                }
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json();
                }
                return response.text();
            });
        }

        function loadState() {
            uiState.loading = true;
            render();
            apiFetch('linkbuilder/state')
                .then(function (data) {
                    uiState.appState = data || {};
                    syncUiWithState();
                    uiState.loading = false;
                    render();
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function syncUiWithState() {
            var state = uiState.appState || {};
            uiState.canonicalInput = state.canonical || uiState.canonicalInput || '';
            uiState.madreSelection = state.madre_id || null;
            uiState.madreAnchorsPreview = state.madre_anchors || [];
            uiState.hijasSelection = Array.isArray(state.hijas_ids) ? state.hijas_ids.slice() : [];
            uiState.nietasSelection = Array.isArray(state.nietas_ids) ? state.nietas_ids.slice() : [];

            if (state.q_madre) {
                uiState.searches.madre.keyword = state.q_madre;
            }
            uiState.searches.madre.includeBody = !!state.in_content_madre;

            if (state.q_hijas) {
                uiState.searches.hijas.keyword = state.q_hijas;
            }
            uiState.searches.hijas.includeBody = !!state.in_content_hijas;

            if (state.q_nietas) {
                uiState.searches.nietas.keyword = state.q_nietas;
            }
            uiState.searches.nietas.includeBody = !!state.in_content_nietas;
        }

        function canAccessStep(step) {
            var state = uiState.appState || {};
            if (step <= 1) {
                return true;
            }
            if (!state.madre_id) {
                return false;
            }
            if (step === 2) {
                return true;
            }
            if (step === 3) {
                return Array.isArray(state.hijas_ids) && state.hijas_ids.length > 0;
            }
            if (step === 4) {
                return Array.isArray(state.nietas_ids) && state.nietas_ids.length > 0;
            }
            return false;
        }

        function goToStep(step) {
            step = parseInt(step, 10);
            if (isNaN(step) || step < 1 || step > 4) {
                return;
            }
            if (!canAccessStep(step)) {
                setNotice(i18n.missingMadre || 'Completa el paso anterior.', 'error');
                render();
                return;
            }
            uiState.currentStep = step;
            updateUrlStep(step);
            clearNotice();
            render();
        }

        function performSearch(step, page) {
            var search = uiState.searches[step];
            if (!search) {
                return;
            }

            if (!search.keyword) {
                setNotice(i18n.keywordLabel || 'Introduce una palabra clave.', 'error');
                render();
                return;
            }

            var state = uiState.appState || {};
            if (step !== 'madre' && !state.madre_id) {
                setNotice(i18n.missingMadre || 'Selecciona una madre antes de continuar.', 'error');
                render();
                return;
            }

            search.page = page || 1;
            uiState.loading = true;
            render();

            var params = new URLSearchParams();
            params.append('kw', search.keyword);
            params.append('page', search.page);
            params.append('in_body', search.includeBody ? 1 : 0);

            if (step !== 'madre') {
                params.append('context_id', state.madre_id);
                params.append('canonical', state.canonical || uiState.canonicalInput || '');
                var exclude = [];
                if (state.madre_id) {
                    exclude.push(state.madre_id);
                }
                if (Array.isArray(state.hijas_ids)) {
                    exclude = exclude.concat(state.hijas_ids);
                }
                if (Array.isArray(state.nietas_ids)) {
                    exclude = exclude.concat(state.nietas_ids);
                }
                var unique = {};
                exclude.forEach(function (id) {
                    if (id) {
                        unique[id] = true;
                    }
                });
                Object.keys(unique).forEach(function (id) {
                    params.append('exclude[]', id);
                });
            }

            apiFetch('search?' + params.toString())
                .then(function (data) {
                    search.items = data.items || [];
                    search.total = data.total || 0;
                    search.totalPages = data.totalPages || 0;
                    search.hasSearched = true;
                    uiState.loading = false;
                    clearNotice();
                    render();
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function saveMadre() {
            var selected = uiState.madreSelection;
            if (!selected) {
                setNotice(i18n.missingMadre || 'Selecciona una madre antes de continuar.', 'error');
                render();
                return;
            }
            var canonical = (uiState.canonicalInput || '').trim();
            if (!canonical) {
                setNotice(i18n.keywordLabel || 'Introduce una palabra clave.', 'error');
                render();
                return;
            }

            uiState.loading = true;
            render();

            var search = uiState.searches.madre;
            var payload = {
                id: selected,
                canonical: canonical,
                keyword: search.keyword || canonical,
                in_content: search.includeBody ? 1 : 0,
            };

            apiFetch('linkbuilder/madre', {
                method: 'POST',
                body: JSON.stringify(payload),
            })
                .then(function (data) {
                    uiState.appState = data.state || {};
                    uiState.madreAnchorsPreview = (data.anchors && data.anchors.anchors) || [];
                    syncUiWithState();
                    uiState.loading = false;
                    clearNotice();
                    goToStep(2);
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function saveHijas() {
            var state = uiState.appState || {};
            var limit = state.limit_hijas || 1;
            var selection = uiState.hijasSelection || [];
            if (selection.length === 0) {
                setNotice(i18n.missingHijas || 'Selecciona al menos una hija.', 'error');
                render();
                return;
            }
            if (selection.length > limit) {
                setNotice(i18n.limitExceeded || 'Has superado el límite.', 'error');
                render();
                return;
            }

            uiState.loading = true;
            render();

            var search = uiState.searches.hijas;
            var payload = {
                ids: selection,
                keyword: search.keyword || '',
                in_content: search.includeBody ? 1 : 0,
            };

            apiFetch('linkbuilder/hijas', {
                method: 'POST',
                body: JSON.stringify(payload),
            })
                .then(function (data) {
                    uiState.appState = data.state || {};
                    uiState.hijasSelection = (data.state && data.state.hijas_ids) ? data.state.hijas_ids.slice() : [];
                    uiState.loading = false;
                    clearNotice();
                    goToStep(3);
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function saveNietas() {
            var state = uiState.appState || {};
            var limit = state.limit_nietas || 1;
            var selection = uiState.nietasSelection || [];
            if (selection.length === 0) {
                setNotice(i18n.missingNietas || 'Selecciona al menos una nieta.', 'error');
                render();
                return;
            }
            if (selection.length > limit) {
                setNotice(i18n.limitExceeded || 'Has superado el límite.', 'error');
                render();
                return;
            }

            uiState.loading = true;
            render();

            var search = uiState.searches.nietas;
            var payload = {
                ids: selection,
                keyword: search.keyword || '',
                in_content: search.includeBody ? 1 : 0,
            };

            apiFetch('linkbuilder/nietas', {
                method: 'POST',
                body: JSON.stringify(payload),
            })
                .then(function (data) {
                    uiState.appState = data || {};
                    uiState.nietasSelection = Array.isArray(data.nietas_ids) ? data.nietas_ids.slice() : [];
                    uiState.loading = false;
                    clearNotice();
                    goToStep(4);
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function resetFlow() {
            uiState.loading = true;
            render();
            apiFetch('linkbuilder/reset', { method: 'POST' })
                .then(function (data) {
                    uiState.appState = data || {};
                    uiState.searches.madre = searchDefault();
                    uiState.searches.hijas = searchDefault();
                    uiState.searches.nietas = searchDefault();
                    uiState.currentStep = 1;
                    uiState.canonicalInput = '';
                    uiState.madreSelection = null;
                    uiState.madreAnchorsPreview = [];
                    uiState.hijasSelection = [];
                    uiState.nietasSelection = [];
                    uiState.loading = false;
                    clearNotice();
                    updateUrlStep(1);
                    render();
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.copyError || 'Error'), 'error');
                    render();
                });
        }

        function exportCsv() {
            uiState.loading = true;
            render();
            apiFetch('linkbuilder/export')
                .then(function (data) {
                    uiState.loading = false;
                    clearNotice();
                    if (!data || !data.csv) {
                        setNotice(i18n.exportError || 'No se pudo generar el CSV.', 'error');
                        render();
                        return;
                    }
                    var blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = data.filename || 'anchors.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setNotice(i18n.csvGenerated || 'CSV generado.', 'success');
                    render();
                })
                .catch(function (error) {
                    uiState.loading = false;
                    setNotice(error.message || (i18n.exportError || 'No se pudo generar el CSV.'), 'error');
                    render();
                });
        }

        function render() {
            var html = '';
            html += '<div class="sai-lb-header">';
            html += renderNav();
            html += '<div class="sai-lb-actions">';
            html += '<button type="button" class="button" data-action="reset-flow"' + (uiState.loading ? ' disabled' : '') + '>' + escapeHtml(i18n.reset || 'Reiniciar flujo') + '</button>';
            html += '</div>';
            html += '</div>';

            if (uiState.notice) {
                var noticeClass = uiState.noticeType === 'error' ? 'sai-notice-error' : 'sai-notice-success';
                html += '<div class="sai-notice ' + noticeClass + '">' + escapeHtml(uiState.notice) + '</div>';
            }

            if (uiState.loading) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            }

            html += '<div class="sai-lb-step-content">' + renderStepContent() + '</div>';

            container.innerHTML = html;

            if (!eventsBound) {
                bindEvents();
                eventsBound = true;
            }
        }

        function renderNav() {
            var steps = [
                { id: 1, label: i18n.madreHeading || 'Paso 1' },
                { id: 2, label: i18n.hijasHeading || 'Paso 2' },
                { id: 3, label: i18n.nietasHeading || 'Paso 3' },
                { id: 4, label: i18n.exportHeading || 'Paso 4' },
            ];
            var html = '<ul class="sai-lb-steps">';
            steps.forEach(function (step) {
                var classes = ['sai-lb-step-item'];
                if (uiState.currentStep === step.id) {
                    classes.push('is-active');
                }
                if (!canAccessStep(step.id)) {
                    classes.push('is-disabled');
                }
                html += '<li class="' + classes.join(' ') + '" data-step="' + step.id + '">' + escapeHtml(step.label) + '</li>';
            });
            html += '</ul>';
            return html;
        }

        function renderStepContent() {
            switch (uiState.currentStep) {
                case 1:
                    return renderStepMadre();
                case 2:
                    return renderStepHijas();
                case 3:
                    return renderStepNietas();
                case 4:
                    return renderStepExport();
                default:
                    return '';
            }
        }

        function renderSearchControls(stepKey, label, showCanonical) {
            var search = uiState.searches[stepKey];
            var html = '<div class="sai-search">';
            html += '<div class="sai-search-controls">';
            html += '<label class="sai-label">' + escapeHtml(i18n.keywordLabel || 'Palabra clave') + '<input type="text" data-input="keyword" data-step="' + stepKey + '" value="' + escapeHtml(search.keyword) + '" placeholder="' + escapeHtml(i18n.keywordLabel || '') + '"></label>';
            html += '<div class="sai-search-actions">';
            html += '<label class="sai-checkbox"><input type="checkbox" data-input="include" data-step="' + stepKey + '"' + (search.includeBody ? ' checked' : '') + '> ' + escapeHtml(i18n.includeBody || '') + '</label>';
            html += '<button type="button" class="button button-primary" data-action="search" data-step="' + stepKey + '">' + escapeHtml(i18n.search || 'Buscar') + '</button>';
            html += '</div>';
            html += '</div>';

            if (showCanonical) {
                html += '<div class="sai-canonical-field">';
                html += '<label class="sai-label">' + escapeHtml(i18n.canonical || 'Canónico') + '<input type="text" data-input="canonical" value="' + escapeHtml(uiState.canonicalInput || search.keyword || '') + '"></label>';
                html += '</div>';
            }

            html += '</div>';
            return html;
        }

        function renderPagination(stepKey) {
            var search = uiState.searches[stepKey];
            if (!search || search.totalPages <= 1) {
                return '';
            }
            var html = '<div class="sai-pagination">';
            html += '<button type="button" class="button" data-action="page-prev" data-step="' + stepKey + '"' + (search.page <= 1 ? ' disabled' : '') + '>&laquo;</button>';
            html += '<span>' + escapeHtml((i18n.pageLabel || 'Página') + ' ' + search.page + ' / ' + search.totalPages) + '</span>';
            html += '<button type="button" class="button" data-action="page-next" data-step="' + stepKey + '"' + (search.page >= search.totalPages ? ' disabled' : '') + '>&raquo;</button>';
            html += '</div>';
            return html;
        }

        function renderStepMadre() {
            var search = uiState.searches.madre;
            var state = uiState.appState || {};
            var html = '<h2>' + escapeHtml(i18n.madreHeading || 'Paso 1: Selecciona la madre') + '</h2>';
            html += renderSearchControls('madre', i18n.madreHeading, true);
            html += '<div class="sai-results">';
            if (uiState.loading && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            } else if (search.hasSearched && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.noResults || 'Sin resultados.') + '</p>';
            } else {
                html += '<table class="widefat fixed">';
                html += '<thead><tr><th></th><th>' + escapeHtml(i18n.keywordLabel || 'Título') + '</th><th>' + escapeHtml(i18n.view || 'Ver') + '</th></tr></thead>';
                html += '<tbody>';
                search.items.forEach(function (item) {
                    html += '<tr>';
                    html += '<td><input type="radio" name="madre-select" data-action="select-madre" value="' + item.id + '"' + (uiState.madreSelection === item.id ? ' checked' : '') + '></td>';
                    html += '<td><strong>' + escapeHtml(item.title) + '</strong><br><span class="sai-result-meta">' + escapeHtml(item.type) + '</span></td>';
                    html += '<td><a href="' + escapeHtml(item.link) + '" class="button" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.view || 'Ver') + '</a></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
            html += '</div>';
            html += renderPagination('madre');

            html += '<div class="sai-step-actions">';
            html += '<button type="button" class="button button-primary" data-action="save-madre"' + (uiState.loading ? ' disabled' : '') + '>' + escapeHtml(i18n.saveMadre || 'Guardar madre y continuar') + '</button>';
            html += '</div>';

            if (state.madre_id) {
                html += '<div class="sai-summary">';
                html += '<h3>' + escapeHtml(i18n.madreAnchors || 'Anchors de la madre') + '</h3>';
                if (uiState.madreAnchorsPreview.length) {
                    html += '<ul class="sai-anchor-list">';
                    uiState.madreAnchorsPreview.forEach(function (anchor) {
                        html += '<li><strong>' + escapeHtml(anchor.text) + '</strong> <span class="sai-tag">' + escapeHtml(anchor.class) + '</span> <span class="sai-tag">' + escapeHtml(anchor.frequency) + '</span></li>';
                    });
                    html += '</ul>';
                } else {
                    html += '<p class="sai-status">' + escapeHtml(i18n.noAnchors || 'No hay anchors disponibles.') + '</p>';
                }
                html += '</div>';
            }

            return html;
        }

        function renderStepHijas() {
            var search = uiState.searches.hijas;
            var state = uiState.appState || {};
            var limit = state.limit_hijas || 1;
            var html = '<h2>' + escapeHtml(i18n.hijasHeading || 'Paso 2: Selecciona las hijas') + '</h2>';
            html += '<p class="sai-status">' + escapeHtml((i18n.selectionLimit || 'Límite de selección') + ': ' + limit) + '</p>';
            html += renderSearchControls('hijas', i18n.hijasHeading, false);
            html += '<div class="sai-results">';
            if (uiState.loading && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            } else if (search.hasSearched && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.noResults || 'Sin resultados.') + '</p>';
            } else {
                html += '<table class="widefat fixed">';
                html += '<thead><tr><th></th><th>' + escapeHtml(i18n.keywordLabel || 'Título') + '</th><th>' + escapeHtml(i18n.cannibalHeading || 'Canibalización') + '</th><th>' + escapeHtml(i18n.view || 'Ver') + '</th></tr></thead>';
                html += '<tbody>';
                search.items.forEach(function (item) {
                    var checked = uiState.hijasSelection.indexOf(item.id) !== -1 ? ' checked' : '';
                    var cannibal = item.cannibalization || {};
                    html += '<tr>';
                    html += '<td><input type="checkbox" data-action="select-hija" value="' + item.id + '"' + checked + '></td>';
                    html += '<td><strong>' + escapeHtml(item.title) + '</strong><br><span class="sai-result-meta">' + escapeHtml(item.type) + '</span></td>';
                    html += '<td>' + renderCannibalBadge(cannibal) + '</td>';
                    html += '<td><a href="' + escapeHtml(item.link) + '" class="button" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.view || 'Ver') + '</a></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
            html += '</div>';
            html += renderPagination('hijas');

            html += '<div class="sai-step-actions">';
            html += '<span>' + escapeHtml((i18n.selected || 'Seleccionados') + ': ' + uiState.hijasSelection.length) + '</span>';
            html += '<button type="button" class="button button-primary" data-action="save-hijas"' + (uiState.loading ? ' disabled' : '') + '>' + escapeHtml(i18n.saveHijas || 'Guardar hijas y continuar') + '</button>';
            html += '</div>';

            if (state.hijas_ids && state.hijas_ids.length) {
                html += '<div class="sai-summary">';
                html += '<h3>' + escapeHtml(i18n.hijaAnchors || 'Anchors por hija') + '</h3>';
                state.hijas_ids.forEach(function (id) {
                    var anchors = state.hijas_anchors && state.hijas_anchors[id] ? state.hijas_anchors[id] : [];
                    html += '<div class="sai-summary-card">';
                    html += '<strong>' + escapeHtml('ID: ' + id) + ' · ' + escapeHtml((anchors.length || 0) + ' anchors') + '</strong>';
                    if (anchors.length) {
                        html += '<ul class="sai-anchor-list">';
                        anchors.forEach(function (anchor) {
                            html += '<li><strong>' + escapeHtml(anchor.text) + '</strong> <span class="sai-tag">' + escapeHtml(anchor.class) + '</span> <span class="sai-tag">' + escapeHtml(anchor.frequency) + '</span></li>';
                        });
                        html += '</ul>';
                    } else {
                        html += '<p class="sai-status">' + escapeHtml(i18n.noAnchors || 'No hay anchors disponibles.') + '</p>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            }

            return html;
        }

        function renderStepNietas() {
            var search = uiState.searches.nietas;
            var state = uiState.appState || {};
            var limit = state.limit_nietas || 1;
            var html = '<h2>' + escapeHtml(i18n.nietasHeading || 'Paso 3: Selecciona las nietas') + '</h2>';
            html += '<p class="sai-status">' + escapeHtml((i18n.selectionLimit || 'Límite de selección') + ': ' + limit) + '</p>';
            html += renderSearchControls('nietas', i18n.nietasHeading, false);
            html += '<div class="sai-results">';
            if (uiState.loading && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            } else if (search.hasSearched && search.items.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.noResults || 'Sin resultados.') + '</p>';
            } else {
                html += '<table class="widefat fixed">';
                html += '<thead><tr><th></th><th>' + escapeHtml(i18n.keywordLabel || 'Título') + '</th><th>' + escapeHtml(i18n.cannibalHeading || 'Canibalización') + '</th><th>' + escapeHtml(i18n.view || 'Ver') + '</th></tr></thead>';
                html += '<tbody>';
                search.items.forEach(function (item) {
                    var checked = uiState.nietasSelection.indexOf(item.id) !== -1 ? ' checked' : '';
                    var cannibal = item.cannibalization || {};
                    html += '<tr>';
                    html += '<td><input type="checkbox" data-action="select-nieta" value="' + item.id + '"' + checked + '></td>';
                    html += '<td><strong>' + escapeHtml(item.title) + '</strong><br><span class="sai-result-meta">' + escapeHtml(item.type) + '</span></td>';
                    html += '<td>' + renderCannibalBadge(cannibal) + '</td>';
                    html += '<td><a href="' + escapeHtml(item.link) + '" class="button" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.view || 'Ver') + '</a></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
            html += '</div>';
            html += renderPagination('nietas');

            html += '<div class="sai-step-actions">';
            html += '<span>' + escapeHtml((i18n.selected || 'Seleccionados') + ': ' + uiState.nietasSelection.length) + '</span>';
            html += '<button type="button" class="button button-primary" data-action="save-nietas"' + (uiState.loading ? ' disabled' : '') + '>' + escapeHtml(i18n.saveNietas || 'Guardar nietas y continuar') + '</button>';
            html += '</div>';

            return html;
        }

        function renderStepExport() {
            var state = uiState.appState || {};
            var madreAnchors = state.madre_anchors || [];
            var hijas = state.hijas_ids || [];
            var nietas = state.nietas_ids || [];
            var hijasAnchors = state.hijas_anchors || {};
            var totalHijaAnchors = 0;
            Object.keys(hijasAnchors).forEach(function (key) {
                if (Array.isArray(hijasAnchors[key])) {
                    totalHijaAnchors += hijasAnchors[key].length;
                }
            });

            var html = '<h2>' + escapeHtml(i18n.exportHeading || 'Paso 4: Exportar CSV') + '</h2>';
            html += '<div class="sai-summary">';
            html += '<p><strong>' + escapeHtml('Madre') + ':</strong> ' + escapeHtml(state.madre_id || '-') + ' · ' + escapeHtml((i18n.madreAnchors || 'Anchors de la madre') + ': ' + madreAnchors.length) + '</p>';
            html += '<p><strong>' + escapeHtml('Hijas') + ':</strong> ' + escapeHtml(hijas.length) + ' · ' + escapeHtml('Anchors: ' + totalHijaAnchors) + '</p>';
            html += '<p><strong>' + escapeHtml('Nietas') + ':</strong> ' + escapeHtml(nietas.length) + '</p>';
            html += '</div>';

            html += '<div class="sai-step-actions">';
            html += '<button type="button" class="button button-primary" data-action="export-csv"' + (uiState.loading ? ' disabled' : '') + '>' + escapeHtml(i18n.exportCsv || 'Exportar CSV') + '</button>';
            html += '</div>';

            return html;
        }

        function renderCannibalBadge(cannibal) {
            if (!cannibal || typeof cannibal !== 'object') {
                return '<span class="sai-semaforo sai-semaforo--amarillo">0</span>';
            }
            var level = cannibal.level || 'amarillo';
            var score = typeof cannibal.score === 'number' ? cannibal.score : 0;
            var reasons = Array.isArray(cannibal.reasons) ? cannibal.reasons.join(', ') : '';
            return '<span class="sai-semaforo sai-semaforo--' + level + '" title="' + escapeHtml(reasons) + '">' + escapeHtml(score) + '</span>';
        }

        function bindEvents() {
            container.addEventListener('click', function (event) {
                var target = event.target;
                var action = target.getAttribute('data-action');
                if (target.classList.contains('sai-lb-step-item')) {
                    var step = parseInt(target.getAttribute('data-step'), 10);
                    if (!isNaN(step)) {
                        goToStep(step);
                    }
                    return;
                }
                if (!action) {
                    return;
                }
                if (action === 'search') {
                    var stepKey = target.getAttribute('data-step');
                    performSearch(stepKey, 1);
                } else if (action === 'page-prev' || action === 'page-next') {
                    var step = target.getAttribute('data-step');
                    var search = uiState.searches[step];
                    if (!search) {
                        return;
                    }
                    var page = search.page || 1;
                    if (action === 'page-prev' && page > 1) {
                        performSearch(step, page - 1);
                    }
                    if (action === 'page-next' && page < search.totalPages) {
                        performSearch(step, page + 1);
                    }
                } else if (action === 'save-madre') {
                    saveMadre();
                } else if (action === 'save-hijas') {
                    saveHijas();
                } else if (action === 'save-nietas') {
                    saveNietas();
                } else if (action === 'reset-flow') {
                    resetFlow();
                } else if (action === 'export-csv') {
                    exportCsv();
                }
            });

            container.addEventListener('change', function (event) {
                var target = event.target;
                var action = target.getAttribute('data-action');
                var inputType = target.getAttribute('data-input');

                if (inputType === 'keyword') {
                    var stepKey = target.getAttribute('data-step');
                    if (uiState.searches[stepKey]) {
                        uiState.searches[stepKey].keyword = target.value.trim();
                    }
                } else if (inputType === 'include') {
                    var includeStep = target.getAttribute('data-step');
                    if (uiState.searches[includeStep]) {
                        uiState.searches[includeStep].includeBody = target.checked;
                    }
                } else if (inputType === 'canonical') {
                    uiState.canonicalInput = target.value;
                }

                if (action === 'select-madre') {
                    uiState.madreSelection = parseInt(target.value, 10) || null;
                } else if (action === 'select-hija') {
                    toggleSelection(uiState.hijasSelection, parseInt(target.value, 10));
                } else if (action === 'select-nieta') {
                    toggleSelection(uiState.nietasSelection, parseInt(target.value, 10));
                }
            });

            container.addEventListener('keydown', function (event) {
                var target = event.target;
                if (target.getAttribute('data-input') === 'keyword' && event.key === 'Enter') {
                    event.preventDefault();
                    var stepKey = target.getAttribute('data-step');
                    performSearch(stepKey, 1);
                }
            });
        }

        function toggleSelection(list, value) {
            if (!value) {
                return;
            }
            var index = list.indexOf(value);
            if (index === -1) {
                list.push(value);
            } else {
                list.splice(index, 1);
            }
        }

        loadState();
        render();
    });
})();
