import ClipboardJS from 'clipboard/dist/clipboard';

// These icons must be inline to avoid rendering bugs.
const clipboardIcon = `<svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>`;
const clipboardCopiedIcon = `<svg fill="currentColor" class="fill-current h-5 w-5" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>`;

let clipboard = new ClipboardJS('button[data-clipboard-target]', {
    text: (trigger) => {
        const target = document.querySelector(trigger.dataset.clipboardTarget);

        // Highlighted blocks keep their unformatted source in `data-code`.
        return target.dataset.code ?? target.textContent;
    },
});

clipboard.on('success', (element) => {
    element.trigger.innerHTML = `${clipboardCopiedIcon}`;
    element.clearSelection();
    setTimeout(() => element.trigger.innerHTML = `${clipboardIcon}`, 1500);

    // Only the fact that a copy succeeded is reported. The copied text stays in
    // the browser: a button that opts in names its event, and nothing else about
    // the copy is sent.
    const copyEvent = element.trigger.dataset.clipboardEvent;

    if (copyEvent && window.Livewire) {
        window.Livewire.dispatch(copyEvent);
    }
});
