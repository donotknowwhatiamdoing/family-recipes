SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS parties (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_parties_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  party_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  role ENUM('owner','editor','viewer') NOT NULL DEFAULT 'editor',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_party_id (party_id),
  CONSTRAINT fk_users_party FOREIGN KEY (party_id) REFERENCES parties(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_party_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  day_time ENUM('fruehstueck','mittag','abend','snack') NULL,
  kcal_per_serving DECIMAL(8,2) NULL,
  protein_g_per_serving DECIMAL(8,2) NULL,
  carbs_g_per_serving DECIMAL(8,2) NULL,
  fat_g_per_serving DECIMAL(8,2) NULL,
  servings SMALLINT UNSIGNED NULL,
  prep_minutes SMALLINT UNSIGNED NULL,
  cook_minutes SMALLINT UNSIGNED NULL,
  image_url VARCHAR(500) NULL,
  visibility ENUM('private','internal','public_link') NOT NULL DEFAULT 'private',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_recipes_owner_party (owner_party_id),
  KEY idx_recipes_created_by (created_by_user_id),
  KEY idx_recipes_visibility (visibility),
  KEY idx_recipes_day_time (day_time),
  FULLTEXT KEY ftx_recipes_title_desc (title, description),
  CONSTRAINT fk_recipes_owner_party FOREIGN KEY (owner_party_id) REFERENCES parties(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_recipes_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id BIGINT UNSIGNED NOT NULL,
  step_no SMALLINT UNSIGNED NOT NULL,
  instruction TEXT NOT NULL,
  image_url VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipe_steps_recipe_stepno (recipe_id, step_no),
  KEY idx_recipe_steps_recipe_id (recipe_id),
  CONSTRAINT fk_recipe_steps_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id BIGINT UNSIGNED NOT NULL,
  line_no SMALLINT UNSIGNED NOT NULL,
  ingredient_name VARCHAR(220) NOT NULL,
  quantity DECIMAL(10,2) NULL,
  unit VARCHAR(40) NULL,
  note VARCHAR(220) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipe_ingredients_recipe_lineno (recipe_id, line_no),
  KEY idx_recipe_ingredients_recipe_id (recipe_id),
  CONSTRAINT fk_recipe_ingredients_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  label VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_tags (
  recipe_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (recipe_id, tag_id),
  KEY idx_recipe_tags_tag_id (tag_id),
  CONSTRAINT fk_recipe_tags_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recipe_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_internal_shares (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id BIGINT UNSIGNED NOT NULL,
  shared_with_party_id BIGINT UNSIGNED NOT NULL,
  permission ENUM('view','edit') NOT NULL DEFAULT 'view',
  shared_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipe_internal_shares (recipe_id, shared_with_party_id),
  KEY idx_recipe_internal_shares_shared_with (shared_with_party_id),
  CONSTRAINT fk_internal_shares_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_internal_shares_party FOREIGN KEY (shared_with_party_id) REFERENCES parties(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_internal_shares_user FOREIGN KEY (shared_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_public_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id BIGINT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipe_public_links_token (token),
  KEY idx_recipe_public_links_recipe_id (recipe_id),
  KEY idx_recipe_public_links_expires_at (expires_at),
  CONSTRAINT fk_public_links_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_public_links_user FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_favorites (
  user_id BIGINT UNSIGNED NOT NULL,
  recipe_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, recipe_id),
  KEY idx_recipe_favorites_recipe (recipe_id),
  CONSTRAINT fk_recipe_favorites_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recipe_favorites_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ratings (
  user_id BIGINT UNSIGNED NOT NULL,
  recipe_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, recipe_id),
  KEY idx_recipe_ratings_recipe (recipe_id),
  CONSTRAINT chk_recipe_ratings_range CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_recipe_ratings_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recipe_ratings_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  recipe_id BIGINT UNSIGNED NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message_text MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chat_messages_user (user_id),
  KEY idx_chat_messages_recipe (recipe_id),
  CONSTRAINT fk_chat_messages_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_messages_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_tokens_token_hash (token_hash),
  KEY idx_auth_tokens_user_id (user_id),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
