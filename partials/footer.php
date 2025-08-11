  </main>
  <footer class="border-t border-slate-700 bg-slate-800">
    <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-slate-400">
      &copy; <?= date('Y') ?> - Lancta
    </div>
  </footer>
  <script>
  document.addEventListener('click', function(e){
    const t = e.target.closest('[data-username]');
    if (!t) return;
    const name = t.getAttribute('data-username');
    const field = document.querySelector('.js-mention-target');
    if (field) { field.value = (field.value ? field.value + ' ' : '') + '@' + name + ' '; field.focus(); }
  });
  function setupMentions(el){
    let box = document.createElement('div');
    box.className = 'absolute z-20 mt-1 bg-slate-800 border border-slate-700 rounded w-64 hidden';
    el.parentNode.style.position = 'relative';
    el.parentNode.appendChild(box);
    el.addEventListener('keyup', async (e) => {
      const caret = el.selectionStart;
      const text = el.value.slice(0, caret);
      const m = text.match(/@([A-Za-z0-9_]{1,32})$/);
      if (!m) { box.style.display='none'; return; }
      const q = m[1];
      try {
        const r = await fetch('users_search.php?q=' + encodeURIComponent(q));
        const items = await r.json();
        box.innerHTML = items.map(u => '<div class="px-2 py-1 hover:bg-slate-700 cursor-pointer" data-name="'+u+'">@'+u+'</div>').join('');
        box.style.display = items.length ? 'block' : 'none';
      } catch(e){ box.style.display='none'; }
    });
    box.addEventListener('click', (ev)=>{
      const n = ev.target.getAttribute('data-name');
      if (!n) return;
      const caret = el.selectionStart;
      const text = el.value.slice(0, caret);
      const m = text.match(/@([A-Za-z0-9_]{1,32})$/);
      if (m) {
        el.value = text.slice(0, -m[1].length) + n + el.value.slice(caret);
      } else {
        el.value += '@' + n + ' ';
      }
      el.focus();
      box.style.display='none';
    });
  }
  document.querySelectorAll('.js-mention-target').forEach(setupMentions);
  </script>
</body>
</html>
