UPDATE infosystems AS s
SET field_schema = jsonb_set(
    jsonb_set(s.field_schema, ARRAY[(f.ordinality - 1)::text, 'type'], '"money"'::jsonb),
    ARRAY[(f.ordinality - 1)::text, 'name'], '"Стоимость, ₽"'::jsonb
)
FROM infosystems AS source
CROSS JOIN LATERAL jsonb_array_elements(source.field_schema) WITH ORDINALITY AS f(field, ordinality)
WHERE s.id = source.id AND s.code = 'mechaniki_prices'
  AND f.field->>'code' = 'amount_minor' AND f.field->>'type' = 'number'
  AND f.field->>'name' = 'Стоимость в копейках';
