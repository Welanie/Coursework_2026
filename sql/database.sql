CREATE DATABASE IF NOT EXISTS chem_flashcards
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chem_flashcards;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS study_sessions;
DROP TABLE IF EXISTS user_progress;
DROP TABLE IF EXISTS flashcards;
DROP TABLE IF EXISTS flashcard_sets;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flashcard_sets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sets_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flashcards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  set_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  formula VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_flashcards_formula (formula),
  INDEX idx_flashcards_name (name),
  CONSTRAINT fk_flashcards_set
    FOREIGN KEY (set_id) REFERENCES flashcard_sets(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_flashcards_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  flashcard_id INT UNSIGNED NOT NULL,
  correct_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_count INT UNSIGNED NOT NULL DEFAULT 0,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_reviewed DATETIME NULL,
  next_review DATETIME NULL,
  UNIQUE KEY uq_progress_user_card (user_id, flashcard_id),
  CONSTRAINT fk_progress_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_progress_card
    FOREIGN KEY (flashcard_id) REFERENCES flashcards(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  set_id INT UNSIGNED NOT NULL,
  mode ENUM('normal', 'review', 'test') NOT NULL DEFAULT 'normal',
  score INT UNSIGNED NOT NULL DEFAULT 0,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sessions_date (date),
  CONSTRAINT fk_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_sessions_set
    FOREIGN KEY (set_id) REFERENCES flashcard_sets(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  flashcard_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorites_user_card (user_id, flashcard_id),
  CONSTRAINT fk_favorites_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_favorites_card
    FOREIGN KEY (flashcard_id) REFERENCES flashcards(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, username, email, password_hash, role) VALUES
(1, 'admin', 'admin@example.local', 'sha256$8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'admin'),
(2, 'student', 'student@example.local', 'sha256$8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'user'),
(3, 'marina', 'marina@example.local', 'sha256$8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'user'),
(4, 'pavel', 'pavel@example.local', 'sha256$8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'user');

INSERT INTO categories (id, name, description) VALUES
(1, 'Оксиды', 'Соединения элементов с кислородом'),
(2, 'Соли', 'Ионные соединения кислотных остатков и металлов'),
(3, 'Кислоты', 'Вещества, содержащие атомы водорода и кислотный остаток'),
(4, 'Основания', 'Гидроксиды металлов'),
(5, 'Гидриды', 'Соединения с водородом'),
(6, 'Углеводороды', 'Органические вещества из углерода и водорода'),
(7, 'Спирты и углеводы', 'Органические соединения с кислородом'),
(8, 'Простые вещества', 'Вещества из атомов одного элемента');

INSERT INTO flashcard_sets (id, user_id, name, description, is_public) VALUES
(1, NULL, 'Базовые химические формулы', 'Демо-набор и основной набор для изучения формул школьного курса.', 1),
(2, NULL, 'Кислоты, соли и основания', 'Набор для тренировки неорганических соединений.', 1),
(3, 2, 'Органика для зачёта', 'Пользовательский набор с простыми органическими веществами.', 1);

INSERT INTO flashcards (id, set_id, category_id, formula, name, difficulty) VALUES
(1, 1, 1, 'H2O', 'Вода', 1),
(2, 1, 1, 'CO2', 'Углекислый газ', 1),
(3, 1, 2, 'NaCl', 'Хлорид натрия', 1),
(4, 1, 3, 'HCl', 'Соляная кислота', 2),
(5, 1, 4, 'NaOH', 'Гидроксид натрия', 2),
(6, 1, 3, 'H2SO4', 'Серная кислота', 3),
(7, 1, 2, 'CaCO3', 'Карбонат кальция', 3),
(8, 1, 6, 'CH4', 'Метан', 1),
(9, 1, 7, 'C2H5OH', 'Этанол', 2),
(10, 1, 5, 'NH3', 'Аммиак', 2),
(11, 1, 1, 'CO', 'Угарный газ', 2),
(12, 1, 1, 'SO2', 'Диоксид серы', 2),
(13, 1, 8, 'O2', 'Кислород', 1),
(14, 1, 8, 'N2', 'Азот', 1),
(15, 1, 8, 'H2', 'Водород', 1),
(16, 2, 3, 'HNO3', 'Азотная кислота', 3),
(17, 2, 3, 'H3PO4', 'Ортофосфорная кислота', 4),
(18, 2, 3, 'CH3COOH', 'Уксусная кислота', 4),
(19, 2, 4, 'KOH', 'Гидроксид калия', 2),
(20, 2, 4, 'Ca(OH)2', 'Гидроксид кальция', 3),
(21, 2, 2, 'KCl', 'Хлорид калия', 1),
(22, 2, 2, 'Na2CO3', 'Карбонат натрия', 3),
(23, 2, 2, 'CuSO4', 'Сульфат меди', 3),
(24, 2, 1, 'Fe2O3', 'Оксид железа(III)', 4),
(25, 2, 2, 'KMnO4', 'Перманганат калия', 5),
(26, 3, 6, 'C2H6', 'Этан', 2),
(27, 3, 6, 'C2H4', 'Этилен', 3),
(28, 3, 6, 'C2H2', 'Ацетилен', 3),
(29, 3, 7, 'C6H12O6', 'Глюкоза', 4),
(30, 3, 1, 'SO3', 'Триоксид серы', 3);

INSERT INTO user_progress (user_id, flashcard_id, correct_count, total_count, rating, last_reviewed, next_review) VALUES
(2, 1, 5, 6, 4, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY)),
(2, 2, 4, 5, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY)),
(2, 4, 1, 4, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(2, 6, 0, 3, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()),
(2, 9, 2, 3, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY)),
(3, 1, 2, 2, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY));

INSERT INTO study_sessions (user_id, set_id, mode, score, total, date) VALUES
(2, 1, 'test', 6, 10, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 1, 'normal', 7, 9, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 1, 'review', 4, 7, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2, 'test', 8, 10, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 1, 'test', 5, 10, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO favorites (user_id, flashcard_id) VALUES
(2, 1),
(2, 6),
(2, 9);
