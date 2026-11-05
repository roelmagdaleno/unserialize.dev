// Grammars are split out so a page only downloads the languages it renders.
const languageLoaders = {
    bash: () => import('shiki/langs/bash.mjs'),
    json: () => import('shiki/langs/json.mjs'),
    php: () => import('shiki/langs/php.mjs'),
};

const highlightableBlocks = Object.keys(languageLoaders)
    .map((language) => `pre[data-lang="${language}"]:not([data-highlight="off"])`)
    .join(',');

let highlighterModule = null;

// Shiki stays out of the initial load: its code and grammars are downloaded
// the first time a block with a known language nears the viewport.
const blockObserver = new IntersectionObserver(async (entries) => {
    const visibleBlocks = entries.filter((entry) => entry.isIntersecting).map((entry) => entry.target);

    if (visibleBlocks.length === 0) {
        return;
    }

    visibleBlocks.forEach((block) => blockObserver.unobserve(block));

    highlighterModule ??= import('./highlighter.js');

    const { highlightElement } = await highlighterModule;

    visibleBlocks.forEach((block) => highlightElement(block, languageLoaders[block.dataset.lang]));
}, { rootMargin: '200px' });

function observeBlocks() {
    document.querySelectorAll(highlightableBlocks).forEach((block) => blockObserver.observe(block));
}

observeBlocks();

// Livewire morphs the highlighted markup back to plain text whenever the
// component re-renders, so the blocks are observed again afterwards.
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', observeBlocks);
});
