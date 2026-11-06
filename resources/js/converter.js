const form = document.querySelector('[data-converter]');
const field = form.querySelector('textarea');
const submitButton = form.querySelector('button[type="submit"]');
const errorPanel = document.getElementById('serialized-data-error');
const errorMessage = errorPanel.querySelector('[data-error-message]');
const diagnosticPanel = document.getElementById('conversion-diagnostic');
const resultPanel = document.getElementById('conversion-result');

const unavailableMessage = 'The conversion could not be completed. Please try again.';

function showError(message) {
    errorMessage.textContent = message ?? '';
    errorPanel.classList.toggle('hidden', !message);
    field.toggleAttribute('data-invalid', Boolean(message));

    if (message) {
        field.setAttribute('aria-invalid', 'true');
    } else {
        field.removeAttribute('aria-invalid');
    }
}

// A panel only changes when the response says so: a `null` result means this
// attempt never reached a conversion, so the last JSON stays on the page.
function replacePanel(panel, html) {
    if (html === null) {
        return;
    }

    panel.innerHTML = html;
    panel.dispatchEvent(new CustomEvent('conversion:rendered', { bubbles: true }));
}

function setBusy(isBusy) {
    submitButton.disabled = isBusy;
    field.readOnly = isBusy;
}

form.addEventListener('submit', async () => {
    setBusy(true);

    try {
        // The value travels as the raw body so no input middleware rewrites it.
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'text/plain;charset=UTF-8', Accept: 'application/json' },
            body: field.value,
        });

        if (!response.ok) {
            throw new Error(`Unexpected status ${response.status}`);
        }

        const conversion = await response.json();

        field.value = conversion.serializedData;
        showError(conversion.error);
        replacePanel(diagnosticPanel, conversion.diagnostic);
        replacePanel(resultPanel, conversion.result);
    } catch {
        showError(unavailableMessage);
    } finally {
        setBusy(false);
    }
});
