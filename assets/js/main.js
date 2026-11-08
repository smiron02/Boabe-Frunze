/**
 * Meniul de navigare pe telefon („hamburger”).
 * Butonul deschide/închide meniul, iar aria-expanded spune
 * cititoarelor de ecran dacă meniul este deschis.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Clasa „js” pe <html>: CSS-ul poate ascunde elemente necesare doar fără JavaScript
    document.documentElement.classList.add('js');

    // Câmpurile <select data-trimite-automat> trimit formularul la schimbare
    // (ex.: ordonarea din catalog), fără a mai apăsa „Aplică”.
    document.querySelectorAll('[data-trimite-automat]').forEach(function (camp) {
        camp.addEventListener('change', function () { camp.form.submit(); });
    });

    // Butoanele <button data-tipareste> deschid dialogul de tipărire (factura)
    document.querySelectorAll('[data-tipareste]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });

    var buton = document.querySelector('.meniu-buton');
    var meniu = document.getElementById('meniu-principal');
    if (!buton || !meniu) return;

    function seteazaMeniu(deschis) {
        buton.setAttribute('aria-expanded', deschis ? 'true' : 'false');
        meniu.classList.toggle('meniu--deschis', deschis);
        buton.querySelector('.vizual-ascuns').textContent = deschis ? 'Închide meniul' : 'Deschide meniul';
    }

    buton.addEventListener('click', function () {
        seteazaMeniu(buton.getAttribute('aria-expanded') !== 'true');
    });

    // Tasta Escape închide meniul și readuce focusul pe buton
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && buton.getAttribute('aria-expanded') === 'true') {
            seteazaMeniu(false);
            buton.focus();
        }
    });
});
