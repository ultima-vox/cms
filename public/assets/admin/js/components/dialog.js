export function initDialogs() {
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const opener = event.target.closest('[data-dialog-open]');
        if (opener instanceof HTMLElement) {
            const dialogId = opener.dataset.dialogOpen;
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (dialog instanceof HTMLDialogElement && !dialog.open) {
                dialog.showModal();
            }
            return;
        }

        const closer = event.target.closest('[data-dialog-close]');
        if (!(closer instanceof HTMLElement)) {
            return;
        }

        const dialog = closer.closest('dialog');
        if (dialog instanceof HTMLDialogElement) {
            dialog.close(closer.dataset.dialogClose ?? '');
        }
    });

    document.querySelectorAll('dialog[data-dialog-backdrop-close]').forEach((dialog) => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
}
