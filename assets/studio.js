/* ProcessWire Studio - Admin JS (UIkit) */
document.addEventListener('DOMContentLoaded', function () {
    console.log('[ProcessWire Studio] Script loaded');
    
    // Configure Prism.js autoloader for PHP
    if (window.Prism && window.Prism.plugins && window.Prism.plugins.autoloader) {
        window.Prism.plugins.autoloader.languages_path = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/';
    }

    function notify(message, status) {
        // status: 'primary' | 'success' | 'warning' | 'danger'
        if (window.UIkit && typeof window.UIkit.notification === 'function') {
            window.UIkit.notification({
                message: message,
                status: status || 'primary',
                pos: 'top-right',
                timeout: 3500
            });
        } else {
            // Fallback if UIkit isn't available for some reason
            console.log('[ProcessWire Studio]', message);
        }
    }

    // Code Generator functionality (only if form exists)
    const form = document.getElementById('codegen-form');
    const generateBtn = document.getElementById('generate-code-btn');
    const copyBtn = document.getElementById('copy-code-btn');
    const codeOutput = document.getElementById('code-output');
    const generatedCode = document.getElementById('generated-code');

    if (form && generateBtn) {
        // Code Generator is available, set it up
        function setGeneratingState(isGenerating) {
            if (!generateBtn) return;

            generateBtn.disabled = !!isGenerating;

            if (isGenerating) {
                generateBtn.dataset.originalText = generateBtn.innerHTML;
                generateBtn.innerHTML = "<span uk-spinner='ratio: 0.7'></span> Generating...";
            } else {
                if (generateBtn.dataset.originalText) {
                    generateBtn.innerHTML = generateBtn.dataset.originalText;
                } else {
                    generateBtn.innerHTML = "<span uk-icon='icon: code'></span> Generate Code";
                }
            }
        }

    function getSelectedFields() {
        const selected = [];
        form.querySelectorAll('input[name="fields[]"]:checked').forEach(function (cb) {
            selected.push(cb.value);
        });
        return selected;
    }

    function showCode(code) {
        if (!generatedCode || !codeOutput) return;

        generatedCode.textContent = code || '';
        codeOutput.style.display = 'block';

        // Highlight code with Prism.js
        if (window.Prism) {
            Prism.highlightElement(generatedCode);
        }

        // Scroll into view
        try {
            codeOutput.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            // ignore
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault(); // Prevent normal form submission

        const selectedFields = getSelectedFields();

        if (!selectedFields.length) {
            notify('Please select at least one field.', 'warning');
            return;
        }

        setGeneratingState(true);

        // Send form data (includes tpl + fields[] + CSRF token)
        const formData = new FormData(form);

        const xhr = new XMLHttpRequest();

        // Use the module page itself, with action=generate (same as in PHP router)
        const url = window.location.pathname + '?action=generate';

        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            setGeneratingState(false);

            if (xhr.status !== 200) {
                notify('Error generating code (HTTP ' + xhr.status + ').', 'danger');
                return;
            }

            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                notify('Invalid response from server.', 'danger');
                return;
            }

            if (!response || !response.success) {
                const msg = (response && response.error) ? response.error : 'Error generating code.';
                notify(msg, 'danger');
                return;
            }

            showCode(response.code);
            notify('Code generated.', 'success');

            // Ensure UIkit icons are applied to any new markup (if needed)
            if (window.UIkit && window.UIkit.update) {
                try { window.UIkit.update(); } catch (e) {}
            }
        };

        xhr.onerror = function () {
            setGeneratingState(false);
            notify('Network error. Please try again.', 'danger');
        };

        xhr.send(formData);
    });

    // Copy to clipboard (UIkit notification feedback)
    if (copyBtn && generatedCode) {
        copyBtn.addEventListener('click', function () {
            const code = generatedCode.textContent || '';

            if (!code.trim()) {
                notify('There is no code to copy yet.', 'warning');
                return;
            }

            // Prefer Clipboard API
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(code).then(function () {
                    notify('Copied to clipboard.', 'success');
                }).catch(function () {
                    notify('Could not copy automatically. Please select and copy manually.', 'warning');
                });
                return;
            }

            // Fallback: temporary textarea
            try {
                const ta = document.createElement('textarea');
                ta.value = code;
                ta.setAttribute('readonly', 'readonly');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);

                if (ok) notify('Copied to clipboard.', 'success');
                else notify('Could not copy automatically. Please select and copy manually.', 'warning');
            } catch (e) {
                notify('Could not copy automatically. Please select and copy manually.', 'warning');
            }
        });
    } // End of Code Generator block
    }

    // Minify functionality (always available)
    const minifyButtons = document.querySelectorAll('.pw-minify-btn');
    console.log('[ProcessWire Studio] Found minify buttons:', minifyButtons.length);
    
    if (minifyButtons.length) {
        const csrfName = document.getElementById('pw-minify-csrf-name');
        const csrfValue = document.getElementById('pw-minify-csrf-value');
        
        console.log('[ProcessWire Studio] CSRF elements:', { csrfName: !!csrfName, csrfValue: !!csrfValue });
        
        if (!csrfName || !csrfValue) {
            console.error('[ProcessWire Studio] CSRF token elements not found!');
            notify('CSRF token elements not found. Please reload the page.', 'danger');
        } else {
            minifyButtons.forEach(function(btn, index) {
                console.log('[ProcessWire Studio] Setting up button', index, { type: btn.dataset.type, file: btn.dataset.file });
                
                btn.addEventListener('click', function() {
                    console.log('[ProcessWire Studio] Minify button clicked:', { type: this.dataset.type, file: this.dataset.file });
                    
                    const type = this.dataset.type;
                    const file = this.dataset.file;
                    
                    if (!type || !file) {
                        notify('Invalid minify request.', 'danger');
                        return;
                    }
                    
                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span uk-spinner="ratio: 0.7"></span> ' + (type === 'css' ? 'Minifying CSS...' : 'Minifying JS...');
                    
                    const formData = new FormData();
                    formData.append('type', type);
                    formData.append('file', file);
                    
                    const tokenName = csrfName.value;
                    const tokenValue = csrfValue.value;
                    console.log('[ProcessWire Studio] CSRF token:', { name: tokenName, hasValue: !!tokenValue });
                    
                    if (tokenName && tokenValue) {
                        formData.append(tokenName, tokenValue);
                    } else {
                        console.error('[ProcessWire Studio] CSRF token values are empty!');
                        notify('CSRF token is missing. Please reload the page.', 'danger');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        return;
                    }
                    
                    const xhr = new XMLHttpRequest();
                    const url = window.location.pathname + '?action=minify';
                    console.log('[ProcessWire Studio] Sending AJAX request to:', url);
                    
                    xhr.open('POST', url, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    xhr.onload = function() {
                        console.log('[ProcessWire Studio] AJAX response:', { status: xhr.status, response: xhr.responseText.substring(0, 200) });
                        
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        
                        if (xhr.status !== 200) {
                            notify('Error minifying file (HTTP ' + xhr.status + ').', 'danger');
                            return;
                        }
                        
                        let response;
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (e) {
                            console.error('[ProcessWire Studio] JSON parse error:', e, xhr.responseText);
                            notify('Invalid response from server.', 'danger');
                            return;
                        }
                        
                        if (!response || !response.success) {
                            const msg = (response && response.message) ? response.message : (response && response.error) ? response.error : 'Error minifying file.';
                            console.error('[ProcessWire Studio] Minify error:', response);
                            notify(msg, 'danger');
                            return;
                        }
                        
                        notify(response.message || 'File minified successfully.', 'success');
                        
                        // Reload page after short delay to show updated status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    };
                    
                    xhr.onerror = function() {
                        console.error('[ProcessWire Studio] AJAX network error');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        notify('Network error. Please try again.', 'danger');
                    };
                    
                    xhr.send(formData);
                });
            });
        }
    } else {
        console.log('[ProcessWire Studio] No minify buttons found on page');
    }

    // Optional: log once
    console.log('ProcessWire Studio loaded');
});
