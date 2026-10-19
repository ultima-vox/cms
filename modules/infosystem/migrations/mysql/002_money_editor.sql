-- cms:statement
UPDATE infosystems
SET field_schema = JSON_SET(field_schema,
    REPLACE(JSON_UNQUOTE(JSON_SEARCH(field_schema, 'one', 'amount_minor', NULL, '$[*].code')), '.code', '.type'), 'money',
    REPLACE(JSON_UNQUOTE(JSON_SEARCH(field_schema, 'one', 'amount_minor', NULL, '$[*].code')), '.code', '.name'), 'Стоимость, ₽')
WHERE code = 'mechaniki_prices'
  AND JSON_UNQUOTE(JSON_EXTRACT(field_schema, REPLACE(JSON_UNQUOTE(JSON_SEARCH(field_schema, 'one', 'amount_minor', NULL, '$[*].code')), '.code', '.type'))) = 'number'
  AND JSON_UNQUOTE(JSON_EXTRACT(field_schema, REPLACE(JSON_UNQUOTE(JSON_SEARCH(field_schema, 'one', 'amount_minor', NULL, '$[*].code')), '.code', '.name'))) = 'Стоимость в копейках';
