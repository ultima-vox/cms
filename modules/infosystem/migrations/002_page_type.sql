UPDATE nodes n
SET page_type = 'infosystem.list',
    updated_at = CURRENT_TIMESTAMP
FROM node_module_bindings b
WHERE b.node_id = n.id
  AND b.module_code = 'infosystem'
  AND b.binding_code = 'primary'
  AND n.page_type = 'core.content';
