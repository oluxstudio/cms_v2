/**
 * olux-forms.js
 *
 * Lightweight client-side validation + submission helper for Olux Studio forms.
 *
 * Usage (any HTML page or Vue template):
 *
 *   <script src="/js/olux-forms.js"></script>
 *
 *   const result = await OluxForms.submit('my-site', 'contact-us', {
 *     name:    'Jane Doe',
 *     email:   'jane@example.com',
 *     message: 'Hello!',
 *   });
 *
 *   if (!result.ok) {
 *     console.log(result.errors); // { email: 'Email Address must be a valid email.' }
 *   } else {
 *     console.log(result.message); // 'Form submitted successfully. Thank you!'
 *   }
 */

const OluxForms = (() => {

    // ─────────────────────────────────────────────────────────────────
    // Schema loader — cached per form to avoid repeat network calls
    // ─────────────────────────────────────────────────────────────────

    const _cache = {};

    async function loadSchema(siteName, formName) {
        const key = `${siteName}::${formName}`;
        if (_cache[key]) return _cache[key];

        const res = await fetch(`/api/sites/${siteName}/form/${formName}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (res.status === 404) throw new Error(`Form "${formName}" not found for site "${siteName}".`);
        if (res.status === 403) throw new Error(`Form "${formName}" is not currently accepting submissions.`);
        if (!res.ok)            throw new Error(`Failed to load form schema (HTTP ${res.status}).`);

        const schema = await res.json();
        _cache[key] = schema;
        return schema;
    }

    // ─────────────────────────────────────────────────────────────────
    // Client-side validator
    // Mirrors the server-side rules produced by Form::buildValidationRules()
    // ─────────────────────────────────────────────────────────────────

    function validate(schema, data) {
        const errors = {};

        for (const field of schema.fields ?? []) {
            const { key, label, type, required, min, max, options } = field;
            const raw   = data[key];
            const value = raw !== undefined && raw !== null ? String(raw).trim() : '';
            const empty = value === '';

            // ── required ──────────────────────────────────────────
            if (required && empty) {
                errors[key] = `${label} is required.`;
                continue;
            }

            // skip remaining checks if the field is optional and empty
            if (empty) continue;

            // ── type rules ────────────────────────────────────────
            if (type === 'email') {
                const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
                if (!emailRx.test(value)) {
                    errors[key] = `${label} must be a valid email address.`;
                    continue;
                }
            }

            if (type === 'url') {
                try { new URL(value); }
                catch (_) {
                    errors[key] = `${label} must be a valid URL (include https://).`;
                    continue;
                }
            }

            if (type === 'number') {
                if (isNaN(Number(value)) || value === '') {
                    errors[key] = `${label} must be a number.`;
                    continue;
                }
            }

            if (type === 'date') {
                if (isNaN(Date.parse(value))) {
                    errors[key] = `${label} must be a valid date.`;
                    continue;
                }
            }

            if (type === 'tel') {
                const phoneRx = /^[\+\d\s\-\(\)\.]{6,25}$/;
                if (!phoneRx.test(value)) {
                    errors[key] = `${label} must be a valid phone number.`;
                    continue;
                }
            }

            if (type === 'checkbox') {
                if (raw !== true && raw !== false && raw !== 'true' && raw !== 'false') {
                    errors[key] = `${label} must be checked or unchecked.`;
                    continue;
                }
            }

            // ── options (select / radio) ──────────────────────────
            if ((type === 'select' || type === 'radio') && options?.length) {
                if (!options.includes(value)) {
                    errors[key] = `${label} must be one of: ${options.join(', ')}.`;
                    continue;
                }
            }

            // ── min length / numeric min ──────────────────────────
            if (min !== null && min !== undefined && min !== '') {
                const minN = Number(min);
                const check = type === 'number' ? Number(value) < minN : value.length < minN;
                if (check) {
                    errors[key] = type === 'number'
                        ? `${label} must be at least ${min}.`
                        : `${label} must be at least ${min} characters.`;
                    continue;
                }
            }

            // ── max length / numeric max ──────────────────────────
            if (max !== null && max !== undefined && max !== '') {
                const maxN = Number(max);
                const check = type === 'number' ? Number(value) > maxN : value.length > maxN;
                if (check) {
                    errors[key] = type === 'number'
                        ? `${label} may not exceed ${max}.`
                        : `${label} may not exceed ${max} characters.`;
                    continue;
                }
            }
        }

        return errors; // {} means valid
    }

    // ─────────────────────────────────────────────────────────────────
    // Main submit helper
    // ─────────────────────────────────────────────────────────────────

    /**
     * Validate client-side, then POST to the API only if all rules pass.
     *
     * @param {string} siteName   - Site slug (e.g. 'my-website')
     * @param {string} formName   - Form slug (e.g. 'contact-us')
     * @param {object} data       - Key/value pairs matching the form's field keys
     *
     * @returns {Promise<{ok: boolean, errors?: object, message?: string}>}
     *   ok:true  + message  on success
     *   ok:false + errors   on validation or server failure
     *     errors is keyed by field key; '_form' holds non-field errors
     */
    async function submit(siteName, formName, data) {
        // ── 1. Fetch schema (cached after first call) ─────────────
        let schema;
        try {
            schema = await loadSchema(siteName, formName);
        } catch (err) {
            return { ok: false, errors: { _form: err.message } };
        }

        // ── 2. Client-side validation ─────────────────────────────
        const errors = validate(schema, data);
        if (Object.keys(errors).length > 0) {
            return { ok: false, errors };
        }

        // ── 3. POST to API ────────────────────────────────────────
        let res, body;
        try {
            res  = await fetch(`/api/sites/${siteName}/form/${formName}`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:    JSON.stringify(data),
            });
            body = await res.json();
        } catch (err) {
            return { ok: false, errors: { _form: 'Network error. Please try again.' } };
        }

        // ── 4. Handle server response ─────────────────────────────
        if (!res.ok) {
            // 422 from server → field-level errors; anything else → form-level
            return {
                ok:     false,
                errors: body.errors ?? { _form: body.message ?? 'Submission failed.' },
            };
        }

        return { ok: true, message: body.message };
    }

    // ─────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────

    return { submit, validate, loadSchema };

})();
