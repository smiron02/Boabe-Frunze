/**
 * Meniul de navigare pe telefon („hamburger”).
 * Butonul deschide/închide meniul, iar aria-expanded spune
 * cititoarelor de ecran dacă meniul este deschis.
 */
document.addEventListener('DOMContentLoaded', function () {
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
