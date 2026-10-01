export class HttpError extends Error {
    constructor(response, body = null) {
        super(`HTTP ${response.status} ${response.statusText}`.trim());
        this.name = 'HttpError';
        this.status = response.status;
        this.statusText = response.statusText;
        this.url = response.url;
        this.body = body;
    }
}

async function parseResponse(response, responseType) {
    if (response.status === 204 || response.status === 205) {
        return null;
    }

    if (responseType === 'text') {
        return response.text();
    }

    if (responseType === 'json') {
        return response.json();
    }

    const contentType = response.headers.get('content-type') ?? '';
    if (contentType.includes('application/json') || contentType.includes('+json')) {
        return response.json();
    }

    return response.text();
}

export async function request(url, options = {}) {
    const {
        method = 'GET',
        headers = {},
        body = null,
        signal = null,
        timeout = 15000,
        responseType = 'auto',
        credentials = 'same-origin',
    } = options;

    const controller = new AbortController();
    let timeoutId = null;

    if (signal instanceof AbortSignal) {
        if (signal.aborted) {
            controller.abort(signal.reason);
        } else {
            signal.addEventListener('abort', () => controller.abort(signal.reason), { once: true });
        }
    }

    if (Number.isFinite(timeout) && timeout > 0) {
        timeoutId = window.setTimeout(() => controller.abort(new DOMException('Request timeout', 'TimeoutError')), timeout);
    }

    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json, text/html;q=0.9, text/plain;q=0.8, */*;q=0.1',
                ...headers,
            },
            body,
            credentials,
            signal: controller.signal,
        });

        const parsed = await parseResponse(response, responseType);
        if (!response.ok) {
            throw new HttpError(response, parsed);
        }

        return {
            response,
            data: parsed,
        };
    } finally {
        if (timeoutId !== null) {
            window.clearTimeout(timeoutId);
        }
    }
}
