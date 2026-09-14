import { createHighlighterCore } from 'shiki/core';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';
import catppuccinLatte from 'shiki/themes/catppuccin-latte.mjs';
import catppuccinMocha from 'shiki/themes/catppuccin-mocha.mjs';

// Both themes are emitted at once as CSS variables. `resources/css/code.css`
// decides which set applies, so the toggle never re-runs the highlighter.
const themes = {
    light: 'catppuccin-latte',
    dark: 'catppuccin-mocha',
};

// Grammars are split out so a page only downloads the languages it renders.
const languageLoaders = {
    json: () => import('shiki/langs/json.mjs'),
    php: () => import('shiki/langs/php.mjs'),
};

const loadedLanguages = {};

let highlighterPromise = null;

function getHighlighter() {
    highlighterPromise ??= createHighlighterCore({
        themes: [catppuccinLatte, catppuccinMocha],
        langs: [],
        engine: createJavaScriptRegexEngine(),
    });

    return highlighterPromise;
}

async function highlightElement(element) {
    const language = element.dataset.lang;
    const loadLanguage = languageLoaders[language];

    if (! loadLanguage) {
        return;
    }

    const code = element.textContent;

    if (code.trim() === '') {
        return;
    }

    const highlighter = await getHighlighter();

    loadedLanguages[language] ??= loadLanguage().then(
        (module) => highlighter.loadLanguage(module.default),
    );

    await loadedLanguages[language];

    // Inline markup uses <br> instead of newlines, so the raw source is kept
    // for the clipboard button to copy verbatim.
    element.dataset.code = code;

    // The element keeps its own id, classes, and clipboard target so only the
    // token markup inside it is swapped.
    element.innerHTML = highlighter.codeToHtml(code, {
        lang: language,
        themes,
        defaultColor: false,
        structure: 'inline',
    });
}

function highlightAll() {
    document.querySelectorAll('pre[data-lang]').forEach(highlightElement);
}

highlightAll();

// Livewire morphs the highlighted markup back to plain text whenever the
// component re-renders, so the blocks are highlighted again afterwards.
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', highlightAll);
});
