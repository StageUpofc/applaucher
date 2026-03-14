-- ============================================================
-- GB Launcher - Script SQL
-- Banco de dados: gb_launcher
-- ============================================================

CREATE DATABASE IF NOT EXISTS `gb_launcher`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gb_launcher`;

-- ============================================================
-- Tabela: settings (configurações gerais da launcher)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`  VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT        DEFAULT NULL,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores padrão de configuração
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('logo_url',        '/uploads/logo.png'),
  ('wallpaper_url',   '/uploads/wallpaper.jpg'),
  ('launcher_title',  'GB Launcher'),
  ('primary_color',   '#1565C0'),
  ('accent_color',    '#FFA726'),
  ('api_token',       'gb_secure_token_2024');

-- ============================================================
-- Tabela: apps (aplicações exibidas na launcher)
-- ============================================================
CREATE TABLE IF NOT EXISTS `apps` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `package_name` VARCHAR(255) NOT NULL UNIQUE,
  `icon_url`     TEXT         DEFAULT NULL,
  `category`     VARCHAR(100) DEFAULT 'geral',
  `description`  TEXT         DEFAULT NULL,
  `position`     INT(11)      NOT NULL DEFAULT 0,
  `is_visible`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_pinned`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_position`   (`position`),
  KEY `idx_is_visible` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Apps de exemplo
INSERT INTO `apps` (`name`, `package_name`, `icon_url`, `category`, `description`, `position`, `is_visible`, `is_pinned`) VALUES
  ('YouTube',       'com.google.android.youtube',           'https://upload.wikimedia.org/wikipedia/commons/thumb/0/09/YouTube_full-color_icon_%282017%29.svg/1024px-YouTube_full-color_icon_%282017%29.svg.png', 'entretenimento', 'Assista vídeos do YouTube',    1, 1, 1),
  ('Netflix',       'com.netflix.mediaclient',              'https://upload.wikimedia.org/wikipedia/commons/thumb/0/08/Netflix_2015_logo.svg/1920px-Netflix_2015_logo.svg.png',                                  'entretenimento', 'Streaming de filmes e séries',  2, 1, 1),
  ('Prime Video',   'com.amazon.avod.thirdpartyclient',     'https://upload.wikimedia.org/wikipedia/commons/thumb/1/11/Amazon_Prime_Video_logo.svg/1920px-Amazon_Prime_Video_logo.svg.png',                       'entretenimento', 'Amazon Prime Video',            3, 1, 0),
  ('Disney+',       'com.disney.disneyplus',                'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Disney%2B_logo.svg/1920px-Disney%2B_logo.svg.png',                                        'entretenimento', 'Disney Plus Streaming',         4, 1, 0),
  ('Kodi',          'org.xbmc.kodi',                        'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Kodi-logo-Thumbnail-light-transparent.png/1024px-Kodi-logo-Thumbnail-light-transparent.png','iptv',          'Media center completo',         5, 1, 0),
  ('Configurações', 'com.android.settings',                 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Gear_icon.svg/1024px-Gear_icon.svg.png',                                                  'sistema',        'Configurações do dispositivo',  6, 1, 0);

-- ============================================================
-- Tabela: categories (categorias de apps)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `name`       VARCHAR(150) NOT NULL,
  `icon`       VARCHAR(100) DEFAULT 'apps',
  `position`   INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`slug`, `name`, `icon`, `position`) VALUES
  ('todos',         'Todos',          'apps',               0),
  ('entretenimento','Entretenimento', 'movie',              1),
  ('iptv',          'IPTV',           'live_tv',            2),
  ('jogos',         'Jogos',          'sports_esports',     3),
  ('sistema',       'Sistema',        'settings',           4),
  ('geral',         'Geral',          'grid_view',          5);

-- ============================================================
-- Tabela: admin_users (usuários do painel admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `email`      VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário padrão: admin / admin123 (troque imediatamente!)
INSERT INTO `admin_users` (`username`, `password`, `email`) VALUES
  ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gblauncher.com');
  -- senha: password (hash bcrypt padrão do Laravel para testes)

-- ============================================================
-- Tabela: banners (banners/destaques do topo)
-- ============================================================
CREATE TABLE IF NOT EXISTS `banners` (
  `id`         INT(11)  NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200) DEFAULT NULL,
  `image_url`  TEXT         NOT NULL,
  `action`     VARCHAR(255) DEFAULT NULL,
  `position`   INT(11)      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
