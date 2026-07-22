<style>
#cookie-consent-banner {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    z-index: 999;
    background: var(--prussian, #023047);
    color: rgba(255,255,255,.85);
    padding: 18px 24px;
    display: none;
    align-items: center;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 -4px 24px rgba(0,0,0,.25);
    font-family: 'DM Sans', sans-serif;
    animation: cookieBannerIn .4s ease both;
}
#cookie-consent-banner.visible { display: flex; }
@keyframes cookieBannerIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
#cookie-consent-banner p { margin: 0; font-size: 13px; max-width: 640px; line-height: 1.6; }
#cookie-consent-banner a { color: var(--pacific, #219EBC); text-decoration: underline; }
#cookie-consent-banner .cc-actions { display: flex; gap: 10px; flex-shrink: 0; }
#cookie-consent-banner button {
    font-family: inherit; font-size: 13px; font-weight: 700; border: none; border-radius: 8px;
    padding: 10px 20px; cursor: pointer; transition: .15s;
}
#cookie-consent-banner .cc-accept { background: var(--yellow, #FFB703); color: var(--prussian, #023047); }
#cookie-consent-banner .cc-accept:hover { background: #e6a600; }
#cookie-consent-banner .cc-reject { background: transparent; color: rgba(255,255,255,.6); border: 1.5px solid rgba(255,255,255,.25); }
#cookie-consent-banner .cc-reject:hover { color: #fff; border-color: rgba(255,255,255,.5); }
@media (max-width: 640px) {
    #cookie-consent-banner { justify-content: flex-start; text-align: left; }
    #cookie-consent-banner .cc-actions { width: 100%; }
    #cookie-consent-banner button { flex: 1; }
}
</style>

<div id="cookie-consent-banner" role="dialog" aria-live="polite" aria-label="Aviso de cookies">
    <p>
        <i class="fa-solid fa-cookie-bite" aria-hidden="true"></i>
        Usamos cookies essenciais para o funcionamento da plataforma e, com seu consentimento, cookies funcionais.
        Saiba mais na nossa <a href="/cookies.php">Política de Cookies</a>.
    </p>
    <div class="cc-actions">
        <button type="button" class="cc-reject" onclick="cookieConsentSet(false)">Recusar</button>
        <button type="button" class="cc-accept" onclick="cookieConsentSet(true)">Aceitar</button>
    </div>
</div>

<script>
(function () {
    function getCookie(name) {
        return document.cookie.split('; ').find(function (r) { return r.startsWith(name + '='); });
    }
    window.cookieConsentSet = function (accepted) {
        var oneYear = 60 * 60 * 24 * 365;
        document.cookie = 'cookie_consent=' + (accepted ? '1' : '0') + '; max-age=' + oneYear + '; path=/; SameSite=Lax';
        document.getElementById('cookie-consent-banner').classList.remove('visible');
    };
    if (!getCookie('cookie_consent')) {
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('cookie-consent-banner').classList.add('visible');
        });
    }
})();
</script>
