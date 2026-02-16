(function () {
    'use strict';

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

            var formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', aiSeo.nonce);

            for (var key in params) {
                if (params.hasOwnProperty(key)) {
                    formData.append(key, params[key]);
                }
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', aiSeo.ajaxUrl, true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;

                showSpinner(false);
                disableButtons(false);

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            onSuccess(response.data);
                        } else {
                            showError(response.data || 'En ukjent feil oppsto.');
                        }
                    } catch (e) {
                        showError('Kunne ikke tolke svaret fra serveren.');
                    }
                } else {
                    showError('Forespørselen feilet (HTTP ' + xhr.status + ').');
                }
            };

            xhr.onerror = function () {
                showSpinner(false);
                disableButtons(false);
                showError('Nettverksfeil – kunne ikke kontakte serveren.');
            };

            xhr.send(formData);
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

            var formData = new FormData();
            formData.append('action', 'ai_seo_refresh_score');
            formData.append('nonce', aiSeo.nonce);
            formData.append('post_id', postId);
            formData.append('seo_title', currentTitle ? currentTitle.value : '');
            formData.append('seo_description', currentDesc ? currentDesc.value : '');
            formData.append('seo_keyword', currentKw ? currentKw.value : '');
            formData.append('post_content', editorContent);

            btnRefreshScore.disabled = true;
            btnRefreshScore.textContent = 'Oppdaterer…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', aiSeo.ajaxUrl, true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;

                btnRefreshScore.disabled = false;
                btnRefreshScore.textContent = 'Oppdater analyse';

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            renderSeoScore(response.data);
                        }
                    } catch (e) {
                        // Silently fail.
                    }
                }
            };

            xhr.send(formData);
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

                var formData = new FormData();
                formData.append('action', 'ai_seo_readability_highlight');
                formData.append('nonce', aiSeo.nonce);
                formData.append('post_id', postId);
                formData.append('post_content', editorContent);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', aiSeo.ajaxUrl, true);

                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;

                    btnHighlight.disabled = false;
                    btnHighlight.textContent = 'Vis i teksten';

                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data && highlightContent && highlightPanel) {
                                highlightContent.innerHTML = response.data.html;
                                highlightPanel.style.display = 'block';
                                initHighlightNavigation();
                            }
                        } catch (e) {}
                    }
                };

                xhr.send(formData);
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

        function findAndScrollToText(container, searchText, doc) {
            var walker = doc.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
            var node;
            var needle = searchText.toLowerCase();

            while ((node = walker.nextNode())) {
                if (node.textContent.toLowerCase().indexOf(needle) !== -1) {
                    var el = node.parentElement;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    clearPreviousHighlight();
                    el.style.outline = '2px solid #2271b1';
                    el.style.outlineOffset = '2px';
                    el.style.borderRadius = '2px';
                    previousHighlightEl = el;
                    previousHighlightDoc = doc;

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

                var formData = new FormData();
                formData.append('action', 'ai_seo_run_migration');
                formData.append('nonce', aiSeo.nonce);
                formData.append('source', source);
                if (overwrite) {
                    formData.append('overwrite', '1');
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', aiSeo.ajaxUrl, true);

                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;

                    btn.disabled = false;
                    btn.textContent = source === 'yoast' ? 'Importer fra Yoast SEO' : 'Importer fra Rank Math';

                    if (xhr.status === 200 && resultBox) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data) {
                                resultBox.className = 'ai-seo-migration-result ai-seo-migration-success';
                                resultBox.textContent = 'Migrering fullf\u00f8rt! ' + response.data.migrated + ' innlegg oppdatert, ' + response.data.skipped + ' hoppet over.';
                            } else {
                                resultBox.className = 'ai-seo-migration-result ai-seo-migration-error';
                                resultBox.textContent = response.data || 'En feil oppsto under migreringen.';
                            }
                        } catch (e) {
                            resultBox.className = 'ai-seo-migration-result ai-seo-migration-error';
                            resultBox.textContent = 'Kunne ikke tolke svaret fra serveren.';
                        }
                    }
                };

                xhr.onerror = function () {
                    btn.disabled = false;
                    btn.textContent = source === 'yoast' ? 'Importer fra Yoast SEO' : 'Importer fra Rank Math';
                    if (resultBox) {
                        resultBox.className = 'ai-seo-migration-result ai-seo-migration-error';
                        resultBox.textContent = 'Nettverksfeil \u2013 kunne ikke kontakte serveren.';
                    }
                };

                xhr.send(formData);
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

                var formData = new FormData();
                formData.append('action', 'ai_seo_inline_save_meta');
                formData.append('nonce', aiSeo.nonce);
                formData.append('post_id', postId);
                formData.append('field', field);
                formData.append('value', value);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', aiSeo.ajaxUrl, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    wrapper.classList.remove('ai-seo-inline-saving');
                };
                xhr.send(formData);
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
    });
})();
