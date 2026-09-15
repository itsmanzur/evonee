document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('eq-quote-modal');
    if (!modal) return;

    const overlay = modal.querySelector('.eq-modal-overlay');
    const closeBtn = modal.querySelector('.eq-modal-close');
    const form = document.getElementById('eq-quote-form');
    const alertBox = document.getElementById('eq-alert-box');
    const submitBtn = document.getElementById('eq-submit-btn');

    // Form inputs
    const productInput = document.getElementById('eq-product-input');
    const previewImg = document.getElementById('eq-preview-img');
    const previewTitle = document.getElementById('eq-preview-title');
    const previewDesc = document.getElementById('eq-preview-desc');

    const qtySelect = document.getElementById('eq-quantity');
    const otherQtyWrap = document.getElementById('eq-other-quantity-wrap');
    const otherQtyInput = document.getElementById('eq-other-quantity');

    const artworkInput = document.getElementById('eq-artwork');
    const dropzone = document.getElementById('eq-dropzone');
    const filePreview = document.getElementById('eq-file-preview');
    const noArtworkCheck = document.getElementById('eq-no-artwork');
    const designHelpNote = document.getElementById('eq-design-help-note');
    const openTimeInput = document.getElementById('eq-open-time');

    // --- 1. OPEN MODAL & PREFILL DATA ---
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.eq-trigger, .eq-open-modal');
        if (!trigger) return;

        e.preventDefault();

        const productName = trigger.getAttribute('data-product') || '';
        const productImage = trigger.getAttribute('data-image') || '';
        const productDesc = trigger.getAttribute('data-description') || '';

        openModal(productName, productImage, productDesc);
    });

    // --- LocalStorage Draft Auto-Save (Phase 4.3) & Progress Bar (Phase 4.4) ---
    const STORAGE_KEY = 'evonee_quote_draft_v2';
    const progressFill = document.getElementById('eq-progress-fill');

    function updateProgressBar() {
        if (!progressFill || !form) return;
        const requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let filledCount = 0;
        requiredInputs.forEach(input => {
            if (input.type === 'checkbox') {
                if (input.checked) filledCount++;
            } else if (input.value.trim() !== '') {
                filledCount++;
            }
        });
        const total = Math.max(requiredInputs.length, 1);
        const percent = Math.min(Math.round((filledCount / total) * 100), 100);
        progressFill.style.width = Math.max(percent, 15) + '%';
    }

    function saveDraft() {
        if (!form) return;
        const data = {};
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), select, textarea');
        inputs.forEach(input => {
            if (input.name) {
                if (input.type === 'checkbox') {
                    data[input.name] = input.checked;
                } else {
                    data[input.name] = input.value;
                }
            }
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        updateProgressBar();
    }

    function restoreDraft() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved || !form) return;
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = !!data[key];
                    } else if (field.value === '' || !field.hasAttribute('readonly')) {
                        field.value = data[key];
                    }
                }
            });
            updateProgressBar();
        } catch(e) {}
    }

    function clearDraft() {
        localStorage.removeItem(STORAGE_KEY);
        if (progressFill) progressFill.style.width = '15%';
    }

    // --- Live Price Estimator Calculation ---
    const estimatorBadge = document.getElementById('eq-live-price-estimator');
    const priceVal = document.getElementById('eq-estimated-price-val');

    function updatePriceEstimate() {
        if (!estimatorBadge || !priceVal) return;
        if (typeof eqQuoteData !== 'undefined' && eqQuoteData.enableEstimator === '0') {
            estimatorBadge.style.display = 'none';
            return;
        }

        let qty = 100;
        if (qtySelect && qtySelect.value) {
            if (qtySelect.value === 'other' || qtySelect.value === 'Other') {
                qty = parseInt(otherQtyInput.value.replace(/[^0-9]/g, '')) || 100;
            } else {
                qty = parseInt(qtySelect.value.replace(/[^0-9]/g, '')) || 100;
            }
        }

        const basePrice = (typeof eqQuoteData !== 'undefined' && eqQuoteData.basePrice) ? parseFloat(eqQuoteData.basePrice) : 50.00;
        const pricePerItem = (typeof eqQuoteData !== 'undefined' && eqQuoteData.pricePerItem) ? parseFloat(eqQuoteData.pricePerItem) : 1.25;

        const total = basePrice + (qty * pricePerItem);
        priceVal.textContent = '$' + total.toFixed(2);
        estimatorBadge.style.display = 'block';
    }

    if (qtySelect) {
        qtySelect.addEventListener('change', updatePriceEstimate);
    }
    if (otherQtyInput) {
        otherQtyInput.addEventListener('input', updatePriceEstimate);
    }

    if (form) {
        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);
    }

    function openModal(name, image, desc) {
        // Record opening timestamp
        if (openTimeInput) {
            openTimeInput.value = Math.floor(Date.now() / 1000);
        }

        // Reset form & errors
        clearErrors();
        if (alertBox) alertBox.style.display = 'none';

        // Populate product input & preview card
        if (name && name.trim() !== '') {
            productInput.value = name;
            productInput.setAttribute('readonly', 'readonly');
            previewTitle.textContent = name;
            previewDesc.textContent = desc || `High-quality custom ${name.toLowerCase()} for events, businesses, and organizations.`;
        } else {
            productInput.value = '';
            productInput.removeAttribute('readonly');
            productInput.placeholder = 'Enter product name (e.g. Wristband, Can Cooler)';
            previewTitle.textContent = 'Custom Product Request';
            previewDesc.textContent = 'Tell us what product you need and our team will prepare a custom quote.';
        }

        if (image && image.trim() !== '') {
            previewImg.src = image;
        } else {
            previewImg.src = (typeof eqQuoteData !== 'undefined' && eqQuoteData.placeholder) ? eqQuoteData.placeholder : '';
        }

        // Restore draft from LocalStorage
        restoreDraft();
        toggleWristbandFields();
        updatePriceEstimate();

        // Show Modal
        modal.classList.add('eq-modal--active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Focus first visible input
        setTimeout(() => {
            if (!name) {
                productInput.focus();
            } else {
                const nameField = document.getElementById('eq-full-name');
                if (nameField) nameField.focus();
            }
        }, 150);
    }

    // --- 2. CLOSE MODAL ---
    function closeModal() {
        modal.classList.remove('eq-modal--active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (overlay) overlay.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('eq-modal--active')) {
            closeModal();
        }
    });

    // --- 3. DYNAMIC FORM INTERACTIONS ---

    // Quantity select change -> toggle "Other Quantity" & Live Price Calculation
    const unitPriceEl = document.getElementById('eq-unit-price');
    const totalPriceEl = document.getElementById('eq-total-price');

    function formatCurrency(amount) {
        const data = (typeof eqQuoteData !== 'undefined') ? eqQuoteData : {};
        const symbol = data.currencySymbol || '$';
        const pos = data.currencyPos || 'left';
        const decimals = (typeof data.decimals !== 'undefined') ? parseInt(data.decimals) : 2;
        const decSep = data.decimalSep || '.';
        const thousandSep = data.thousandSep || ',';

        const parts = Number(amount).toFixed(decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        const formattedNum = parts.join(decSep);

        switch (pos) {
            case 'right':
                return formattedNum + symbol;
            case 'left_space':
                return symbol + ' ' + formattedNum;
            case 'right_space':
                return formattedNum + ' ' + symbol;
            case 'left':
            default:
                return symbol + formattedNum;
        }
    }

    function updateLiveEstimate() {
        if (!unitPriceEl || !totalPriceEl) return;

        let numQty = 100;
        if (qtySelect && qtySelect.value) {
            if (qtySelect.value === 'other' || qtySelect.value === 'Other') {
                numQty = parseInt(otherQtyInput.value) || 100;
            } else {
                numQty = parseInt(qtySelect.value) || 100;
            }
        }

        let unitPrice = 0.65;
        if (numQty < 50) unitPrice = 1.50;
        else if (numQty < 100) unitPrice = 0.95;
        else if (numQty < 250) unitPrice = 0.65;
        else if (numQty < 500) unitPrice = 0.48;
        else if (numQty < 1000) unitPrice = 0.35;
        else if (numQty < 2500) unitPrice = 0.28;
        else if (numQty < 5000) unitPrice = 0.22;
        else unitPrice = 0.18;

        const total = numQty * unitPrice;
        unitPriceEl.textContent = formatCurrency(unitPrice) + ' / pc';
        totalPriceEl.textContent = formatCurrency(total);
    }

    if (qtySelect) {
        qtySelect.addEventListener('change', function () {
            if (this.value === 'other') {
                otherQtyWrap.style.display = 'block';
                otherQtyInput.setAttribute('required', 'required');
                otherQtyInput.focus();
            } else {
                otherQtyWrap.style.display = 'none';
                otherQtyInput.removeAttribute('required');
                otherQtyInput.value = '';
            }
            updateLiveEstimate();
        });
    }

    if (otherQtyInput) {
        otherQtyInput.addEventListener('input', updateLiveEstimate);
    }

    updateLiveEstimate();

    // Country select change -> toggle "Other Country"
    const countrySelect = document.getElementById('eq-country');
    const otherCountryWrap = document.getElementById('eq-other-country-wrap');
    const otherCountryInput = document.getElementById('eq-other-country');

    if (countrySelect && otherCountryWrap && otherCountryInput) {
        countrySelect.addEventListener('change', function () {
            if (this.value === 'Other') {
                otherCountryWrap.style.display = 'block';
                otherCountryInput.setAttribute('required', 'required');
                otherCountryInput.focus();
            } else {
                otherCountryWrap.style.display = 'none';
                otherCountryInput.removeAttribute('required');
                otherCountryInput.value = '';
                clearFieldError('eq-other-country');
            }
        });
    }

    function isWristbandProduct(name) {
        return /wristband/i.test(name || '');
    }

    function toggleWristbandFields() {
        const wrap = document.getElementById('eq-wristband-details');
        const productName = productInput ? productInput.value : '';
        const show = isWristbandProduct(productName);
        if (wrap) {
            wrap.style.display = show ? '' : 'none';
        }
        ['eq-wristband-type', 'eq-size', 'eq-color'].forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (show) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
    }

    if (productInput) {
        productInput.addEventListener('input', toggleWristbandFields);
        productInput.addEventListener('change', toggleWristbandFields);
    }

    toggleWristbandFields();
    const colorSwatch = document.getElementById('eq-color-swatch');
    const colorInput = document.getElementById('eq-color');
    if (colorSwatch && colorInput) {
        colorSwatch.addEventListener('input', function () {
            colorInput.value = this.value;
        });
    }

    // "No Artwork" Checkbox toggle
    if (noArtworkCheck) {
        noArtworkCheck.addEventListener('change', function () {
            if (this.checked) {
                dropzone.style.opacity = '0.5';
                dropzone.style.pointerEvents = 'none';
                designHelpNote.style.display = 'block';
                clearFieldError('eq-artwork');
            } else {
                dropzone.style.opacity = '1';
                dropzone.style.pointerEvents = 'auto';
                designHelpNote.style.display = 'none';
            }
        });
    }

    // Drag and Drop Upload Zone
    if (dropzone && artworkInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('eq-dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('eq-dragover'), false);
        });

        dropzone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length) {
                assignArtworkFiles(files);
            }
        }

        artworkInput.addEventListener('change', function () {
            if (this.files.length) {
                assignArtworkFiles(this.files);
            }
        });
    }

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str).replace(/[&<>"']/g, function (s) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[s];
        });
    }

    function assignArtworkFiles(fileList) {
        const maxBytes = (typeof eqQuoteData !== 'undefined' && eqQuoteData.maxUploadSize) ? parseInt(eqQuoteData.maxUploadSize, 10) : (20 * 1024 * 1024);
        const maxFormatted = (typeof eqQuoteData !== 'undefined' && eqQuoteData.maxUploadSizeFormatted) ? eqQuoteData.maxUploadSizeFormatted : '20MB';
        const allowedExts = ['ai', 'pdf', 'eps', 'svg', 'png', 'jpg', 'jpeg'];
        const dt = new DataTransfer();
        const accepted = [];

        Array.from(fileList).slice(0, 3).forEach(function (file) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (file.size > maxBytes) {
                showFieldError('eq-artwork', 'File exceeds the maximum allowed limit of ' + maxFormatted + '.');
                return;
            }
            if (!allowedExts.includes(ext)) {
                showFieldError('eq-artwork', 'Invalid file type. Allowed formats: AI, PDF, EPS, SVG, PNG, JPG.');
                return;
            }
            accepted.push(file);
            dt.items.add(file);
        });

        artworkInput.files = dt.files;
        renderFilePreview(accepted);
    }

    function renderFilePreview(files) {
        clearFieldError('eq-artwork');
        if (!files.length) {
            filePreview.style.display = 'none';
            filePreview.innerHTML = '';
            return;
        }

        filePreview.innerHTML = files.map(function (file, i) {
            return `
            <div class="eq-file-info" data-index="${i}">
                <span><strong>${escapeHtml(file.name)}</strong> (${(file.size / (1024 * 1024)).toFixed(2)} MB)</span>
                <button type="button" class="eq-remove-file" data-index="${i}" title="Remove file">&times;</button>
            </div>`;
        }).join('');
        filePreview.style.display = 'block';

        filePreview.querySelectorAll('.eq-remove-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idx = parseInt(this.getAttribute('data-index'), 10);
                const dt = new DataTransfer();
                Array.from(artworkInput.files).forEach(function (f, i) {
                    if (i !== idx) dt.items.add(f);
                });
                artworkInput.files = dt.files;
                renderFilePreview(Array.from(dt.files));
            });
        });
    }

    // --- 4. FORM SUBMISSION VIA AJAX ---
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors();

            // Client Validation
            let isValid = true;

            const name = document.getElementById('eq-full-name');
            if (!name.value.trim()) {
                showFieldError('eq-full-name', 'Please enter your full name.');
                isValid = false;
            }

            const email = document.getElementById('eq-email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
                showFieldError('eq-email', 'Please enter a valid email address.');
                isValid = false;
            }

            const phone = document.getElementById('eq-phone');
            if (!phone.value.trim()) {
                showFieldError('eq-phone', 'Please enter your phone/WhatsApp number.');
                isValid = false;
            }

            const country = document.getElementById('eq-country');
            if (!country.value) {
                showFieldError('eq-country', 'Please select your country.');
                isValid = false;
            } else if (country.value === 'Other' && otherCountryInput && !otherCountryInput.value.trim()) {
                showFieldError('eq-other-country', 'Please specify your country name.');
                isValid = false;
            }

            const qty = document.getElementById('eq-quantity');
            if (!qty.value) {
                showFieldError('eq-quantity', 'Please select a quantity.');
                isValid = false;
            }

            const timeframe = document.getElementById('eq-timeframe');
            if (!timeframe.value) {
                showFieldError('eq-timeframe', 'Please select a delivery timeframe.');
                isValid = false;
            }

            const zip = document.getElementById('eq-zip-code');
            if (!zip.value.trim()) {
                showFieldError('eq-zip-code', 'Please enter your ZIP/postal code.');
                isValid = false;
            }

            const consent = document.getElementById('eq-consent');
            if (!consent.checked) {
                showFieldError('eq-consent', 'You must agree to allow Evonee to contact you.');
                isValid = false;
            }

            if (!isValid) {
                showAlert('error', 'Please fill in all required fields correctly before submitting.');
                return;
            }

            // Show Loading State
            setLoading(true);

            // Function to perform fetch submission
            const submitForm = (token = '') => {
                const formData = new FormData(form);
                formData.append('action', 'eq_submit_quote');
                if (token) {
                    formData.append('eq_recaptcha_token', token);
                }

                // Combine prefix + phone
                const prefixSelect = form.querySelector('.eq-phone-prefix');
                if (prefixSelect && phone) {
                    formData.set('phone', prefixSelect.value + ' ' + phone.value.trim());
                }

                fetch(eqQuoteData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    setLoading(false);
                    if (data.success) {
                        playSuccessChime();
                        showAlert('success', data.data.message || 'Quote request received! We will get back to you within 24 hours.');

                        // Clear only user-filled fields, not product info pre-filled from trigger
                        const fieldsToClear = ['eq-full-name', 'eq-company', 'eq-email', 'eq-phone',
                            'eq-country', 'eq-quantity', 'eq-other-quantity', 'eq-wristband-type',
                            'eq-size', 'eq-color', 'eq-has-pms-color', 'eq-debossed-text',
                            'eq-text-color', 'eq-timeframe', 'eq-specific-date', 'eq-zip-code', 'eq-project-notes'];
                        fieldsToClear.forEach(id => {
                            const el = document.getElementById(id);
                            if (el) { el.tagName === 'SELECT' ? el.selectedIndex = 0 : el.value = ''; }
                        });
                        const consentEl = document.getElementById('eq-consent');
                        if (consentEl) consentEl.checked = false;
                        if (filePreview) { filePreview.style.display = 'none'; filePreview.innerHTML = ''; }
                        if (otherQtyWrap) otherQtyWrap.style.display = 'none';
                        if (otherCountryWrap) otherCountryWrap.style.display = 'none';

                        clearDraft();

                        setTimeout(() => {
                            closeModal();
                        }, 4000);
                    } else {
                        if (data.data && data.data.errors) {
                            for (let field in data.data.errors) {
                                showFieldError('eq-' + field.replaceAll('_', '-'), data.data.errors[field]);
                            }
                        }
                        showAlert('error', (data.data && data.data.message) ? data.data.message : 'An error occurred. Please check the fields and try again.');
                    }
                })
                .catch(err => {
                    setLoading(false);
                    showAlert('error', 'Network error. Please check your connection and try again.');
                });
            };

            // Execute reCAPTCHA v3 if enabled
            if (window.grecaptcha && eqQuoteData.recaptchaSiteKey) {
                grecaptcha.ready(function() {
                    grecaptcha.execute(eqQuoteData.recaptchaSiteKey, { action: 'submit_quote' })
                        .then(function(token) {
                            submitForm(token);
                        })
                        .catch(function() {
                            submitForm('');
                        });
                });
            } else {
                submitForm('');
            }
        });
    }

    // --- HELPERS ---
    function playSuccessChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.2);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) {}
    }

    function setLoading(isLoading) {
        if (!submitBtn) return;
        if (isLoading) {
            submitBtn.disabled = true;
            submitBtn.classList.add('eq-loading');
            submitBtn.querySelector('span').textContent = 'Submitting Request...';
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('eq-loading');
            submitBtn.querySelector('span').textContent = 'Get Free Quote';
        }
    }

    function showAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = `eq-alert eq-alert-${type}`;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showFieldError(fieldId, text) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.classList.add('eq-invalid');
            const parent = input.closest('.eq-field') || input.closest('.eq-checkbox-wrap') || input.closest('.eq-dropzone');
            if (parent) {
                const errSpan = parent.querySelector('.eq-error-text');
                if (errSpan) errSpan.textContent = text;
            }
        } else if (fieldId === 'eq-artwork') {
            const errSpan = document.getElementById('eq-artwork-error');
            if (errSpan) errSpan.textContent = text;
        } else if (fieldId === 'eq-consent') {
            const errSpan = document.getElementById('eq-consent-error');
            if (errSpan) errSpan.textContent = text;
        }
    }

    function clearErrors() {
        document.querySelectorAll('.eq-invalid').forEach(el => el.classList.remove('eq-invalid'));
        document.querySelectorAll('.eq-error-text').forEach(el => el.textContent = '');
    }

    function clearFieldError(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.classList.remove('eq-invalid');
            const parent = input.closest('.eq-field') || input.closest('.eq-checkbox-wrap');
            if (parent) {
                const errSpan = parent.querySelector('.eq-error-text');
                if (errSpan) errSpan.textContent = '';
            }
        }
    }
});
