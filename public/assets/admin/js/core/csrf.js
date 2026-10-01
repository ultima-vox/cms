const DEFAULT_FIELD = '_csrf';

export function csrfToken(root = document) {
    const meta = root.querySelector?.('meta[name="csrf-token"]');
    if (meta instanceof HTMLMetaElement && meta.content !== '') {
        return meta.content;
    }

    const input = root.querySelector?.(`input[name="${DEFAULT_FIELD}"]`);
    if (input instanceof HTMLInputElement && input.value !== '') {
        return input.value;
    }

    return null;
}

export function appendCsrf(formData, root = document, fieldName = DEFAULT_FIELD) {
    if (formData.has(fieldName)) {
        return formData;
    }

    const token = csrfToken(root);
    if (token !== null) {
        formData.append(fieldName, token);
    }

    return formData;
}
