'use strict';
const driver = document.getElementById('database-driver');
const port = document.querySelector('input[name="port"]');
if (driver && port) {
    let previous = driver.value;
    driver.addEventListener('change', () => {
        const defaultPort = previous === 'mysql' ? '3306' : '5432';
        if (port.value === defaultPort || port.value === '') {
            port.value = driver.value === 'mysql' ? '3306' : '5432';
        }
        previous = driver.value;
    });
}
