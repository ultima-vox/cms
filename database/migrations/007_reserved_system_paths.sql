ALTER TABLE nodes
    ADD CONSTRAINT nodes_path_not_reserved_system_prefix
    CHECK (
        path = '/'
        OR NOT (
            path = '/admin' OR path LIKE '/admin/%'
            OR path = '/api' OR path LIKE '/api/%'
            OR path = '/health' OR path LIKE '/health/%'
            OR path = '/assets' OR path LIKE '/assets/%'
            OR path = '/media' OR path LIKE '/media/%'
            OR path = '/storage' OR path LIKE '/storage/%'
        )
    );
