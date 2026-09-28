(() => {
    const list = document.getElementById('structure-list');
    if (!list) return;

    const csrf = list.dataset.csrf || '';
    let dragged = null;

    const rows = () => Array.from(list.querySelectorAll('.structure-row'));

    for (const row of rows()) {
        row.addEventListener('dragstart', () => {
            dragged = row;
            row.classList.add('structure-row--dragging');
        });

        row.addEventListener('dragend', () => {
            row.classList.remove('structure-row--dragging');
            dragged = null;
        });

        row.addEventListener('dragover', (event) => event.preventDefault());

        row.addEventListener('drop', async (event) => {
            event.preventDefault();
            if (!dragged || dragged === row) return;

            const targetParent = row.dataset.parent || '';
            const sourceParent = dragged.dataset.parent || '';

            if (sourceParent !== targetParent) {
                window.alert('Перетаскивание меняет только порядок соседних узлов. Для переноса в другую ветку измените поле «Родитель».');
                return;
            }

            const siblingRows = rows().filter((item) => item.dataset.parent === targetParent && item !== dragged);
            const targetIndex = siblingRows.indexOf(row);
            if (targetIndex < 0) return;

            siblingRows.splice(targetIndex, 0, dragged);

            const items = siblingRows.map((item, index) => ({
                id: Number(item.dataset.id),
                parent_id: targetParent === '' ? null : Number(targetParent),
                sorting: index * 10,
            }));

            const body = new URLSearchParams();
            body.set('_csrf', csrf);
            body.set('items', JSON.stringify(items));

            const response = await fetch('/admin/structure/reorder', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: body.toString(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const result = await response.json().catch(() => null);
                window.alert(result?.error || 'Не удалось изменить порядок узлов.');
                return;
            }

            window.location.reload();
        });
    }
})();
