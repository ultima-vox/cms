(() => {
    'use strict';

    void import('/assets/admin/js/app.js').catch((error) => {
        console.error('Ultima Vox Admin initialization failed.', error);
    });
})();
