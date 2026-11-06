// Flux's storage key is kept so visitors who already chose a theme keep it. The
// `system` choice stores nothing and follows the operating system.
const storageKey = 'flux.appearance';
const systemPreference = matchMedia('(prefers-color-scheme: dark)');
const menu = document.querySelector('[data-theme-menu]');
const items = [...menu.querySelectorAll('[role="menuitem"]')];

function applyStoredAppearance() {
    const appearance = localStorage.getItem(storageKey);
    const isDark = appearance === 'dark' || (appearance !== 'light' && systemPreference.matches);

    document.documentElement.classList.toggle('dark', isDark);
}

systemPreference.addEventListener('change', applyStoredAppearance);

menu.addEventListener('click', (event) => {
    const choice = event.target.closest('[data-theme-choice]')?.dataset.themeChoice;

    if (!choice) {
        return;
    }

    if (choice === 'system') {
        localStorage.removeItem(storageKey);
    } else {
        localStorage.setItem(storageKey, choice);
    }

    applyStoredAppearance();
    menu.hidePopover();
});

menu.addEventListener('toggle', (event) => {
    if (event.newState === 'open') {
        items[0].focus();
    }
});

menu.addEventListener('keydown', (event) => {
    const position = items.indexOf(document.activeElement);
    const targets = {
        ArrowDown: items[(position + 1) % items.length],
        ArrowUp: items[(position - 1 + items.length) % items.length],
        Home: items[0],
        End: items[items.length - 1],
    };

    if (targets[event.key]) {
        event.preventDefault();
        targets[event.key].focus();
    }
});
