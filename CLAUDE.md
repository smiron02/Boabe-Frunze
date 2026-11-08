# CLAUDE.md — Magazin online de ceai și cafea

## Despre proiect

Proiect universitar de comerț electronic (B2C): un magazin online de **cafea și ceai** pentru România, realizat manual în **PHP + MySQL**, fără framework. Numele magazinului este provizoriu: **Boabe & Frunze** (se poate schimba dintr-un singur loc, în `includes/config.php`).

Proiectul este evaluat după un barem, deci fiecare funcționalitate trebuie să poată fi **demonstrată și explicată** de student la susținere. Codul trebuie să fie simplu, clar și comentat în română.

## Cerințe obligatorii

1. Site publicat pe un server web (sau livrat ca arhivă zip + export SQL).
2. Bază de date: **MySQL** (acces prin PDO).
3. Script-uri pe server în **PHP 8**.
4. Plată electronică **testată** (Stripe în mod test, card `4242 4242 4242 4242`).

## Elemente clasice de implementat (0.75 p)

1. Meniu de navigare (CSS + puțin JavaScript)
2. Catalog de produse
3. Categorii: Cafea boabe, Cafea măcinată, Ceai, Accesorii
4. Pagină de detalii produs (caracteristici, preț, imagine)
5. Coș de cumpărături + actualizare (cantitate, ștergere)
6. Înregistrarea comenzii pentru un cont de utilizator
7. Stocuri (scădere la comandă, afișare „stoc epuizat")
8. Promoții (reducere procentuală, cu perioadă de valabilitate)
9. Factură (articole, prețuri unitare, valori, TVA)
10. Plata facturii cu Stripe în mod test
11. Interfață de administrare (produse, utilizatori, comenzi, promoții, stocuri)

## Personalizare (1 p)

**Modul implementat: Quiz „Găsește-ți cafeaua ideală"** (`quiz.php`)

- 4-5 întrebări: aparatul folosit (filtru/espresso/ibric), intensitatea preferată, gust (fructat/ciocolată), cu sau fără lapte.
- Fiecare răspuns setează valori pe profilul utilizatorului: `intensitate`, `acid`, `amar`, `aparat`.
- Interogare SQL care ordonează produsele după distanța față de profil, de exemplu `ABS(intensitate - :i) + ABS(acid - :a) + ABS(amar - :m)`, filtrate după aparat.
- Se afișează primele 3 produse cu **procent de potrivire** și buton „Adaugă în coș".
- Decis (etapa 1): ponderile din `quiz_raspunsuri` sunt **ajustări** față de profilul neutru (3, 3, 3); profil = 3 + suma ponderilor, limitat la 1..5. Procent potrivire = 100 − distanță / 12 × 100 (12 = distanța maximă).

**Module doar prezentate în documentație (neimplementate):** abonament lunar, puncte de fidelitate, pachete personalizate „alege 3 sortimente".

## Tehnologii și reguli

- PHP 8+, MySQL, **PDO cu prepared statements** (niciodată concatenare de variabile în SQL).
- Fără framework. Se permit doar biblioteci prin Composer: `stripe/stripe-php` și, opțional, `dompdf/dompdf` pentru factura PDF.
- Frontend: HTML5, CSS propriu (fără framework mare), JavaScript simplu. Design responsive.
- Parole cu `password_hash()` / `password_verify()`.
- Toate datele afișate trec prin `htmlspecialchars()`.
- Formularele care modifică date folosesc **token CSRF**.
- Coșul se ține în `$_SESSION`; prețurile se **recalculează din baza de date** la checkout, nu se iau din sesiune sau din formular.
- Cheile Stripe și parolele bazei de date stau doar în `includes/config.php`, care este în `.gitignore`. Se versionează doar `includes/config.example.php`.
- Interfața și textele sunt în **română**, cu diacritice. Codul (variabile, funcții) poate fi în română sau engleză, dar consecvent.
- Moneda: **RON**. Prețurile includ TVA; pe factură se detaliază baza și TVA.
- **TVA pe categorie** (decis în etapa 1): coloana `categorii.cota_tva` — **11%** cafea și ceai (cotă redusă alimente, în vigoare din 1 aug. 2025), **21%** accesorii (cotă standard). Cota se copiază în `comenzi_produse.cota_tva` la plasarea comenzii; factura calculează baza/TVA pe fiecare linie și afișează un subtotal pentru fiecare cotă.

## Structura proiectului

```
/ceai-cafea
├── index.php            # acasă: promoții, produse recomandate
├── catalog.php          # listă produse + filtre pe categorie
├── produs.php           # detalii produs + adăugare în coș
├── cos.php              # afișare coș + actualizare cantități
├── checkout.php         # adresă livrare + inițiere plată Stripe
├── plata_succes.php     # confirmare plată (callback Stripe)
├── comanda.php          # detalii comandă
├── factura.php          # factură HTML/PDF
├── login.php
├── register.php
├── logout.php
├── cont.php             # profilul și istoricul comenzilor
├── quiz.php             # modulul de personalizare
├── /admin
│   ├── index.php        # dashboard
│   ├── produse.php      # CRUD produse + stoc
│   ├── categorii.php
│   ├── comenzi.php      # listă comenzi + schimbare status
│   ├── utilizatori.php
│   └── promotii.php
├── /includes
│   ├── config.php       # NU în Git
│   ├── config.example.php
│   ├── db.php           # conexiune PDO
│   ├── functii.php      # funcții ajutătoare (preț cu promoție, CSRF, flash etc.)
│   ├── auth.php         # verificare login / rol admin
│   ├── header.php
│   └── footer.php
├── /assets
│   ├── /css
│   ├── /js
│   └── /img             # imagini produse, preferabil WebP
├── /sql
│   ├── schema.sql       # tabele
│   └── date_test.sql    # 20-30 produse de test
├── /docs                # print screen-uri și documentația proiectului
├── .gitignore
└── CLAUDE.md
```

## Baza de date (MySQL, utf8mb4)

| Tabel | Câmpuri principale |
|---|---|
| `categorii` | id, nume, descriere, **cota_tva** |
| `produse` | id, categorie_id, nume, descriere, pret, gramaj, imagine, stoc, activ, creat_la |
| `atribute_cafea` | produs_id, intensitate (1-5), origine, prajire, acid (1-5), amar (1-5), aparat (filtru/espresso/ibric) |
| `utilizatori` | id, nume, email (unic), parola_hash, rol (client/admin), puncte, creat_la |
| `comenzi` | id, utilizator_id, data, total, status (noua/platita/expediata/anulata), adresa, stripe_id |
| `comenzi_produse` | comanda_id, produs_id, cantitate, pret_unitar, **cota_tva** |
| `promotii` | id, produs_id, procent, data_start, data_end |
| `facturi` | id, comanda_id, serie_numar, baza, tva, total, emisa_la |
| `abonamente` | id, utilizator_id, produs_id, frecventa, urmatoarea_livrare, activ (doar schemă, neimplementat) |
| `quiz_intrebari` | id, text, ordine |
| `quiz_raspunsuri` | id, intrebare_id, text, pondere_intensitate, pondere_acid, pondere_amar, aparat |

Relații cu chei străine și `ON DELETE` potrivit. Produsele din categoria Ceai și Accesorii pot avea `atribute_cafea` gol.

Conturi de test (din `date_test.sql`): `admin@boabe-frunze.ro` / `Admin123!`, `client@exemplu.ro` / `Client123!`.

## Plata și factura

- Plata: **Stripe Checkout** în mod test (sesiune creată în PHP, redirect către Stripe, apoi întoarcere în `plata_succes.php`). Statusul comenzii devine „platita" doar după confirmarea plătii, verificată pe server.
- La plată confirmată: se scade stocul (într-o tranzacție), se creează factura cu serie și număr unic, se golește coșul.
- Factura conține: date vânzător și client, articole, preț unitar, cantitate, valoare, baza impozabilă, TVA (pe cote), total.

## Performanță (test PageSpeed)

Site-ul va fi testat cu https://pagespeed.web.dev/. De la început:
- imagini WebP, dimensionate corect, cu `width`/`height` și `loading="lazy"`
- CSS și JS minime, JS cu `defer`
- fără biblioteci încărcate degeaba
- titlu, `meta description` și structură semantică HTML (accesibilitate, SEO)
- compresie și cache activate în `.htaccess`

## Mod de lucru cu Claude Code

- Lucrează **pe etape mici**, câte una pe rând, și la final spune cum se testează în browser.
- Înainte de modificări mari, propune un plan scurt și așteaptă confirmarea.
- Explică pe scurt codul important, deoarece studentul trebuie să-l poată prezenta.
- Nu adăuga funcționalități din afara listei fără să întrebi.
- Fă un commit Git după fiecare etapă funcțională, cu mesaj descriptiv în română.
- Nu șterge și nu rescrie fișiere existente fără să spui ce schimbi și de ce.
- Mediul local: XAMPP sau Laragon (PHP 8 + MySQL), accesibil la `http://localhost/ceai-cafea/`.

## Etape (bifează pe parcurs)

- [x] 1. `sql/schema.sql`, `sql/date_test.sql`, `includes/db.php`, `config.example.php`
- [x] 2. Header, footer, meniu, CSS de bază
- [x] 3. Catalog cu categorii, pagina de produs
- [x] 4. Înregistrare, login, logout, roluri
- [x] 5. Coș de cumpărături
- [x] 6. Checkout, comenzi, stocuri
- [ ] 7. Promoții și factură
- [ ] 8. Plată Stripe în mod test
- [ ] 9. Panou de administrare
- [ ] 10. Quiz „Găsește-ți cafeaua"
- [ ] 11. Optimizare PageSpeed, publicare, documentație cu print screen-uri

## Documentația finală (de pregătit în `/docs`)

Prezentare `site_nume_prenume.zip` cu: tipul de comerț (B2C), produsele și motivul alegerii, site-ul model și concluzia studiului de caz, platforma (PHP manual, motivul), baza de date (MySQL), link-ul de publicare sau arhiva site-ului, print screen pentru fiecare cerință, prezentarea personalizărilor cu print screen și rezultatul PageSpeed.
