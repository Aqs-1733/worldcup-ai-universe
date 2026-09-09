CREATE DATABASE IF NOT EXISTS `worldcup_ai_universe`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `worldcup_ai_universe`;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(80) NULL,
  avatar_url VARCHAR(500) NULL,
  survey_completed_at DATETIME NULL,
  survey_skipped_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  student_no VARCHAR(50) NULL,
  role_name VARCHAR(80) NOT NULL,
  bio TEXT NULL,
  photo_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_team_members_name_role (name, role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_key VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  url VARCHAR(800) NOT NULL,
  source_type ENUM('official','api','wiki','rss','manual') NOT NULL DEFAULT 'api',
  trust_level TINYINT UNSIGNED NOT NULL DEFAULT 80,
  last_synced_at DATETIME NULL,
  last_status VARCHAR(40) NULL,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fifa_id VARCHAR(80) NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  code VARCHAR(8) NULL,
  name_cn VARCHAR(120) NULL,
  name_original VARCHAR(160) NOT NULL,
  country_code CHAR(2) NULL,
  flag_emoji VARCHAR(16) NULL,
  flag_url VARCHAR(800) NULL,
  confederation VARCHAR(40) NULL,
  group_name VARCHAR(20) NULL,
  coach_name VARCHAR(160) NULL,
  world_ranking INT NULL,
  profile TEXT NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_group (group_name),
  INDEX idx_teams_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_stages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(80) NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  name_cn VARCHAR(120) NULL,
  stage_order INT NULL,
  is_knockout TINYINT(1) NOT NULL DEFAULT 0,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(80) NULL UNIQUE,
  name_cn VARCHAR(160) NULL,
  name_original VARCHAR(220) NOT NULL,
  country_code CHAR(3) NULL,
  avg_cards_per_game DECIMAL(5,2) NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id BIGINT UNSIGNED NULL,
  fifa_id VARCHAR(80) NULL UNIQUE,
  slug VARCHAR(160) NOT NULL UNIQUE,
  name_cn VARCHAR(160) NULL,
  name_original VARCHAR(220) NOT NULL,
  position VARCHAR(40) NULL,
  shirt_number INT NULL,
  birth_date DATE NULL,
  age INT NULL,
  club VARCHAR(180) NULL,
  caps INT NULL,
  goals INT NULL,
  height_cm INT NULL,
  photo_url VARCHAR(800) NULL,
  popularity_score DECIMAL(8,2) NOT NULL DEFAULT 0,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_players_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  INDEX idx_players_team_popularity (team_id, popularity_score),
  INDEX idx_players_name_original (name_original)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coaches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id BIGINT UNSIGNED NOT NULL,
  name_cn VARCHAR(160) NULL,
  name_original VARCHAR(220) NOT NULL,
  nationality VARCHAR(120) NULL,
  source_url VARCHAR(800) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_coaches_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fifa_id VARCHAR(80) NULL UNIQUE,
  name_cn VARCHAR(160) NULL,
  name_original VARCHAR(220) NOT NULL,
  city_cn VARCHAR(120) NULL,
  city_original VARCHAR(160) NULL,
  country_code CHAR(2) NULL,
  capacity INT NULL,
  source_url VARCHAR(800) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(100) NULL UNIQUE,
  stage VARCHAR(80) NOT NULL,
  group_name VARCHAR(20) NULL,
  home_team_id BIGINT UNSIGNED NULL,
  away_team_id BIGINT UNSIGNED NULL,
  home_team_name VARCHAR(160) NULL,
  away_team_name VARCHAR(160) NULL,
  home_score INT NULL,
  away_score INT NULL,
  home_penalty_score INT NULL,
  away_penalty_score INT NULL,
  status ENUM('scheduled','live','finished','postponed','cancelled','unknown') NOT NULL DEFAULT 'unknown',
  starts_at DATETIME NULL,
  venue_id BIGINT UNSIGNED NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_matches_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_matches_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
  INDEX idx_matches_stage_time (stage, starts_at),
  INDEX idx_matches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_stats (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NULL,
  possession DECIMAL(5,2) NULL,
  shots INT NULL,
  shots_on_target INT NULL,
  corners INT NULL,
  fouls INT NULL,
  yellow_cards INT NULL,
  red_cards INT NULL,
  expected_goals DECIMAL(5,2) NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_stats_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_stats_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_match_stats_team (match_id, team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_statistics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_id BIGINT UNSIGNED NULL,
  external_player_id VARCHAR(80) NULL UNIQUE,
  matches_played INT NULL,
  matches_started INT NULL,
  minutes_played INT NULL,
  goals INT NULL,
  assists INT NULL,
  shots INT NULL,
  shots_on_target INT NULL,
  yellow_cards INT NULL,
  red_cards INT NULL,
  penalty_goals INT NULL,
  own_goals INT NULL,
  clean_sheets INT NULL,
  saves INT NULL,
  goals_conceded INT NULL,
  average_rating DECIMAL(5,2) NULL,
  data_source VARCHAR(160) NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  raw_payload_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_player_statistics_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
  INDEX idx_player_statistics_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_lineups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_lineup_id VARCHAR(100) NULL UNIQUE,
  match_id BIGINT UNSIGNED NULL,
  player_id BIGINT UNSIGNED NULL,
  team_id BIGINT UNSIGNED NULL,
  is_starting_xi TINYINT(1) NOT NULL DEFAULT 0,
  tactical_position VARCHAR(40) NULL,
  minutes_played INT NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  raw_payload_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_lineups_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_lineups_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
  CONSTRAINT fk_match_lineups_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  INDEX idx_match_lineups_match (match_id),
  INDEX idx_match_lineups_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_event_id VARCHAR(100) NULL UNIQUE,
  match_id BIGINT UNSIGNED NULL,
  minute INT NULL,
  event_type VARCHAR(80) NOT NULL,
  team_id BIGINT UNSIGNED NULL,
  player_id BIGINT UNSIGNED NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  raw_payload_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_events_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_events_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_match_events_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
  INDEX idx_match_events_match (match_id),
  INDEX idx_match_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_prediction_features (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_match_id VARCHAR(100) NOT NULL UNIQUE,
  match_id BIGINT UNSIGNED NULL,
  home_team_id BIGINT UNSIGNED NULL,
  away_team_id BIGINT UNSIGNED NULL,
  feature_payload_json JSON NOT NULL,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_prediction_features_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
  CONSTRAINT fk_match_prediction_features_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_match_prediction_features_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS standings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id BIGINT UNSIGNED NOT NULL,
  group_name VARCHAR(20) NOT NULL,
  played INT NOT NULL DEFAULT 0,
  won INT NOT NULL DEFAULT 0,
  drawn INT NOT NULL DEFAULT 0,
  lost INT NOT NULL DEFAULT 0,
  goals_for INT NOT NULL DEFAULT 0,
  goals_against INT NOT NULL DEFAULT 0,
  goal_difference INT NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  source_url VARCHAR(800) NULL,
  source_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_standings_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_standings_group_team (group_name, team_id),
  INDEX idx_standings_group_points (group_name, points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  url VARCHAR(800) NOT NULL,
  rss_url VARCHAR(800) NULL,
  language_code VARCHAR(20) NOT NULL DEFAULT 'en',
  trust_level TINYINT UNSIGNED NOT NULL DEFAULT 80,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_synced_at DATETIME NULL,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_news_sources_rss (rss_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name_cn VARCHAR(80) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#16a34a',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id BIGINT UNSIGNED NULL,
  source_name VARCHAR(120) NOT NULL,
  source_url VARCHAR(900) NOT NULL UNIQUE,
  title_cn VARCHAR(280) NULL,
  title_original VARCHAR(280) NOT NULL,
  summary_cn TEXT NULL,
  summary_original TEXT NULL,
  content_cn MEDIUMTEXT NULL,
  content_original MEDIUMTEXT NULL,
  language_code VARCHAR(20) NOT NULL DEFAULT 'en',
  published_at DATETIME NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  credibility_score TINYINT UNSIGNED NOT NULL DEFAULT 80,
  translation_status ENUM('none','translated','failed') NOT NULL DEFAULT 'none',
  raw_payload_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_news_articles_source FOREIGN KEY (source_id) REFERENCES news_sources(id) ON DELETE SET NULL,
  INDEX idx_news_published (published_at),
  INDEX idx_news_credibility (credibility_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_article_tags (
  article_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, tag_id),
  CONSTRAINT fk_news_article_tags_article FOREIGN KEY (article_id) REFERENCES news_articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_news_article_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('team','player','match','news','site') NOT NULL DEFAULT 'site',
  entity_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  status ENUM('visible','hidden','pending') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_comments_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fan_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  push_match TINYINT(1) NOT NULL DEFAULT 1,
  push_tactics TINYINT(1) NOT NULL DEFAULT 1,
  push_team TINYINT(1) NOT NULL DEFAULT 1,
  push_fanmade TINYINT(1) NOT NULL DEFAULT 1,
  push_entertainment TINYINT(1) NOT NULL DEFAULT 1,
  preferred_language VARCHAR(20) NOT NULL DEFAULT 'zh-CN',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fan_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_favorite_teams (
  user_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, team_id),
  CONSTRAINT fk_favorite_teams_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorite_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_favorite_players (
  user_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, player_id),
  CONSTRAINT fk_favorite_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorite_players_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_blocked_teams (
  user_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, team_id),
  CONSTRAINT fk_blocked_teams_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blocked_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  post_type ENUM('announcement','team','personal','match') NOT NULL DEFAULT 'announcement',
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_generations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  generation_type ENUM('chat','image','vision') NOT NULL DEFAULT 'chat',
  prompt TEXT NOT NULL,
  tone VARCHAR(80) NULL,
  target_team VARCHAR(120) NULL,
  response_text MEDIUMTEXT NULL,
  image_url VARCHAR(900) NULL,
  model_name VARCHAR(160) NULL,
  status ENUM('success','failed') NOT NULL DEFAULT 'success',
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_generations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ai_generations_user_type (user_id, generation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dataset_key VARCHAR(120) NOT NULL,
  source_name VARCHAR(160) NOT NULL,
  source_path VARCHAR(900) NOT NULL UNIQUE,
  source_url VARCHAR(900) NULL,
  file_type VARCHAR(20) NOT NULL,
  row_count INT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_source_files_dataset (dataset_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_file_id BIGINT UNSIGNED NOT NULL,
  row_number INT NOT NULL,
  entity_key VARCHAR(160) NULL,
  record_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_source_records_file FOREIGN KEY (source_file_id) REFERENCES source_files(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_source_record_row (source_file_id, row_number),
  INDEX idx_source_records_entity (entity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_quality_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  check_key VARCHAR(120) NOT NULL UNIQUE,
  label VARCHAR(200) NOT NULL,
  expected_value VARCHAR(120) NULL,
  actual_value VARCHAR(120) NULL,
  status ENUM('pass','warn','fail') NOT NULL DEFAULT 'warn',
  detail TEXT NULL,
  source_url VARCHAR(900) NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_data_quality_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  detail_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_logs_action (action),
  INDEX idx_audit_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
