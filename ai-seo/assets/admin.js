(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Shared AJAX plumbing
    //
    // Every request to admin-ajax.php goes through aiSeoRequest().  It keeps
    // one copy of the nonce, renews it over the WordPress heartbeat when the
    // server rejects it, and retries the request once.  A nonce lives for 12
    // hours, so an editor screen left open overnight used to answer every
    // click with "HTTP 403" until the page was reloaded.
    // ------------------------------------------------------------------

    var nonceRefresh    = null;
    var heartbeatBound  = false;

    function heartbeatAvailable() {
        return !!( window.jQuery && window.wp && window.wp.heartbeat );
    }

    function bindHeartbeat() {
        if ( heartbeatBound || ! heartbeatAvailable() ) {
            return;
        }

        heartbeatBound = true;

        window.jQuery( document ).on( 'heartbeat-send.ai-seo', function ( event, data ) {
            data.ai_seo_refresh_nonce = 1;
        } );

        window.jQuery( document ).on( 'heartbeat-tick.ai-seo', function ( event, data ) {
            if ( data && data.ai_seo_nonce && window.aiSeo ) {
                window.aiSeo.nonce = data.ai_seo_nonce;
            }
        } );
    }

    /**
     * Ask the server for a fresh nonce via the heartbeat API.
     *
     * @return {Promise<string>} Resolves with the new nonce.
     */
    function refreshNonce() {
        if ( nonceRefresh ) {
            return nonceRefresh;
        }

        if ( ! heartbeatAvailable() ) {
            return Promise.reject( new Error( 'no-heartbeat' ) );
        }

        // The page may have loaded before the heartbeat API was ready.
        bindHeartbeat();

        nonceRefresh = new Promise( function ( resolve, reject ) {
            var $doc  = window.jQuery( document );
            var timer = window.setTimeout( function () {
                cleanup();
                reject( new Error( 'timeout' ) );
            }, 15000 );

            function cleanup() {
                window.clearTimeout( timer );
                $doc.off( 'heartbeat-tick', handler );
                $doc.off( 'heartbeat-error', errorHandler );
                nonceRefresh = null;
            }

            function errorHandler() {
                cleanup();
                reject( new Error( 'heartbeat-error' ) );
            }

            function handler( event, data ) {
                if ( ! data || ! data.ai_seo_nonce ) {
                    return;
                }
                if ( window.aiSeo ) {
                    window.aiSeo.nonce = data.ai_seo_nonce;
                }
                var fresh = data.ai_seo_nonce;
                cleanup();
                resolve( fresh );
            }

            $doc.on( 'heartbeat-tick', handler );
            $doc.on( 'heartbeat-error', errorHandler );
            window.wp.heartbeat.connectNow();
        } );

        return nonceRefresh;
    }

    /**
     * Base64-encode UTF-8 text for transport.
     *
     * Editor content is posted encoded: raw HTML in the request body makes web
     * application firewalls answer admin-ajax.php with HTTP 403 before
     * WordPress runs, which silently kills the analyses that send the whole
     * post body while simpler actions keep working.
     *
     * @param {string} text Content to encode.
     * @return {?string} Base64 string, or null when encoding is unavailable.
     */
    function encodeContent( text ) {
        try {
            var bytes  = new TextEncoder().encode( String( text ) );
            var binary = '';
            var chunk  = 0x8000;

            for ( var i = 0; i < bytes.length; i += chunk ) {
                binary += String.fromCharCode.apply( null, bytes.subarray( i, i + chunk ) );
            }

            return window.btoa( binary );
        } catch ( e ) {
            return null;
        }
    }

    function aiSeoError( message, code ) {
        var error = new Error( message );
        error.aiSeoCode = code || 'error';
        return error;
    }

    /**
     * POST to admin-ajax.php with automatic nonce renewal.
     *
     * @param {string} action  AJAX action name (without the wp_ajax_ prefix).
     * @param {Object} params  Extra POST fields.
     * @return {Promise<Object>} Resolves with the payload from wp_send_json_success().
     */
    function aiSeoRequest( action, params ) {
        return send( false );

        function send( isRetry ) {
            var body = new FormData();
            body.append( 'action', action );
            body.append( 'nonce', ( window.aiSeo && window.aiSeo.nonce ) || '' );

            Object.keys( params || {} ).forEach( function ( key ) {
                var value = params[ key ];

                if ( value === undefined || value === null ) {
                    value = '';
                }

                if ( key === 'post_content' ) {
                    var encoded = encodeContent( value );
                    if ( null !== encoded ) {
                        body.append( 'post_content_b64', encoded );
                        return;
                    }
                }

                body.append( key, value );
            } );

            return fetch( window.aiSeo.ajaxUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            } ).catch( function () {
                throw aiSeoError( 'Nettverksfeil – kunne ikke kontakte serveren.', 'network' );
            } ).then( function ( response ) {
                return response.text().then( function ( text ) {
                    return handle( response, text, isRetry );
                } );
            } );
        }

        function retryOrFail( isRetry, message, code ) {
            if ( isRetry ) {
                return Promise.reject( aiSeoError( message, code ) );
            }

            return refreshNonce().then(
                function () {
                    return send( true );
                },
                function () {
                    return Promise.reject( aiSeoError( message, code ) );
                }
            );
        }

        function handle( response, text, isRetry ) {
            var trimmed = ( text || '' ).trim();
            var data    = null;

            try {
                data = JSON.parse( trimmed );
            } catch ( e ) {
                data = null;
            }

            if ( data && typeof data === 'object' && 'success' in data ) {
                if ( data.success ) {
                    return data.data;
                }

                var payload = data.data;
                var code    = payload && payload.code ? payload.code : '';
                var message = '';

                if ( typeof payload === 'string' ) {
                    message = payload;
                } else if ( payload && payload.message ) {
                    message = payload.message;
                }

                if ( code === 'invalid_nonce' ) {
                    return retryOrFail( isRetry, message || 'Sikkerhetsnøkkelen er utløpt. Last siden på nytt og prøv igjen.', code );
                }

                return Promise.reject( aiSeoError( message || 'En ukjent feil oppsto.', code || 'error' ) );
            }

            // Bare "-1" is WordPress' answer to a rejected nonce, "0" means the
            // action is not registered or the session is gone.
            if ( trimmed === '-1' || response.status === 403 ) {
                return retryOrFail(
                    isRetry,
                    'Sikkerhetsnøkkelen er utløpt eller blokkert. Last siden på nytt og prøv igjen.',
                    'invalid_nonce'
                );
            }

            if ( trimmed === '0' || response.status === 400 ) {
                return Promise.reject( aiSeoError(
                    'Handlingen ble avvist av WordPress. Sjekk at du er logget inn, og at programtillegget er aktivt.',
                    'unknown_action'
                ) );
            }

            if ( response.status === 401 ) {
                return Promise.reject( aiSeoError( 'Du er logget ut. Logg inn på nytt og prøv igjen.', 'logged_out' ) );
            }

            if ( ! response.ok ) {
                return Promise.reject( aiSeoError( 'Forespørselen feilet (HTTP ' + response.status + ').', 'http_error' ) );
            }

            return Promise.reject( aiSeoError( 'Kunne ikke tolke svaret fra serveren.', 'parse_error' ) );
        }
    }

    /**
     * Build a red error paragraph, text-only so server messages cannot inject markup.
     */
    function errorParagraph( error ) {
        var p = document.createElement( 'p' );
        p.style.color = '#cc0000';
        p.textContent = ( error && error.message ) || 'En ukjent feil oppsto.';
        return p;
    }

    bindHeartbeat();

    document.addEventListener('DOMContentLoaded', function () {
        // --- SERP Preview live update ---
        var titleInput = document.getElementById('ai_seo_meta_title');
        var descInput  = document.getElementById('ai_seo_meta_description');
        var serpTitle   = document.getElementById('ai-seo-serp-title');
        var serpDesc    = document.getElementById('ai-seo-serp-desc');

        if (titleInput && serpTitle) {
            titleInput.addEventListener('input', function () {
                serpTitle.textContent = this.value || document.getElementById('title')?.value || '';
                updateCharCount(this);
            });
        }

        if (descInput && serpDesc) {
            descInput.addEventListener('input', function () {
                serpDesc.textContent = this.value || '';
                updateCharCount(this);
            });
        }

        // --- Character counters ---
        function updateCharCount(el) {
            var counters = document.querySelectorAll('.ai-seo-char-count');
            counters.forEach(function (counter) {
                if (counter.dataset.target === el.id) {
                    var max = parseInt(counter.dataset.max, 10);
                    var len = el.value.length;
                    counter.textContent = len + '/' + max;
                    counter.classList.toggle('ai-seo-over-limit', len > max);
                }
            });
        }

        // --- AI Action Buttons ---
        var btnGenDesc        = document.getElementById('ai-seo-generate-desc');
        var btnSuggestTitle   = document.getElementById('ai-seo-suggest-title');
        var btnSuggestKeyword = document.getElementById('ai-seo-suggest-keyword');
        var btnAnalyze        = document.getElementById('ai-seo-analyze-keywords');
        var btnSuggestLinks   = document.getElementById('ai-seo-suggest-links');
        var spinner           = document.getElementById('ai-seo-spinner');
        var resultBox         = document.getElementById('ai-seo-result');
        var errorBox          = document.getElementById('ai-seo-error');
        var keywordInput      = document.getElementById('ai_seo_focus_keyword');

        var allButtons = [btnGenDesc, btnSuggestTitle, btnSuggestKeyword, btnAnalyze, btnSuggestLinks];

        if (btnGenDesc) {
            btnGenDesc.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_generate_description', { post_id: postId }, function (data) {
                    if (descInput) {
                        descInput.value = data.text;
                        descInput.dispatchEvent(new Event('input'));
                    }
                    showResult('Metabeskrivelse generert: ' + data.text);
                    refreshSeoScore();
                });
            });
        }

        if (btnSuggestTitle) {
            btnSuggestTitle.addEventListener('click', function () {
                var postId  = this.dataset.postId;
                var keyword = document.getElementById('ai_seo_focus_keyword');
                doAjax('ai_seo_suggest_title', {
                    post_id: postId,
                    keyword: keyword ? keyword.value : ''
                }, function (data) {
                    showResult(data.text, true);
                });
            });
        }

        if (btnSuggestKeyword) {
            btnSuggestKeyword.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_suggest_keyword', { post_id: postId }, function (data) {
                    if (keywordInput && data.keyword) {
                        keywordInput.value = data.keyword;
                        keywordInput.dispatchEvent(new Event('input'));
                    }
                    showResult('Fokusord satt: ' + data.keyword);
                    refreshSeoScore();
                });
            });
        }

        if (btnAnalyze) {
            btnAnalyze.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_analyze_keywords', { post_id: postId }, function (data) {
                    showResult(data.text, true);
                });
            });
        }

        if (btnSuggestLinks) {
            btnSuggestLinks.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_suggest_links', { post_id: postId }, function (data) {
                    showResult(data.text, true);
                });
            });
        }

        /**
         * Perform AJAX request to admin-ajax.php.
         */
        function doAjax(action, params, onSuccess) {
            hideMessages();
            showSpinner(true);
            disableButtons(true);

            aiSeoRequest(action, params)
                .then(function (data) {
                    showSpinner(false);
                    disableButtons(false);
                    onSuccess(data);
                })
                .catch(function (error) {
                    showSpinner(false);
                    disableButtons(false);
                    showError(error.message || 'En ukjent feil oppsto.');
                });
        }

        function showSpinner(show) {
            if (spinner) {
                spinner.style.display = show ? 'flex' : 'none';
            }
        }

        function showResult(text, multiline) {
            if (!resultBox) return;
            if (multiline) {
                resultBox.innerHTML = '';
                var pre = document.createElement('pre');
                pre.textContent = text;
                resultBox.appendChild(pre);
            } else {
                resultBox.textContent = text;
            }
            resultBox.style.display = 'block';
        }

        function showError(message) {
            if (!errorBox) return;
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }

        function hideMessages() {
            if (resultBox) {
                resultBox.style.display = 'none';
                resultBox.textContent = '';
            }
            if (errorBox) {
                errorBox.style.display = 'none';
                errorBox.textContent = '';
            }
        }

        function disableButtons(disabled) {
            allButtons.forEach(function (btn) {
                if (btn) btn.disabled = disabled;
            });
        }

        // --- Settings page: toggle API key visibility ---
        var toggleKeyBtn = document.getElementById('ai-seo-toggle-key');
        var apiKeyInput  = document.getElementById('ai_seo_api_key');

        if (toggleKeyBtn && apiKeyInput) {
            toggleKeyBtn.addEventListener('click', function () {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                } else {
                    apiKeyInput.type = 'password';
                }
            });

            apiKeyInput.addEventListener('focus', function () {
                if (this.value && this.type === 'password') {
                    this.value = '';
                    this.type = 'text';
                }
            });
        }

        // --- Settings page: filter models by provider ---
        var providerSelect = document.getElementById('ai_seo_provider');
        var modelSelect    = document.getElementById('ai_seo_model');

        if (providerSelect && modelSelect) {
            function filterModels() {
                var selected = providerSelect.value;
                var groups = modelSelect.querySelectorAll('optgroup');
                var firstVisible = null;

                groups.forEach(function (group) {
                    if (group.dataset.provider === selected) {
                        group.style.display = '';
                        group.querySelectorAll('option').forEach(function (opt) {
                            opt.style.display = '';
                            if (!firstVisible) firstVisible = opt;
                        });
                    } else {
                        group.style.display = 'none';
                        group.querySelectorAll('option').forEach(function (opt) {
                            opt.style.display = 'none';
                        });
                    }
                });

                var currentOption = modelSelect.options[modelSelect.selectedIndex];
                if (currentOption && currentOption.style.display === 'none' && firstVisible) {
                    firstVisible.selected = true;
                }
            }

            providerSelect.addEventListener('change', filterModels);
            filterModels();
        }

        // --- Social image upload (WordPress media library) ---
        var uploadBtn  = document.getElementById('ai-seo-upload-social-image');
        var removeBtn  = document.getElementById('ai-seo-remove-social-image');
        var imageInput = document.getElementById('ai_seo_social_image_id');
        var preview    = document.getElementById('ai-seo-social-image-preview');

        if (uploadBtn && imageInput) {
            uploadBtn.addEventListener('click', function (e) {
                e.preventDefault();

                if (typeof wp === 'undefined' || !wp.media) return;

                var frame = wp.media({
                    title: 'Velg sosialt bilde',
                    button: { text: 'Bruk dette bildet' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    imageInput.value = attachment.id;

                    if (preview) {
                        var imgUrl = attachment.sizes && attachment.sizes.medium
                            ? attachment.sizes.medium.url
                            : attachment.url;
                        preview.innerHTML = '<img src="' + imgUrl + '" style="max-width:300px;height:auto;" />';
                        preview.style.display = 'block';
                    }

                    if (removeBtn) {
                        removeBtn.style.display = '';
                    }
                });

                frame.open();
            });
        }

        if (removeBtn && imageInput) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                imageInput.value = '';
                if (preview) {
                    preview.innerHTML = '';
                    preview.style.display = 'none';
                }
                removeBtn.style.display = 'none';
            });
        }

        // --- Refresh SEO Score ---
        var btnRefreshScore = document.getElementById('ai-seo-refresh-score');
        var scoreBadge      = document.getElementById('ai-seo-score-badge');
        var scoreValue      = document.getElementById('ai-seo-score-value');
        var checklist       = document.getElementById('ai-seo-checklist');

        function refreshSeoScore() {
            if (!btnRefreshScore || !checklist) return;

            var postId       = btnRefreshScore.dataset.postId;
            // Re-query elements fresh to avoid stale references.
            var currentTitle = document.getElementById('ai_seo_meta_title');
            var currentDesc  = document.getElementById('ai_seo_meta_description');
            var currentKw    = document.getElementById('ai_seo_focus_keyword');

            // Get current editor content (supports both Gutenberg and Classic editor).
            var editorContent = '';
            try {
                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                    editorContent = wp.data.select('core/editor').getEditedPostContent() || '';
                }
            } catch (e) {
                // Gutenberg not available, try Classic Editor.
            }
            if (!editorContent) {
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    editorContent = tinymce.activeEditor.getContent();
                } else {
                    var contentArea = document.getElementById('content');
                    if (contentArea) {
                        editorContent = contentArea.value;
                    }
                }
            }

            btnRefreshScore.disabled = true;
            btnRefreshScore.textContent = 'Oppdaterer…';

            aiSeoRequest('ai_seo_refresh_score', {
                post_id: postId,
                seo_title: currentTitle ? currentTitle.value : '',
                seo_description: currentDesc ? currentDesc.value : '',
                seo_keyword: currentKw ? currentKw.value : '',
                post_content: editorContent
            })
                .then(function (data) {
                    btnRefreshScore.disabled = false;
                    btnRefreshScore.textContent = 'Oppdater analyse';
                    if (data) {
                        renderSeoScore(data);
                    }
                })
                .catch(function (error) {
                    btnRefreshScore.disabled = false;
                    btnRefreshScore.textContent = 'Oppdater analyse';
                    showError(error.message || 'Kunne ikke oppdatere analysen.');
                });
        }

        function renderSeoScore(data) {
            // Update score number.
            if (scoreValue) {
                scoreValue.textContent = data.score;
            }

            // Update badge color.
            if (scoreBadge) {
                scoreBadge.className = 'ai-seo-readability-score ai-seo-score-' + data.rating;
            }

            // Update checklist.
            if (checklist && data.checks) {
                var html = '';
                data.checks.forEach(function (check) {
                    var cls  = check.pass ? 'pass' : 'fail';
                    var icon = check.pass ? '&#10004;' : '&#10008;';
                    html += '<li class="ai-seo-check-' + cls + '">';
                    html += '<span class="ai-seo-check-icon">' + icon + '</span>';
                    html += escapeHtml(check.label);
                    if (check.detail) {
                        html += ' <span class="ai-seo-check-detail">(' + escapeHtml(check.detail) + ')</span>';
                    }
                    html += '</li>';
                });
                checklist.innerHTML = html;
            }
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        if (btnRefreshScore) {
            btnRefreshScore.addEventListener('click', refreshSeoScore);
        }

        // --- Readability highlight ---
        var btnHighlight    = document.getElementById('ai-seo-highlight-readability');
        var highlightPanel  = document.getElementById('ai-seo-highlight-panel');
        var highlightContent = document.getElementById('ai-seo-highlight-content');

        if (btnHighlight) {
            btnHighlight.addEventListener('click', function () {
                var postId = this.dataset.postId;

                // Get current editor content.
                var editorContent = '';
                try {
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                        editorContent = wp.data.select('core/editor').getEditedPostContent() || '';
                    }
                } catch (e) {}
                if (!editorContent) {
                    if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                        editorContent = tinymce.activeEditor.getContent();
                    } else {
                        var contentArea = document.getElementById('content');
                        if (contentArea) editorContent = contentArea.value;
                    }
                }

                btnHighlight.disabled = true;
                btnHighlight.textContent = 'Analyserer\u2026';

                aiSeoRequest('ai_seo_readability_highlight', {
                    post_id: postId,
                    post_content: editorContent
                })
                    .then(function (data) {
                        btnHighlight.disabled = false;
                        btnHighlight.textContent = 'Vis i teksten';

                        if (data && highlightContent && highlightPanel) {
                            highlightContent.innerHTML = data.html;
                            highlightPanel.style.display = 'block';
                            initHighlightNavigation();
                        }
                    })
                    .catch(function (error) {
                        btnHighlight.disabled = false;
                        btnHighlight.textContent = 'Vis i teksten';
                        showError(error.message || 'Kunne ikke analysere teksten.');
                    });
            });
        }

        // --- Click-to-navigate: scroll to sentence in editor ---
        var previousHighlightEl = null;
        var previousHighlightDoc = null;

        function clearPreviousHighlight() {
            if (previousHighlightEl) {
                try {
                    previousHighlightEl.style.outline = '';
                    previousHighlightEl.style.outlineOffset = '';
                    previousHighlightEl.style.borderRadius = '';
                } catch (e) {}
                previousHighlightEl = null;
                previousHighlightDoc = null;
            }
        }

        function initHighlightNavigation() {
            if (!highlightContent) return;

            highlightContent.addEventListener('click', function (e) {
                var span = e.target.closest('[data-sentence]');
                if (!span) return;

                var sentence = span.textContent.trim();
                var searchText = sentence.substring(0, 60);
                scrollToSentenceInEditor(searchText);
            });
        }

        function scrollToSentenceInEditor(searchText) {
            var found = false;

            // Try Gutenberg editor (iframe variant).
            var iframe = document.querySelector('iframe[name="editor-canvas"]');
            if (!found && iframe && iframe.contentDocument) {
                found = findAndScrollToText(iframe.contentDocument.body, searchText, iframe.contentDocument);
            }

            // Try Gutenberg editor (non-iframe variant).
            if (!found) {
                var wrapper = document.querySelector('.editor-styles-wrapper');
                if (wrapper) {
                    found = findAndScrollToText(wrapper, searchText, document);
                }
            }

            // Try Classic Editor (TinyMCE).
            if (!found && typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                found = findAndScrollInTinyMCE(tinymce.activeEditor, searchText);
            }

            // Try plain textarea.
            if (!found) {
                var textarea = document.getElementById('content');
                if (textarea) {
                    scrollTextareaToText(textarea, searchText);
                }
            }
        }

        function highlightElement(el, doc) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });

            clearPreviousHighlight();
            el.style.outline = '2px solid #2271b1';
            el.style.outlineOffset = '2px';
            el.style.borderRadius = '2px';
            previousHighlightEl = el;
            previousHighlightDoc = doc;
        }

        function findAndScrollToText(container, searchText, doc) {
            var needle = searchText.toLowerCase();

            // First search block-level elements by their full textContent.
            // This handles inline formatting (strong, em, a) that splits
            // text across multiple DOM nodes.
            var blocks = container.querySelectorAll('p, li, h1, h2, h3, h4, h5, h6, td, th, blockquote');
            for (var i = 0; i < blocks.length; i++) {
                if (blocks[i].textContent.toLowerCase().indexOf(needle) !== -1) {
                    highlightElement(blocks[i], doc);
                    return true;
                }
            }

            // Fallback: search individual text nodes.
            var walker = doc.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
            var node;
            while ((node = walker.nextNode())) {
                if (node.textContent.toLowerCase().indexOf(needle) !== -1) {
                    highlightElement(node.parentElement, doc);
                    return true;
                }
            }
            return false;
        }

        function findAndScrollInTinyMCE(editor, searchText) {
            var body = editor.getBody();
            var doc = editor.getDoc();
            var walker = doc.createTreeWalker(body, NodeFilter.SHOW_TEXT, null, false);
            var node;
            var needle = searchText.toLowerCase();

            while ((node = walker.nextNode())) {
                var nodeText = node.textContent.toLowerCase();
                var pos = nodeText.indexOf(needle);
                if (pos !== -1) {
                    var range = doc.createRange();
                    range.setStart(node, pos);
                    range.setEnd(node, Math.min(pos + searchText.length, node.textContent.length));
                    editor.selection.setRng(range);
                    editor.selection.scrollIntoView();
                    return true;
                }
            }
            return false;
        }

        function scrollTextareaToText(textarea, searchText) {
            var text = textarea.value.toLowerCase();
            var pos = text.indexOf(searchText.toLowerCase());
            if (pos !== -1) {
                textarea.focus();
                textarea.setSelectionRange(pos, pos + searchText.length);
                // Approximate scroll position.
                var lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight, 10) || 20;
                var charsPerLine = Math.floor(textarea.clientWidth / 8);
                var lineNumber = charsPerLine > 0 ? Math.floor(pos / charsPerLine) : 0;
                textarea.scrollTop = Math.max(0, lineNumber * lineHeight - textarea.clientHeight / 2);
            }
        }

        // --- Migration buttons ---
        var migrateBtns = document.querySelectorAll('.ai-seo-migrate-btn');
        migrateBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var source    = this.dataset.source;
                var resultBox = document.getElementById('ai-seo-' + source + '-result');
                var overwriteCheckbox = document.getElementById('ai-seo-' + source + '-overwrite');
                var overwrite = overwriteCheckbox ? overwriteCheckbox.checked : false;

                if (overwrite && !confirm('Er du sikker på at du vil overskrive eksisterende AI SEO-data?')) {
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Importerer\u2026';

                if (resultBox) {
                    resultBox.style.display = 'none';
                    resultBox.className = 'ai-seo-migration-result';
                    resultBox.textContent = '';
                }

                var params = { source: source };
                if (overwrite) {
                    params.overwrite = '1';
                }

                function restoreButton() {
                    btn.disabled = false;
                    btn.textContent = source === 'yoast' ? 'Importer fra Yoast SEO' : 'Importer fra Rank Math';
                }

                aiSeoRequest('ai_seo_run_migration', params)
                    .then(function (data) {
                        restoreButton();
                        if (resultBox) {
                            resultBox.className = 'ai-seo-migration-result ai-seo-migration-success';
                            resultBox.textContent = 'Migrering fullf\u00f8rt! ' + data.migrated + ' innlegg oppdatert, ' + data.skipped + ' hoppet over.';
                            resultBox.style.display = 'block';
                        }
                    })
                    .catch(function (error) {
                        restoreButton();
                        if (resultBox) {
                            resultBox.className = 'ai-seo-migration-result ai-seo-migration-error';
                            resultBox.textContent = error.message || 'En feil oppsto under migreringen.';
                            resultBox.style.display = 'block';
                        }
                    });
            });
        });

        // --- Inline editing in post list ---
        var inlineEdits = document.querySelectorAll('.ai-seo-inline-edit');
        inlineEdits.forEach(function (wrapper) {
            var valueSpan = wrapper.querySelector('.ai-seo-inline-value');
            var inputEl   = wrapper.querySelector('.ai-seo-inline-input');
            if (!valueSpan || !inputEl) return;

            // Click to start editing.
            valueSpan.addEventListener('click', function () {
                valueSpan.style.display = 'none';
                inputEl.style.display = '';
                inputEl.focus();
                inputEl.select();
            });

            // Save on blur or Enter key.
            function saveInline() {
                var postId = wrapper.dataset.postId;
                var field  = wrapper.dataset.field;
                var value  = inputEl.value;

                inputEl.style.display = 'none';
                valueSpan.style.display = '';

                if (value) {
                    var maxLen = parseInt(wrapper.dataset.max, 10) || 160;
                    var display = value.length > 60 ? value.substring(0, 60) + '...' : value;
                    valueSpan.textContent = display;
                    valueSpan.classList.remove('ai-seo-inline-empty');
                } else {
                    valueSpan.textContent = '\u2014';
                    valueSpan.classList.add('ai-seo-inline-empty');
                }

                wrapper.classList.add('ai-seo-inline-saving');
                wrapper.classList.remove('ai-seo-inline-error');
                wrapper.removeAttribute('title');

                aiSeoRequest('ai_seo_inline_save_meta', {
                    post_id: postId,
                    field: field,
                    value: value
                })
                    .then(function () {
                        wrapper.classList.remove('ai-seo-inline-saving');
                    })
                    .catch(function (error) {
                        // A silent failure here loses the edit without telling
                        // anyone, so mark the cell and keep the reason on hover.
                        wrapper.classList.remove('ai-seo-inline-saving');
                        wrapper.classList.add('ai-seo-inline-error');
                        wrapper.title = error.message || 'Lagringen feilet.';
                    });
            }

            inputEl.addEventListener('blur', saveInline);
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    inputEl.blur();
                } else if (e.key === 'Escape') {
                    inputEl.style.display = 'none';
                    valueSpan.style.display = '';
                }
            });
        });

        // --- Copy cornerstone URL to clipboard ---
        var copyLinks = document.querySelectorAll('.ai-seo-copy-url');
        copyLinks.forEach(function (el) {
            el.addEventListener('click', function () {
                var url = this.textContent.trim();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function () {
                        el.classList.add('ai-seo-copied');
                        setTimeout(function () {
                            el.classList.remove('ai-seo-copied');
                        }, 1500);
                    });
                }
            });
            el.style.cursor = 'pointer';
        });

    // AI Quality Analysis
    const aiQualityBtn     = document.getElementById( 'ai-seo-run-ai-quality' );
    const aiQualitySpinner = document.getElementById( 'ai-quality-spinner' );
    const aiQualityResults = document.getElementById( 'ai-seo-quality-results' );

    if ( aiQualityBtn ) {
        aiQualityBtn.addEventListener( 'click', function () {
            const postId   = document.getElementById( 'post_ID' ) ? document.getElementById( 'post_ID' ).value : '';
            const seoTitle = document.getElementById( 'ai_seo_meta_title' ) ? document.getElementById( 'ai_seo_meta_title' ).value : '';
            const seoDesc  = document.getElementById( 'ai_seo_meta_description' ) ? document.getElementById( 'ai_seo_meta_description' ).value : '';

            // Get editor content (Gutenberg or Classic Editor)
            let editorContent = '';
            if ( typeof wp !== 'undefined' && wp.data && wp.data.select( 'core/editor' ) ) {
                editorContent = wp.data.select( 'core/editor' ).getEditedPostContent() || '';
            } else if ( document.getElementById( 'content' ) ) {
                editorContent = document.getElementById( 'content' ).value;
            }

            aiQualityBtn.disabled        = true;
            aiQualitySpinner.style.display = 'inline';
            aiQualityResults.innerHTML   = '';

            aiSeoRequest( 'ai_seo_run_ai_quality', {
                post_id:         postId,
                seo_title:       seoTitle,
                seo_description: seoDesc,
                post_content:    editorContent
            } )
                .then( function ( data ) {
                    aiQualityBtn.disabled          = false;
                    aiQualitySpinner.style.display = 'none';
                    renderAiQualityResults( data );
                } )
                .catch( function ( error ) {
                    aiQualityBtn.disabled          = false;
                    aiQualitySpinner.style.display = 'none';
                    aiQualityResults.textContent   = '';
                    aiQualityResults.appendChild( errorParagraph( error ) );
                } );
        } );
    }

    function renderAiQualityResults( data ) {
        if ( ! aiQualityResults ) { return; }
        const checks = data.checks || [];
        const cached = data.cached
            ? ' <em style="font-size:11px;color:#888;">(fra cache)</em>'
            : '';

        let html = '<p><strong>AI-kvalitetssjekker</strong>' + cached + '</p>';
        html += '<ul style="margin:0;padding:0;list-style:none;">';

        checks.forEach( function ( check ) {
            const icon     = check.pass ? '\u2713' : '\u2717';
            const color    = check.pass ? 'green' : '#cc0000';
            const detail   = check.detail
                ? ' <span style="color:#666;">(' + check.detail + ')</span>'
                : '';
            const feedback = check.feedback
                ? '<br><small style="color:#555;margin-left:22px;">' + check.feedback + '</small>'
                : '';
            html += '<li style="padding:3px 0;">'
                + '<span style="color:' + color + ';font-weight:bold;margin-right:6px;">' + icon + '</span>'
                + check.label + detail + feedback
                + '</li>';
        } );

        html += '</ul>';
        html += '<p style="margin-top:8px;"><a href="#" id="ai-quality-force-refresh" style="font-size:12px;">Tving ny analyse (t\u00f8m cache)</a></p>';

        aiQualityResults.innerHTML = html;

        const forceRefreshLink = document.getElementById( 'ai-quality-force-refresh' );
        if ( forceRefreshLink ) {
            forceRefreshLink.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                const postId   = document.getElementById( 'post_ID' ) ? document.getElementById( 'post_ID' ).value : '';
                const seoTitle = document.getElementById( 'ai_seo_meta_title' ) ? document.getElementById( 'ai_seo_meta_title' ).value : '';
                const seoDesc  = document.getElementById( 'ai_seo_meta_description' ) ? document.getElementById( 'ai_seo_meta_description' ).value : '';

                let editorContent = '';
                if ( typeof wp !== 'undefined' && wp.data && wp.data.select( 'core/editor' ) ) {
                    editorContent = wp.data.select( 'core/editor' ).getEditedPostContent() || '';
                } else if ( document.getElementById( 'content' ) ) {
                    editorContent = document.getElementById( 'content' ).value;
                }

                aiQualityBtn.disabled          = true;
                aiQualitySpinner.style.display = 'inline';
                aiQualityResults.innerHTML     = '';

                aiSeoRequest( 'ai_seo_run_ai_quality', {
                    post_id:         postId,
                    seo_title:       seoTitle,
                    seo_description: seoDesc,
                    post_content:    editorContent,
                    force_refresh:   '1'
                } )
                    .then( function ( d ) {
                        aiQualityBtn.disabled          = false;
                        aiQualitySpinner.style.display = 'none';
                        renderAiQualityResults( d );
                    } )
                    .catch( function ( error ) {
                        aiQualityBtn.disabled          = false;
                        aiQualitySpinner.style.display = 'none';
                        aiQualityResults.textContent   = '';
                        aiQualityResults.appendChild( errorParagraph( error ) );
                    } );
            } );
        }
    }

    // --- Citability (GEO) analysis ---
    const citabilityBtn     = document.getElementById( 'ai-seo-run-citability' );
    const citabilitySpinner = document.getElementById( 'ai-seo-citability-spinner' );
    const citabilityResults = document.getElementById( 'ai-seo-citability-results' );

    function runCitability( forceRefresh ) {
        const postId = document.getElementById( 'post_ID' ) ? document.getElementById( 'post_ID' ).value : '';

        let editorContent = '';
        if ( typeof wp !== 'undefined' && wp.data && wp.data.select( 'core/editor' ) ) {
            editorContent = wp.data.select( 'core/editor' ).getEditedPostContent() || '';
        } else if ( document.getElementById( 'content' ) ) {
            editorContent = document.getElementById( 'content' ).value;
        }

        const params = {
            post_id:      postId,
            post_content: editorContent
        };
        if ( forceRefresh ) {
            params.force_refresh = '1';
        }

        citabilityBtn.disabled          = true;
        citabilitySpinner.style.display = 'inline';
        citabilityResults.innerHTML     = '';

        aiSeoRequest( 'ai_seo_citability', params )
            .then( function ( data ) {
                citabilityBtn.disabled          = false;
                citabilitySpinner.style.display = 'none';
                renderCitabilityResults( data );
            } )
            .catch( function ( error ) {
                citabilityBtn.disabled          = false;
                citabilitySpinner.style.display = 'none';
                citabilityResults.textContent   = '';
                citabilityResults.appendChild( errorParagraph( error ) );
            } );
    }

    if ( citabilityBtn ) {
        citabilityBtn.addEventListener( 'click', function () { runCitability( false ); } );
    }

    function renderCitabilityResults( data ) {
        if ( ! citabilityResults ) { return; }
        const checks = data.checks || [];
        const rating = data.rating || 'none';
        const cached = data.cached
            ? ' <em style="font-size:11px;color:#888;">(fra cache)</em>'
            : '';

        const ratingLabels = { good: 'God sitatbarhet', ok: 'Middels sitatbarhet', poor: 'Lav sitatbarhet' };
        const ratingText   = ratingLabels[ rating ] ? ' — ' + ratingLabels[ rating ] : '';

        let html = '<div class="ai-seo-readability-score ai-seo-score-' + rating + '">'
            + '<strong>Sitatbarhet: ' + ( data.score || 0 ) + '/100</strong>' + ratingText + cached
            + '</div>';
        html += '<ul class="ai-seo-checklist">';
        checks.forEach( function ( check ) {
            const icon = check.pass ? '✓' : '✗';
            html += '<li class="ai-seo-check-' + ( check.pass ? 'pass' : 'fail' ) + '" style="display:block;padding:6px 0;">'
                + '<div style="display:flex;align-items:center;">'
                +   '<span class="ai-seo-check-icon">' + icon + '</span> '
                +   '<span>' + check.label + '</span>'
                +   '<span class="ai-seo-check-detail">' + check.points + '/' + check.weight + '</span>'
                + '</div>'
                + ( check.feedback ? '<small style="display:block;color:#555;margin-left:26px;">' + check.feedback + '</small>' : '' )
                + '</li>';
        } );
        html += '</ul>';
        html += '<p style="margin-top:8px;"><a href="#" id="ai-seo-citability-refresh" style="font-size:12px;">Tving ny analyse (tøm cache)</a></p>';

        citabilityResults.innerHTML = html;

        const refreshLink = document.getElementById( 'ai-seo-citability-refresh' );
        if ( refreshLink ) {
            refreshLink.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                runCitability( true );
            } );
        }
    }

    });
})();
