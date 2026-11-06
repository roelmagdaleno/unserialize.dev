import hljs from 'highlight.js/lib/core';

const registeredLanguages = {};

export async function highlightElement(element, loadLanguage) {
    const language = element.dataset.lang;
    const code = element.textContent;

    if (code.trim() === '') {
        return;
    }

    registeredLanguages[language] ??= loadLanguage().then(
        (module) => hljs.registerLanguage(language, module.default),
    );

    await registeredLanguages[language];

    // The input is untrusted: `ignoreIllegals` keeps a malformed value
    // highlighted instead of dropping the whole block to plain text.
    element.innerHTML = hljs.highlight(code, { language, ignoreIllegals: true }).value;
}
