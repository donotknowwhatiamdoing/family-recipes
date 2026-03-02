ALTER TABLE recipes
  ADD COLUMN IF NOT EXISTS kcal_per_serving DECIMAL(8,2) NULL AFTER day_time,
  ADD COLUMN IF NOT EXISTS protein_g_per_serving DECIMAL(8,2) NULL AFTER kcal_per_serving,
  ADD COLUMN IF NOT EXISTS carbs_g_per_serving DECIMAL(8,2) NULL AFTER protein_g_per_serving,
  ADD COLUMN IF NOT EXISTS fat_g_per_serving DECIMAL(8,2) NULL AFTER carbs_g_per_serving;

