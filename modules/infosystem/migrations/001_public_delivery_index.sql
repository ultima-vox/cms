CREATE INDEX idx_infosystem_items_public_delivery
    ON infosystem_items (infosystem_id, publish_at, sorting, id)
    WHERE is_active = TRUE
      AND status = 'published';
