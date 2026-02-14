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
        var btnGenDesc     = document.getElementById('ai-seo-generate-desc');
        var btnSuggestTitle = document.getElementById('ai-seo-suggest-title');
        var btnAnalyze     = document.getElementById('ai-seo-analyze-keywords');
        var spinner        = document.getElementById('ai-seo-spinner');
        var resultBox      = document.getElementById('ai-seo-result');
        var errorBox       = document.getElementById('ai-seo-error');

        if (btnGenDesc) {
            btnGenDesc.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_generate_description', { post_id: postId }, function (data) {
                    if (descInput) {
                        descInput.value = data.text;
                        descInput.dispatchEvent(new Event('input'));
                    }
                    showResult('Metabeskrivelse generert: ' + data.text);
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

        if (btnAnalyze) {
            btnAnalyze.addEventListener('click', function () {
                var postId = this.dataset.postId;
                doAjax('ai_seo_analyze_keywords', { post_id: postId }, function (data) {
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
            [btnGenDesc, btnSuggestTitle, btnAnalyze].forEach(function (btn) {
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

            // Clear placeholder hash when user starts typing a new key.
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

                // Select first visible option if current selection is hidden.
                var currentOption = modelSelect.options[modelSelect.selectedIndex];
                if (currentOption && currentOption.style.display === 'none' && firstVisible) {
                    firstVisible.selected = true;
                }
            }

            providerSelect.addEventListener('change', filterModels);
            filterModels();
        }
    });
})();
