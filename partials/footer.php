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
</script>
</body>
</html>
