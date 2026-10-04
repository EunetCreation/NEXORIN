(() => {
    'use strict';

    const form = document.querySelector('[data-login-form]');
    const submitButton = document.querySelector('[data-submit-button]');
    const submitLabel = document.querySelector('[data-submit-label]');
    const emailInput = document.querySelector('#email');
    const passwordInput = document.querySelector('#password');
    const passwordToggle = document.querySelector('[data-password-toggle]');

    const setFieldState = (input, message = '') => {
        if (!input) return;

        const shell = input.closest('.field-shell');
        const messageNode = document.querySelector(`[data-field-message="${input.name}"]`);
        const invalid = message.length > 0;

        shell?.classList.toggle('is-invalid', invalid);
        input.setAttribute('aria-invalid', String(invalid));

        if (messageNode) {
            messageNode.textContent = message;
            messageNode.classList.toggle('is-visible', invalid);
        }
    };

    const validateEmail = () => {
        if (!emailInput) return true;
        const value = emailInput.value.trim();

        if (!value) {
            setFieldState(emailInput, 'Enter your email address.');
            return false;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            setFieldState(emailInput, 'Enter a valid email address.');
            return false;
        }

        setFieldState(emailInput);
        return true;
    };

    const validatePassword = () => {
        if (!passwordInput) return true;

        if (!passwordInput.value) {
            setFieldState(passwordInput, 'Enter your password.');
            return false;
        }

        setFieldState(passwordInput);
        return true;
    };

    if (emailInput) {
        emailInput.addEventListener('blur', validateEmail);
        emailInput.addEventListener('input', () => {
            if (emailInput.getAttribute('aria-invalid') === 'true') validateEmail();
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('blur', validatePassword);
        passwordInput.addEventListener('input', () => {
            if (passwordInput.getAttribute('aria-invalid') === 'true') validatePassword();
        });
    }

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', () => {
            const reveal = passwordInput.type === 'password';
            passwordInput.type = reveal ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(reveal));
            passwordToggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            passwordToggle.classList.toggle('is-revealed', reveal);
            passwordInput.focus({ preventScroll: true });
        });
    }

    if (form && submitButton) {
        form.addEventListener('submit', (event) => {
            const emailValid = validateEmail();
            const passwordValid = validatePassword();

            if (!emailValid || !passwordValid) {
                event.preventDefault();
                (emailValid ? passwordInput : emailInput)?.focus({ preventScroll: true });
                return;
            }

            document.body.classList.add('is-authenticating');
            submitButton.classList.add('is-loading');
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;

            if (submitLabel) {
                submitLabel.textContent = 'Signing in';
            }

            try {
                sessionStorage.setItem('nexorin-auth-transition', String(Date.now()));
            } catch (error) {
                // Storage can be unavailable in hardened browsers.
            }
        });
    }

    window.addEventListener('pageshow', () => {
        if (!submitButton) return;
        document.body.classList.remove('is-authenticating');
        submitButton.classList.remove('is-loading');
        submitButton.removeAttribute('aria-busy');
        submitButton.disabled = false;
        if (submitLabel) submitLabel.textContent = 'Sign In';
    });

    document.addEventListener('visibilitychange', () => {
        document.body.classList.toggle('motion-paused', document.hidden);
    });
})();
