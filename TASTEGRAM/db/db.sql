DROP DATABASE IF EXISTS socialapp;
CREATE DATABASE socialapp;
USE socialapp;

-- 2. TABELLA UTENTI
CREATE TABLE users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(20)  NOT NULL UNIQUE,
    email            VARCHAR(100) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    bio              TEXT,
    avatar_url       VARCHAR(255) DEFAULT 'default_avatar.png',
    followers_count  INT UNSIGNED DEFAULT 0,
    following_count  INT UNSIGNED DEFAULT 0,
    role             TINYINT UNSIGNED NOT NULL DEFAULT 2,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- 3. TABELLA POST (Con le colonne likes_count e comments_count)
CREATE TABLE posts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    title_work     VARCHAR(255) NOT NULL,
    content        TEXT         NOT NULL,
    rating         TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
    cuisine_type   VARCHAR(100),
    image_path     VARCHAR(255),
    likes_count    INT UNSIGNED DEFAULT 0,
    comments_count INT UNSIGNED DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_post_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created  (user_id, created_at DESC),
    INDEX idx_created_at    (created_at DESC),
    FULLTEXT INDEX ft_title_work (title_work)
) ENGINE=InnoDB;

-- 4. TABELLA SHOP
CREATE TABLE IF NOT EXISTS `shop_items` (
    `id`          INT              NOT NULL AUTO_INCREMENT,
    `user_id`     INT              NOT NULL,
    `title`       VARCHAR(255)     NOT NULL,
    `description` TEXT             NOT NULL,
    `price`       DECIMAL(10, 2)   NOT NULL,
    `image_path`  VARCHAR(255)     DEFAULT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_shop_user`    (`user_id`),
    KEY `idx_shop_created` (`created_at`),
    CONSTRAINT `fk_shop_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 5. TABELLA FOLLOW
CREATE TABLE follows (
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (follower_id, followed_id),
    CONSTRAINT fk_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_followed FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. TABELLA LIKE
CREATE TABLE likes (
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (post_id, user_id),
    CONSTRAINT fk_like_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. TABELLA COMMENTI
CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT     NOT NULL,
    user_id    INT     NOT NULL,
    parent_id  INT     DEFAULT NULL,
    content    TEXT    NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comment_post   FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
    CONSTRAINT fk_comment_user   FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. TABELLA NOTIFICHE
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT     NOT NULL,
    actor_id   INT     NOT NULL,
    post_id    INT     DEFAULT NULL,
    type       ENUM('follow','like','comment') NOT NULL,
    is_read    BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notif_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. INSERIMENTO UTENTI di DEFAULT (ADMIN-OSPITE)

INSERT INTO users (username, email, password, bio, role) 
VALUES ('admin', 'admin@tastegram.it', '$5f$67$abc' , 'Account admin di tastegram', 1);

INSERT INTO users (username, email, password, bio, role) 
VALUES ('ospite', 'ospite@tastegram.it', '$2y$10$xyz' , 'Account per visitatori', 3);


-- 10. TRIGGER-aggiornano a commento e like il valore dei like e commenti
DELIMITER $$

CREATE TRIGGER trg_like_insert AFTER INSERT ON likes FOR EACH ROW
BEGIN
    UPDATE posts SET likes_count = likes_count + 1 WHERE id = NEW.post_id;
END$$

CREATE TRIGGER trg_like_delete AFTER DELETE ON likes FOR EACH ROW
BEGIN
    UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = OLD.post_id;
END$$

CREATE TRIGGER trg_comment_insert AFTER INSERT ON comments FOR EACH ROW
BEGIN
    UPDATE posts SET comments_count = comments_count + 1 WHERE id = NEW.post_id;
END$$

CREATE TRIGGER trg_comment_delete AFTER DELETE ON comments FOR EACH ROW
BEGIN
    UPDATE posts SET comments_count = comments_count -1  WHERE id = OLD.post_id;
END$$

CREATE TRIGGER trg_follow_insert AFTER INSERT ON follows FOR EACH ROW
BEGIN
    UPDATE users SET followers_count = followers_count + 1 WHERE id = NEW.followed_id;
    UPDATE users SET following_count = following_count + 1 WHERE id = NEW.follower_id;
END$$

CREATE TRIGGER trg_follow_delete AFTER DELETE ON follows FOR EACH ROW
BEGIN
    UPDATE users SET followers_count = GREATEST(followers_count - 1, 0) WHERE id = OLD.followed_id;
    UPDATE users SET following_count = GREATEST(following_count - 1, 0) WHERE id = OLD.follower_id;
END$$

DELIMITER ;