(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var settings = window.AnchorsSinIA || {};
        var app = document.getElementById('sai-app');
        if (!app || !settings.restUrl) {
            return;
        }

        var state = {
            keyword: '',
            includeBody: false,
            loading: false,
            results: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            hasSearched: false,
            selectedPost: null,
            postDetail: null,
            anchors: [],
            quotas: null,
            notice: '',
            noticeType: 'success',
            canonical: '',
            extracting: false
        };

        var i18n = settings.i18n || {};

        function updateState(updates) {
            for (var key in updates) {
                if (Object.prototype.hasOwnProperty.call(updates, key)) {
                    state[key] = updates[key];
                }
            }
            render();
        }

        function getPreset(wordCount) {
            if (wordCount <= 700) {
                return { total: 4, exacta: 1, frase: 1, semantica: 2 };
            }
            if (wordCount <= 1500) {
                return { total: 6, exacta: 1, frase: 3, semantica: 2 };
            }
            return { total: 8, exacta: 1, frase: 4, semantica: 3 };
        }

        function renderNotice() {
            if (!state.notice) {
                return '';
            }
            var typeClass = state.noticeType === 'error' ? 'sai-notice-error' : 'sai-notice-success';
            return '<div class="sai-notice ' + typeClass + '">' + escapeHtml(state.notice) + '</div>';
        }

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

        function renderSearch() {
            var html = '';
            html += '<div class="sai-search">';
            html += '<div class="sai-search-controls">';
            html += '<label class="sai-label">' + escapeHtml(i18n.keywordLabel || 'Palabra clave (canónico)') +
                '<input type="text" id="sai-keyword" value="' + escapeHtml(state.keyword) + '" placeholder="' + escapeHtml(i18n.keywordLabel || '') + '"></label>';
            html += '<div class="sai-search-actions">';
            html += '<label class="sai-checkbox"><input type="checkbox" id="sai-in-body" ' + (state.includeBody ? 'checked' : '') + '> ' + escapeHtml(i18n.includeBody || '') + '</label>';
            html += '<button type="button" id="sai-search-btn" class="button button-primary">' + escapeHtml(i18n.search || 'Buscar') + '</button>';
            html += '</div></div>';
            html += renderNotice();
            html += '<div id="sai-results" class="sai-results">';

            if (state.loading) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            } else if (state.hasSearched && state.results.length === 0) {
                html += '<p class="sai-status">' + escapeHtml(i18n.noResults || 'Sin resultados.') + '</p>';
            } else {
                for (var i = 0; i < state.results.length; i++) {
                    var item = state.results[i];
                    html += '<div class="sai-result">';
                    html += '<div class="sai-result-info">';
                    html += '<h3>' + escapeHtml(item.title) + '</h3>';
                    html += '<p class="sai-result-meta">' + escapeHtml(item.type) + '</p>';
                    html += '</div>';
                    html += '<div class="sai-result-actions">';
                    html += '<a class="button" href="' + escapeHtml(item.link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.view || 'Ver') + '</a>';
                    html += '<button type="button" class="button button-primary" data-action="select" data-id="' + item.id + '">' + escapeHtml(i18n.select || 'Seleccionar') + '</button>';
                    html += '</div>';
                    html += '</div>';
                }
            }

            html += '</div>';

            if (state.totalPages > 1) {
                html += '<div class="sai-pagination">';
                html += '<button type="button" class="button" id="sai-prev" ' + (state.currentPage <= 1 ? 'disabled' : '') + '>&laquo;</button>';
                html += '<span>' + escapeHtml((i18n.pageLabel || 'Página') + ' ' + state.currentPage + ' / ' + state.totalPages) + '</span>';
                html += '<button type="button" class="button" id="sai-next" ' + (state.currentPage >= state.totalPages ? 'disabled' : '') + '>&raquo;</button>';
                html += '</div>';
            }

            html += '</div>';

            app.innerHTML = html;
            bindSearchEvents();
        }

        function renderDetail() {
            var preset = getPreset(state.postDetail && state.postDetail.word_count ? state.postDetail.word_count : 0);
            var html = '';
            html += '<div class="sai-detail">';
            html += '<div class="sai-detail-header">';
            html += '<button type="button" class="button" id="sai-back">' + escapeHtml(i18n.back || 'Volver a la búsqueda') + '</button>';
            html += '</div>';
            html += renderNotice();
            if (!state.postDetail) {
                if (!state.notice) {
                    html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
                }
                html += '</div>';
                app.innerHTML = html;
                bindDetailEvents();
                return;
            }
            html += '<div class="sai-detail-columns">';
            html += '<div class="sai-detail-left">';
            html += '<p><strong>' + escapeHtml(i18n.keywordLabel || 'Palabra clave (canónico)') + ':</strong> ' + escapeHtml(state.canonical) + '</p>';
            html += '<p><strong>' + escapeHtml(i18n.wordCount || 'Palabras') + ':</strong> ' + (state.postDetail.word_count || 0) + '</p>';
            html += '<p><strong>' + escapeHtml(i18n.preset || 'Preset') + ':</strong> ' + preset.total + ' (Exacta ' + preset.exacta + ' / Frase ' + preset.frase + ' / Semántica ' + preset.semantica + ')</p>';
            if (state.quotas) {
                html += '<p><strong>' + escapeHtml(i18n.usedQuotas || 'Cuotas usadas') + ':</strong> ' + state.quotas.total + ' (Exacta ' + state.quotas.exacta + ' / Frase ' + state.quotas.frase + ' / Semántica ' + state.quotas.semantica + ')</p>';
            }
            html += '</div>';
            html += '<div class="sai-detail-right">';
            html += '<h2>' + escapeHtml((state.postDetail && state.postDetail.title) || (state.selectedPost && state.selectedPost.title) || '') + '</h2>';
            html += '<textarea readonly class="sai-body-text" id="sai-body-text">' + escapeHtml(state.postDetail.body_text || '') + '</textarea>';
            html += '<button type="button" class="button button-primary" id="sai-extract" ' + (state.extracting ? 'disabled' : '') + '>' + escapeHtml(state.extracting ? (i18n.extracting || 'Extrayendo...') : (i18n.extractAnchors || 'Extraer anchors')) + '</button>';
            if (state.extracting) {
                html += '<p class="sai-status">' + escapeHtml(i18n.loading || 'Cargando...') + '</p>';
            }
            if (state.anchors && state.anchors.length > 0) {
                html += '<div class="sai-table-wrapper">';
                html += '<table class="widefat fixed">';
                html += '<thead><tr><th>' + escapeHtml((i18n.tableHeader && i18n.tableHeader[0]) || 'Anchor') + '</th><th>' + escapeHtml((i18n.tableHeader && i18n.tableHeader[1]) || 'Clasificación') + '</th><th>' + escapeHtml((i18n.tableHeader && i18n.tableHeader[2]) || 'Frecuencia') + '</th></tr></thead>';
                html += '<tbody>';
                for (var i = 0; i < state.anchors.length; i++) {
                    var anchor = state.anchors[i];
                    html += '<tr><td>' + escapeHtml(anchor.text) + '</td><td>' + escapeHtml(anchor.class) + '</td><td>' + escapeHtml(anchor.frequency) + '</td></tr>';
                }
                html += '</tbody></table>';
                html += '</div>';
                html += '<button type="button" class="button" id="sai-copy">' + escapeHtml(i18n.copyAnchors || 'Copiar anchors') + '</button>';
            } else if (!state.extracting && state.quotas) {
                html += '<p class="sai-status">' + escapeHtml(i18n.noAnchors || 'No hay anchors disponibles.') + '</p>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';

            app.innerHTML = html;
            bindDetailEvents();
        }

        function render() {
            if (state.selectedPost) {
                renderDetail();
            } else {
                renderSearch();
            }
        }

        function bindSearchEvents() {
            var keywordInput = document.getElementById('sai-keyword');
            var searchBtn = document.getElementById('sai-search-btn');
            var inBodyCheckbox = document.getElementById('sai-in-body');
            if (keywordInput) {
                keywordInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        state.keyword = keywordInput.value.trim();
                        performSearch(1);
                    }
                });
            }
            if (searchBtn) {
                searchBtn.addEventListener('click', function () {
                    state.keyword = keywordInput ? keywordInput.value.trim() : state.keyword;
                    performSearch(1);
                });
            }
            if (inBodyCheckbox) {
                inBodyCheckbox.addEventListener('change', function () {
                    state.includeBody = !!inBodyCheckbox.checked;
                });
            }

            var selectButtons = app.querySelectorAll('button[data-action="select"]');
            for (var i = 0; i < selectButtons.length; i++) {
                selectButtons[i].addEventListener('click', function (event) {
                    var id = parseInt(event.currentTarget.getAttribute('data-id'), 10);
                    var selected = null;
                    for (var j = 0; j < state.results.length; j++) {
                        if (state.results[j].id === id) {
                            selected = state.results[j];
                            break;
                        }
                    }
                    if (selected) {
                        loadPostDetail(selected);
                    }
                });
            }

            var prevBtn = document.getElementById('sai-prev');
            var nextBtn = document.getElementById('sai-next');
            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    if (state.currentPage > 1) {
                        performSearch(state.currentPage - 1);
                    }
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    if (state.currentPage < state.totalPages) {
                        performSearch(state.currentPage + 1);
                    }
                });
            }
        }

        function bindDetailEvents() {
            var backBtn = document.getElementById('sai-back');
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    updateState({
                        selectedPost: null,
                        postDetail: null,
                        anchors: [],
                        quotas: null,
                        notice: '',
                        extracting: false
                    });
                });
            }

            var extractBtn = document.getElementById('sai-extract');
            if (extractBtn) {
                extractBtn.addEventListener('click', function () {
                    if (!state.postDetail || state.extracting) {
                        return;
                    }
                    extractAnchors();
                });
            }

            var copyBtn = document.getElementById('sai-copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    copyAnchorsToClipboard();
                });
            }
        }

        function performSearch(page) {
            if (!state.keyword) {
                updateState({ notice: i18n.keywordRequired || 'Introduce una palabra clave.', noticeType: 'error' });
                return;
            }
            updateState({ loading: true, hasSearched: true, notice: '', noticeType: 'success' });
            var params = new URLSearchParams();
            params.append('kw', state.keyword);
            params.append('in_body', state.includeBody ? '1' : '0');
            params.append('page', page || 1);

            fetch(settings.restUrl + 'search?' + params.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': settings.nonce
                }
            })
                .then(handleFetchResponse)
                .then(function (data) {
                    updateState({
                        loading: false,
                        results: data.items || [],
                        total: data.total || 0,
                        totalPages: data.totalPages || 0,
                        currentPage: page || 1,
                        notice: '',
                        noticeType: 'success',
                        canonical: state.keyword
                    });
                })
                .catch(function (error) {
                    updateState({
                        loading: false,
                        notice: (i18n.loadError || 'Ocurrió un error. Inténtalo nuevamente.') + ' ' + (error && error.message ? error.message : ''),
                        noticeType: 'error'
                    });
                });
        }

        function loadPostDetail(selected) {
            updateState({
                selectedPost: selected,
                postDetail: null,
                anchors: [],
                quotas: null,
                notice: '',
                extracting: false,
                canonical: state.keyword
            });

            fetch(settings.restUrl + 'post/' + selected.id, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': settings.nonce
                }
            })
                .then(handleFetchResponse)
                .then(function (data) {
                    updateState({ postDetail: data, canonical: state.keyword });
                })
                .catch(function (error) {
                    updateState({
                        notice: (i18n.loadError || 'Ocurrió un error. Inténtalo nuevamente.') + ' ' + (error && error.message ? error.message : ''),
                        noticeType: 'error'
                    });
                });
        }

        function extractAnchors() {
            updateState({ extracting: true, notice: '', anchors: [], quotas: null });
            var payload = {
                id: state.selectedPost ? state.selectedPost.id : 0,
                canonical: state.canonical || '',
                body_text: state.postDetail ? state.postDetail.body_text || '' : ''
            };

            fetch(settings.restUrl + 'extract', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce
                },
                body: JSON.stringify(payload)
            })
                .then(handleFetchResponse)
                .then(function (data) {
                    // Si el backend devolvió word_count actualizado, reflejarlo.
                    var updatedDetail = state.postDetail ? JSON.parse(JSON.stringify(state.postDetail)) : null;
                    if (updatedDetail && typeof data.word_count === 'number') {
                        updatedDetail.word_count = data.word_count;
                    }
                    updateState({
                        extracting: false,
                        anchors: (data && data.anchors) ? data.anchors : [],
                        quotas: (data && data.quotas) ? data.quotas : null,
                        postDetail: updatedDetail || state.postDetail,
                        notice: '',
                        noticeType: 'success'
                    });
                })
                .catch(function (error) {
                    updateState({
                        extracting: false,
                        notice: (i18n.loadError || 'Ocurrió un error. Inténtalo nuevamente.') + ' ' + (error && error.message ? error.message : ''),
                        noticeType: 'error'
                    });
                });
        }

        function copyAnchorsToClipboard() {
            if (!state.anchors || state.anchors.length === 0) {
                updateState({ notice: i18n.noAnchors || 'No hay anchors disponibles.', noticeType: 'error' });
                return;
            }
            var lines = [];
            for (var i = 0; i < state.anchors.length; i++) {
                var row = state.anchors[i];
                lines.push(row.text + '\t' + row.class + '\t' + row.frequency);
            }
            var output = lines.join('\n');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(output)
                    .then(function () {
                        updateState({ notice: i18n.copySuccess || 'Anchors copiados al portapapeles.', noticeType: 'success' });
                    })
                    .catch(function () {
                        fallbackCopy(output);
                    });
            } else {
                fallbackCopy(output);
            }
        }

        function fallbackCopy(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                var success = document.execCommand('copy');
                updateState({
                    notice: success ? (i18n.copySuccess || 'Anchors copiados al portapapeles.') : (i18n.copyError || 'No se pudo copiar. Copie manualmente.'),
                    noticeType: success ? 'success' : 'error'
                });
            } catch (err) {
                updateState({
                    notice: i18n.copyError || 'No se pudo copiar. Copie manualmente.',
                    noticeType: 'error'
                });
            }
            document.body.removeChild(textarea);
        }

        function handleFetchResponse(response) {
            if (!response.ok) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    var message = data && data.message ? data.message : response.statusText;
                    throw new Error(message || 'Error');
                });
            }
            return response.json();
        }

        render();
    });
})();

