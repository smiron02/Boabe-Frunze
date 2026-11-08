-- =====================================================================
-- Boabe & Frunze — date de test
-- Se importă DUPĂ schema.sql.
--
-- Conturi de test:
--   admin  : admin@boabe-frunze.ro  / Admin123!
--   client : client@exemplu.ro      / Client123!
-- =====================================================================

-- Clientul trimite textul ca UTF-8 (altfel diacriticele se strică la import)
SET NAMES utf8mb4;

USE ceai_cafea;

-- ---------------------------------------------------------------------
-- Categorii (cota TVA: 11% alimente, 21% accesorii)
-- ---------------------------------------------------------------------
INSERT INTO categorii (id, nume, descriere, cota_tva) VALUES
(1, 'Cafea boabe',    'Cafea de specialitate în boabe, prăjită în loturi mici.', 11.00),
(2, 'Cafea măcinată', 'Cafea proaspăt măcinată pentru filtru, espresso sau ibric.', 11.00),
(3, 'Ceai',           'Ceaiuri verzi, negre, albe și infuzii din plante.', 11.00),
(4, 'Accesorii',      'Râșnițe, ibrice, filtre și infuzoare.', 21.00);

-- ---------------------------------------------------------------------
-- Produse (24). Stoc 0 la două produse => demonstrăm „stoc epuizat”.
-- Imaginile (.webp) se adaugă în assets/img; până atunci se afișează
-- o imagine implicită.
-- ---------------------------------------------------------------------
INSERT INTO produse (id, categorie_id, nume, descriere, pret, gramaj, imagine, stoc, activ) VALUES
-- Cafea boabe
(1,  1, 'Etiopia Yirgacheffe',   'Note florale de iasomie și citrice, corp ușor. Ideală la filtru.', 54.90, '250 g', 'etiopia-yirgacheffe.webp', 25, 1),
(2,  1, 'Columbia Supremo',      'Echilibrată, cu note de caramel și fructe roșii.', 49.90, '250 g', 'columbia-supremo.webp', 40, 1),
(3,  1, 'Brazilia Santos',       'Dulce, cu note de alune și ciocolată, aciditate scăzută.', 129.90, '1 kg', 'brazilia-santos.webp', 15, 1),
(4,  1, 'Espresso Italiano Blend','Amestec intens, prăjire închisă, cremă bogată.', 119.90, '1 kg', 'espresso-italiano.webp', 20, 1),
(5,  1, 'Kenya AA',              'Aciditate vie, note de coacăze negre și grepfrut.', 59.90, '250 g', 'kenya-aa.webp', 12, 1),
(6,  1, 'Guatemala Antigua',     'Corp plin, note de cacao și condimente.', 79.90, '500 g', 'guatemala-antigua.webp', 18, 1),
-- Cafea măcinată
(7,  2, 'Cafea la ibric Tradițională', 'Măcinată extrafin, gust puternic, ca la bunica.', 29.90, '250 g', 'ibric-traditionala.webp', 50, 1),
(8,  2, 'Cafea turcească cu cardamom', 'Măcinată extrafin, aromatizată cu cardamom.', 34.90, '250 g', 'turceasca-cardamom.webp', 30, 1),
(9,  2, 'Sumatra Mandheling',    'Corp greu, note de pământ și ciocolată amăruie.', 44.90, '250 g', 'sumatra-mandheling.webp', 3, 1),
(10, 2, 'Costa Rica Tarrazú',    'Măcinată mediu pentru filtru, curată și luminoasă.', 47.90, '250 g', 'costa-rica-tarrazu.webp', 22, 1),
(11, 2, 'Espresso Decaf',        'Decofeinizată prin procedeul Swiss Water, măcinată pentru espresso.', 39.90, '250 g', 'espresso-decaf.webp', 14, 1),
(12, 2, 'Honduras Marcala',      'Echilibrată, note de miere și migdale, pentru ibric.', 36.90, '250 g', 'honduras-marcala.webp', 26, 1),
-- Ceai
(13, 3, 'Ceai verde Sencha',     'Ceai verde japonez, proaspăt și ierbos.', 32.90, '100 g', 'sencha.webp', 35, 1),
(14, 3, 'Earl Grey',             'Ceai negru cu ulei esențial de bergamotă.', 27.90, '100 g', 'earl-grey.webp', 45, 1),
(15, 3, 'Ceai de mentă',         'Frunze întregi de mentă piperată, fără cofeină.', 19.90, '50 g', 'menta.webp', 60, 1),
(16, 3, 'Chai Masala',           'Ceai negru cu scorțișoară, ghimbir și cardamom.', 29.90, '100 g', 'chai-masala.webp', 28, 1),
(17, 3, 'Rooibos cu vanilie',    'Infuzie sud-africană, dulce, fără cofeină.', 26.90, '100 g', 'rooibos-vanilie.webp', 33, 1),
(18, 3, 'Ceai alb Pai Mu Tan',   'Ceai alb delicat, note de fân dulce și miere.', 42.90, '50 g', 'pai-mu-tan.webp', 0, 1),
(19, 3, 'Matcha ceremonial',     'Pudră de ceai verde japonez, calitate ceremonială.', 69.90, '30 g', 'matcha.webp', 10, 1),
(20, 3, 'Ceai de mușețel',       'Flori de mușețel, calmant, în plicuri.', 14.90, '20 plicuri', 'musetel.webp', 70, 1),
-- Accesorii
(21, 4, 'Râșniță manuală cu pietre ceramice', 'Măcinare reglabilă, de la espresso la french press.', 149.90, NULL, 'rasnita-manuala.webp', 8, 1),
(22, 4, 'Ibric de cupru',        'Ibric tradițional din cupru cositorit, 300 ml.', 89.90, '300 ml', 'ibric-cupru.webp', 0, 1),
(23, 4, 'Filtre de hârtie V60',  'Filtre conice albe, mărimea 02.', 24.90, '100 buc.', 'filtre-v60.webp', 40, 1),
(24, 4, 'Infuzor de ceai din inox', 'Infuzor tip bilă, cu lanț, pentru ceai vrac.', 19.90, NULL, 'infuzor-inox.webp', 55, 1);

-- ---------------------------------------------------------------------
-- Atribute cafea (scări 1-5). Câte 4 cafele pentru fiecare aparat,
-- ca quiz-ul să poată propune mereu 3 produse.
-- ---------------------------------------------------------------------
INSERT INTO atribute_cafea (produs_id, intensitate, origine, prajire, acid, amar, aparat) VALUES
(1,  2, 'Etiopia',           'deschisă', 5, 1, 'filtru'),
(2,  3, 'Columbia',          'medie',    3, 2, 'filtru'),
(3,  3, 'Brazilia',          'medie',    2, 3, 'espresso'),
(4,  5, 'Brazilia, India',   'închisă',  1, 5, 'espresso'),
(5,  3, 'Kenya',             'deschisă', 5, 2, 'filtru'),
(6,  4, 'Guatemala',         'medie',    3, 3, 'espresso'),
(7,  4, 'Brazilia',          'închisă',  1, 4, 'ibric'),
(8,  4, 'Brazilia, Etiopia', 'închisă',  2, 4, 'ibric'),
(9,  5, 'Indonezia',         'închisă',  1, 5, 'ibric'),
(10, 3, 'Costa Rica',        'medie',    4, 2, 'filtru'),
(11, 2, 'Columbia',          'medie',    2, 2, 'espresso'),
(12, 3, 'Honduras',          'medie',    3, 3, 'ibric');

-- ---------------------------------------------------------------------
-- Utilizatori (parolele sunt hash-uri generate cu password_hash())
-- ---------------------------------------------------------------------
INSERT INTO utilizatori (id, nume, email, parola_hash, rol) VALUES
(1, 'Administrator',  'admin@boabe-frunze.ro', '$2y$10$eYQdEzuMltFEID6FO4z/l.pRzFm9.csNRHxuHe7kj76eoe72Rr06S', 'admin'),
(2, 'Ana Popescu',    'client@exemplu.ro',     '$2y$10$UVcM/3lqVyQcNB1iB6pALeUtK0/59FKEIrzZzEqrhugWdWsTiGHdi', 'client');

-- ---------------------------------------------------------------------
-- Promoții — date RELATIVE la ziua importului, ca să existe mereu
-- promoții active la demonstrație. Una este expirată (pentru test).
-- ---------------------------------------------------------------------
INSERT INTO promotii (produs_id, procent, data_start, data_end) VALUES
(2,  15, CURDATE() - INTERVAL 5 DAY,  CURDATE() + INTERVAL 30 DAY),  -- activă
(14, 10, CURDATE() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 14 DAY),  -- activă
(21, 20, CURDATE(),                   CURDATE() + INTERVAL 7 DAY),   -- activă
(4,  25, CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 10 DAY),  -- expirată
(19, 30, CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY);  -- viitoare

-- ---------------------------------------------------------------------
-- Quiz: 5 întrebări. Profil pornește de la (3, 3, 3); ponderile
-- se adună, apoi rezultatul se limitează la intervalul 1..5.
-- ---------------------------------------------------------------------
INSERT INTO quiz_intrebari (id, text, ordine) VALUES
(1, 'Cum îți prepari de obicei cafeaua?', 1),
(2, 'Cât de intensă îți place cafeaua?', 2),
(3, 'Ce arome preferi?', 3),
(4, 'Bei cafeaua cu lapte?', 4),
(5, 'Când bei cel mai des cafea?', 5);

INSERT INTO quiz_raspunsuri (intrebare_id, text, pondere_intensitate, pondere_acid, pondere_amar, aparat) VALUES
-- 1. Aparatul (setează doar filtrul pe aparat)
(1, 'La filtru / V60 / Chemex',   0, 0, 0, 'filtru'),
(1, 'La espressor',               0, 0, 0, 'espresso'),
(1, 'La ibric (cafea turcească)', 0, 0, 0, 'ibric'),
-- 2. Intensitatea
(2, 'Ușoară, delicată',           -2, 0, 0, NULL),
(2, 'Medie, echilibrată',          0, 0, 0, NULL),
(2, 'Puternică, să mă trezească',  2, 0, 1, NULL),
-- 3. Gustul
(3, 'Fructat, floral, luminos',    0,  2, -1, NULL),
(3, 'Ciocolată, nuci, caramel',    0, -1,  1, NULL),
(3, 'Nu știu, ceva echilibrat',    0,  0,  0, NULL),
-- 4. Laptele (laptele „acoperă” gustul, deci cerem o cafea mai puternică)
(4, 'Da, cu lapte sau frișcă',     1, -1,  1, NULL),
(4, 'Nu, o beau simplă',           0,  0,  0, NULL),
-- 5. Momentul zilei
(5, 'Dimineața, ca să pornesc ziua', 1, 0, 0, NULL),
(5, 'După-amiaza, la o pauză',       0, 0, 0, NULL),
(5, 'Seara, pentru relaxare',       -1, 0, -1, NULL);
