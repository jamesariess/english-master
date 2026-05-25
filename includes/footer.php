  </main><!-- end main-content -->
</div><!-- end app-layout -->

<script>
// Mobile sidebar close on nav click
document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.remove('open');
  });
});
// Click outside sidebar to close
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.querySelector('.menu-toggle');
  if (sidebar && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
    sidebar.classList.remove('open');
  }
});
</script>
</body>
</html>

<script>
/* ── Auto data-label for mobile tables ── */
document.querySelectorAll('.data-table').forEach(table => {
  const headers = [...table.querySelectorAll('th')].map(th => th.textContent.trim());
  table.querySelectorAll('tbody tr').forEach(row => {
    [...row.querySelectorAll('td')].forEach((td, i) => {
      if (!td.dataset.label && headers[i]) td.dataset.label = headers[i];
    });
  });
});

/* ── Prevent body scroll when sidebar open ── */
const _sidebar = document.getElementById('sidebar');
if (_sidebar) {
  new MutationObserver(() => {
    document.body.style.overflow = _sidebar.classList.contains('open') ? 'hidden' : '';
  }).observe(_sidebar, { attributes: true, attributeFilter: ['class'] });
}
</script>

<script>
/* ── Sidebar open/close/swipe ── */
function openSidebar() {
  document.getElementById('sidebar')?.classList.add('open');
  document.getElementById('sidebarOverlay')?.classList.add('visible');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('visible');
  document.body.style.overflow = '';
}
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  if (s?.classList.contains('open')) closeSidebar(); else openSidebar();
}
/* Swipe left to close, right-edge swipe to open */
let _tx = 0;
document.addEventListener('touchstart', e => { _tx = e.touches[0].clientX; }, { passive: true });
document.addEventListener('touchend', e => {
  const dx = e.changedTouches[0].clientX - _tx;
  if (dx < -55) closeSidebar();
  if (dx > 55 && _tx < 28) openSidebar();
}, { passive: true });
/* ESC to close */
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
/* Close on nav click (mobile) */
document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});
</script>
