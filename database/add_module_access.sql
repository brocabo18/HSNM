-- Add 'assets' and 'maintenance' to all users with a valid JSON array module_access
UPDATE users
SET module_access = (
    SELECT jsonb_agg(DISTINCT m ORDER BY m)
    FROM jsonb_array_elements_text(
        module_access || '["assets","maintenance"]'::jsonb
    ) AS m
)
WHERE module_access IS NOT NULL
  AND jsonb_typeof(module_access) = 'array'
  AND NOT (module_access @> '["assets"]'::jsonb);
