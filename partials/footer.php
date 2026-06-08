  </div><!-- /content-area -->
</div><!-- /main-wrap -->
<script>
  // Menu mobile
  const menuBtn = document.getElementById('menuBtn');
  if (menuBtn) {
    window.addEventListener('resize', () => {
      menuBtn.style.display = window.innerWidth <= 900 ? 'block' : 'none';
    });
    menuBtn.style.display = window.innerWidth <= 900 ? 'block' : 'none';
  }
  // Fechar sidebar ao clicar fora (mobile)
  document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth <= 900
        && !sidebar.contains(e.target)
        && !e.target.closest('#menuBtn')) {
      sidebar.classList.remove('open');
    }
  });
  // ── Temporizador de inactividade (15 min = 900s) ──────────────────────────
  (function() {
    const TIMEOUT     = 900;  // segundos — deve coincidir com SESSION_TIMEOUT
    const AVISO_EM    = 120;  // mostrar aviso X segundos antes do fim
    let restantes     = TIMEOUT;
    let intervalo;

    // Cria o toast de aviso (invisível por defeito)
    const toast = document.createElement('div');
    toast.id = 'session-toast';
    toast.style.cssText = `
      display:none; position:fixed; bottom:28px; right:28px; z-index:9999;
      background:#1a2610; color:white; padding:16px 22px; border-radius:14px;
      box-shadow:0 8px 32px rgba(0,0,0,0.25); font-family:'DM Sans',sans-serif;
      font-size:0.9rem; max-width:320px; line-height:1.5;
    `;
    toast.innerHTML = `
      <div style="display:flex;align-items:flex-start;gap:12px">
        <span style="font-size:1.4rem">⏱</span>
        <div>
          <div style="font-weight:700;margin-bottom:3px">Sessão prestes a expirar</div>
          <div id="session-countdown" style="color:rgba(255,255,255,0.75);font-size:0.82rem"></div>
          <button onclick="renovarSessao()" style="
            margin-top:10px; padding:7px 16px; background:#00843D; color:white;
            border:none; border-radius:8px; cursor:pointer; font-size:0.85rem;
            font-family:'DM Sans',sans-serif; font-weight:600;">
            Continuar sessão
          </button>
        </div>
      </div>`;
    document.body.appendChild(toast);

    function tick() {
      restantes--;
      if (restantes <= 0) {
        clearInterval(intervalo);
        window.location.href = 'logout.php?timeout=1';
        return;
      }
      if (restantes <= AVISO_EM) {
        toast.style.display = 'block';
        const m = Math.floor(restantes / 60);
        const s = restantes % 60;
        document.getElementById('session-countdown').textContent =
          'A sessão termina em ' + (m > 0 ? m + 'm ' : '') + s + 's.';
      }
    }

    function resetar() {
      restantes = TIMEOUT;
      toast.style.display = 'none';
    }

    window.renovarSessao = function() {
      fetch('dashboard.php', { method:'HEAD' }).catch(()=>{});
      resetar();
    };

    // Qualquer actividade reinicia o contador
    ['mousemove','keydown','click','scroll','touchstart'].forEach(ev =>
      document.addEventListener(ev, resetar, { passive: true })
    );

    intervalo = setInterval(tick, 1000);
  })();
</script>
</body>
</html>
