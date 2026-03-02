ALTER TABLE recipes
  ADD COLUMN IF NOT EXISTS day_time ENUM('fruehstueck','mittag','abend','snack') NULL AFTER description;

ALTER TABLE recipes
  ADD INDEX IF NOT EXISTS idx_recipes_day_time (day_time);
