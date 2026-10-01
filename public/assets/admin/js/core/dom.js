export function query(selector, root = document) {
    return root.querySelector(selector);
}

export function queryAll(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
}

export function delegate(root, eventName, selector, handler, options = undefined) {
    root.addEventListener(eventName, (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const matched = event.target.closest(selector);
        if (!matched || !root.contains(matched)) {
            return;
        }

        handler(event, matched);
    }, options);
}

export function setBusy(element, busy) {
    element.classList.toggle('is-busy', busy);

    if (busy) {
        element.setAttribute('aria-busy', 'true');
        return;
    }

    element.removeAttribute('aria-busy');
}
