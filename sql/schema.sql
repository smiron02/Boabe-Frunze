-- =====================================================================
-- Boabe & Frunze — schema bazei de date
-- MySQL 8 / MariaDB 10.4+ (XAMPP, Laragon), set de caractere utf8mb4
--
-- Import: phpMyAdmin → Import → schema.sql, apoi date_test.sql
--     sau: mysql -u root < sql/schema.sql
-- ATENȚIE: scriptul șterge și recreează tabelele (datele existente se pierd).
-- =====================================================================

-- Clientul trimite textul ca UTF-8 (altfel diacriticele se strică la import)
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS ceai_cafea
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ceai_cafea;

-- Ștergem în ordinea inversă a dependențelor (copiii înaintea părinților)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS quiz_raspunsuri, quiz_intrebari, abonamente, facturi,
    promotii, comenzi_produse, comenzi, utilizatori, atribute_cafea,
    produse, categorii;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Categorii. Fiecare categorie are propria cotă TVA:
--   11% = cotă redusă (alimente: cafea, ceai), 21% = cotă standard (accesorii)
-- ---------------------------------------------------------------------
CREATE TABLE categorii (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nume      VARCHAR(100) NOT NULL UNIQUE,
    descriere TEXT,
    cota_tva  DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    CONSTRAINT chk_categorii_tva CHECK (cota_tva >= 0 AND cota_tva <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Produse. `pret` include TVA. `activ` = 0 ascunde produsul din catalog
-- fără să-l ștergem (rămâne în comenzile vechi).
-- ---------------------------------------------------------------------
CREATE TABLE produse (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categorie_id INT UNSIGNED NOT NULL,
    nume         VARCHAR(150) NOT NULL,
    descriere    TEXT,
    pret         DECIMAL(10,2) NOT NULL,
    gramaj       VARCHAR(50),               -- ex.: „250 g”, „20 plicuri”
    imagine      VARCHAR(255),              -- nume fișier din assets/img
    stoc         INT UNSIGNED NOT NULL DEFAULT 0,
    activ        TINYINT(1) NOT NULL DEFAULT 1,
    creat_la     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_produse_pret CHECK (pret > 0),
    -- RESTRICT: nu putem șterge o categorie care încă are produse
    CONSTRAINT fk_produse_categorie FOREIGN KEY (categorie_id)
        REFERENCES categorii(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_produse_activ (activ, categorie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Atribute doar pentru cafea (relație 1:1 cu produse). Folosite de quiz.
-- Ceaiurile și accesoriile pur și simplu nu au rând aici.
-- ---------------------------------------------------------------------
CREATE TABLE atribute_cafea (
    produs_id   INT UNSIGNED PRIMARY KEY,
    intensitate TINYINT UNSIGNED NOT NULL,
    origine     VARCHAR(100),
    prajire     ENUM('deschisă','medie','închisă') NOT NULL DEFAULT 'medie',
    acid        TINYINT UNSIGNED NOT NULL,
    amar        TINYINT UNSIGNED NOT NULL,
    aparat      ENUM('filtru','espresso','ibric') NOT NULL,
    CONSTRAINT chk_atribute_intensitate CHECK (intensitate BETWEEN 1 AND 5),
    CONSTRAINT chk_atribute_acid        CHECK (acid BETWEEN 1 AND 5),
    CONSTRAINT chk_atribute_amar        CHECK (amar BETWEEN 1 AND 5),
    -- CASCADE: dacă ștergem produsul, dispar și atributele lui
    CONSTRAINT fk_atribute_produs FOREIGN KEY (produs_id)
        REFERENCES produse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Utilizatori. Parola se păstrează DOAR ca hash (password_hash()).
-- ---------------------------------------------------------------------
CREATE TABLE utilizatori (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nume        VARCHAR(100) NOT NULL,
    email       VARCHAR(190) NOT NULL UNIQUE,
    parola_hash VARCHAR(255) NOT NULL,
    rol         ENUM('client','admin') NOT NULL DEFAULT 'client',
    puncte      INT UNSIGNED NOT NULL DEFAULT 0,   -- fidelitate (doar documentat)
    creat_la    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Comenzi. Status „platita” se setează DOAR după confirmarea Stripe.
-- ---------------------------------------------------------------------
CREATE TABLE comenzi (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizator_id INT UNSIGNED NOT NULL,
    data          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total         DECIMAL(10,2) NOT NULL,
    status        ENUM('noua','platita','expediata','anulata') NOT NULL DEFAULT 'noua',
    adresa        TEXT NOT NULL,
    stripe_id     VARCHAR(255) NULL,               -- ID-ul sesiunii Stripe Checkout
    -- RESTRICT: un utilizator cu comenzi nu poate fi șters (istoric contabil)
    CONSTRAINT fk_comenzi_utilizator FOREIGN KEY (utilizator_id)
        REFERENCES utilizatori(id) ON DELETE RESTRICT,
    INDEX idx_comenzi_status (status),
    UNIQUE KEY uq_comenzi_stripe (stripe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Liniile comenzii. Prețul și cota TVA se COPIAZĂ la momentul comenzii
-- („instantaneu”), ca factura să rămână corectă chiar dacă mai târziu
-- se schimbă prețul produsului sau cota TVA a categoriei.
-- ---------------------------------------------------------------------
CREATE TABLE comenzi_produse (
    comanda_id  INT UNSIGNED NOT NULL,
    produs_id   INT UNSIGNED NOT NULL,
    cantitate   INT UNSIGNED NOT NULL,
    pret_unitar DECIMAL(10,2) NOT NULL,            -- cu TVA, după promoție
    cota_tva    DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (comanda_id, produs_id),
    CONSTRAINT chk_cp_cantitate CHECK (cantitate > 0),
    CONSTRAINT fk_cp_comanda FOREIGN KEY (comanda_id)
        REFERENCES comenzi(id) ON DELETE CASCADE,
    -- RESTRICT: un produs care apare în comenzi nu se șterge (se dezactivează)
    CONSTRAINT fk_cp_produs FOREIGN KEY (produs_id)
        REFERENCES produse(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Promoții: reducere procentuală pe produs, valabilă între două date
-- (inclusiv). Promoție activă: CURDATE() BETWEEN data_start AND data_end
-- ---------------------------------------------------------------------
CREATE TABLE promotii (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produs_id  INT UNSIGNED NOT NULL,
    procent    TINYINT UNSIGNED NOT NULL,
    data_start DATE NOT NULL,
    data_end   DATE NOT NULL,
    CONSTRAINT chk_promotii_procent CHECK (procent BETWEEN 1 AND 90),
    CONSTRAINT chk_promotii_perioada CHECK (data_end >= data_start),
    CONSTRAINT fk_promotii_produs FOREIGN KEY (produs_id)
        REFERENCES produse(id) ON DELETE CASCADE,
    INDEX idx_promotii_perioada (produs_id, data_start, data_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Facturi: una pe comandă (UNIQUE). serie_numar ex.: BF-2026-00001
-- ---------------------------------------------------------------------
CREATE TABLE facturi (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comanda_id  INT UNSIGNED NOT NULL UNIQUE,
    serie_numar VARCHAR(30) NOT NULL UNIQUE,
    baza        DECIMAL(10,2) NOT NULL,            -- total fără TVA
    tva         DECIMAL(10,2) NOT NULL,            -- total TVA (toate cotele)
    total       DECIMAL(10,2) NOT NULL,            -- baza + tva
    emisa_la    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_facturi_comanda FOREIGN KEY (comanda_id)
        REFERENCES comenzi(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Abonamente — DOAR schemă (modul prezentat în documentație, neimplementat)
-- ---------------------------------------------------------------------
CREATE TABLE abonamente (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizator_id      INT UNSIGNED NOT NULL,
    produs_id          INT UNSIGNED NOT NULL,
    frecventa          ENUM('saptamanal','bilunar','lunar') NOT NULL DEFAULT 'lunar',
    urmatoarea_livrare DATE NOT NULL,
    activ              TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_abonamente_utilizator FOREIGN KEY (utilizator_id)
        REFERENCES utilizatori(id) ON DELETE CASCADE,
    CONSTRAINT fk_abonamente_produs FOREIGN KEY (produs_id)
        REFERENCES produse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Quiz „Găsește-ți cafeaua ideală”
-- Ponderile sunt AJUSTĂRI față de un profil neutru (3, 3, 3):
--   profil = 3 + suma ponderilor răspunsurilor alese, limitat la 1..5
-- `aparat` este completat doar la răspunsurile întrebării despre aparat.
-- ---------------------------------------------------------------------
CREATE TABLE quiz_intrebari (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text   VARCHAR(255) NOT NULL,
    ordine TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_raspunsuri (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intrebare_id        INT UNSIGNED NOT NULL,
    text                VARCHAR(255) NOT NULL,
    pondere_intensitate TINYINT NOT NULL DEFAULT 0,   -- poate fi negativă
    pondere_acid        TINYINT NOT NULL DEFAULT 0,
    pondere_amar        TINYINT NOT NULL DEFAULT 0,
    aparat              ENUM('filtru','espresso','ibric') NULL,
    CONSTRAINT fk_raspunsuri_intrebare FOREIGN KEY (intrebare_id)
        REFERENCES quiz_intrebari(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
