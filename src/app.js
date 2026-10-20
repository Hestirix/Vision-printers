const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#navigation');
toggle?.addEventListener('click', () => {
  const open = toggle.getAttribute('aria-expanded') !== 'true';
  toggle.setAttribute('aria-expanded', String(open)); nav.classList.toggle('open', open);
});
document.addEventListener('keydown', e => { if(e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') { nav.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); toggle.focus(); } });
document.querySelectorAll('form[data-inquiry]').forEach(async form => {
  const button = form.querySelector('button[type="button"]');
  const status = form.querySelector('.form-status');
  const notice = form.querySelector('.form-notice');
  const help = form.querySelector('.form-help');
  let session = null, busy = false, completed = false;
  const requestId = crypto.randomUUID();
  form.addEventListener('input', e => {
    if (e.target.dataset.serverError) {
      e.target.setCustomValidity(''); e.target.removeAttribute('aria-invalid'); delete e.target.dataset.serverError;
    }
  });
  form.addEventListener('submit', e => e.preventDefault());
  try {
    const response = await fetch('api/session.php', {credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(10000)});
    const result = await response.json();
    if (!response.ok || result.ok !== true || !['mock','smtp'].includes(result.mode) || !/^[a-f0-9]{64}$/.test(result.csrf)) throw new Error('Unavailable');
    session = result;
    notice.querySelector('strong').textContent = result.mode === 'mock' ? 'Local test mode · no real emails are sent' : 'Send your inquiry to Vision Printers';
    notice.querySelector('p').textContent = result.mode === 'mock' ? 'The local backend validates your inquiry and prepares emails in memory. Nothing is delivered or permanently stored.' : 'Your details and optional artwork will be sent to sales@visionprintersmw.com to respond to your inquiry. Avoid including sensitive information.';
    button.textContent = result.mode === 'mock' ? 'Test Inquiry — No Email' : 'Send Inquiry';
    help.textContent = result.mode === 'mock' ? 'Test acknowledgment emails are also prepared without delivery.' : 'We will confirm the mail server’s response after you submit.';
    button.disabled = false;
  } catch {
    help.textContent = 'Submissions are unavailable. Use the listed email or telephone, or start the documented PHP preview.';
  }
  button.addEventListener('click', async () => {
    if (!session || busy || completed || !form.reportValidity()) return;
    busy = true; button.disabled = true; status.textContent = session.mode === 'mock' ? 'Testing your inquiry locally…' : 'Sending your inquiry…';
    const body = new FormData(form); body.set('csrf',session.csrf); body.set('request_id',requestId);
    try {
      const response = await fetch(`api/${form.dataset.inquiry}.php`, {method:'POST',credentials:'same-origin',body,signal:AbortSignal.timeout(60000)});
      const result = await response.json();
      if (!response.ok || result.ok !== true) {
        for (const [name,message] of Object.entries(result.errors || {})) {
          const field = form.elements.namedItem(name);
          if (field && typeof field.setCustomValidity === 'function' && typeof message === 'string') {
            field.setCustomValidity(message); field.setAttribute('aria-invalid','true'); field.dataset.serverError = 'true';
          }
        }
        status.textContent = typeof result.message === 'string' ? result.message : 'We could not confirm acceptance. Please contact us directly.';
        form.reportValidity(); return;
      }
      if (result.mode !== session.mode || !/^[a-f0-9]{16}$/.test(result.reference) || result.status !== (session.mode === 'mock' ? 'mocked' : 'accepted')) throw new Error('Unverified response');
      status.textContent = session.mode === 'mock'
        ? `Local test passed. No emails were sent. Reference: ${result.reference}.`
        : `The mail server accepted your inquiry. Reference: ${result.reference}. This does not guarantee inbox delivery.`;
      if (session.mode === 'smtp' && result.acknowledgment === 'failed') status.textContent += ' Your acknowledgment could not be sent; please keep this reference.';
      completed = true; button.textContent = session.mode === 'mock' ? 'Test complete' : 'Inquiry accepted';
    } catch {
      status.textContent = 'We could not verify the response. Your inquiry may have been accepted. Retry only this same form or contact us directly; do not create a duplicate inquiry.';
    } finally { busy = false; button.disabled = completed; }
  });
});
const service = new URLSearchParams(location.search).get('service');
const select = document.querySelector('#service');
if(select && [...select.options].some(option => option.value === service)) select.value = service;
const upload = document.querySelector('#artwork');
upload?.addEventListener('change', () => {
  const file = upload.files[0];
  const valid = !file || (file.size <= 10 * 1024 * 1024 && /\.(pdf|png|jpe?g)$/i.test(file.name));
  upload.setCustomValidity(valid ? '' : 'Choose a PDF, PNG or JPG file smaller than 10 MB.');
  document.querySelector('#file-status').textContent = !valid ? 'Choose a PDF, PNG or JPG file smaller than 10 MB.' : file ? 'Reference selected. The server will validate its contents when you submit.' : '';
});
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver(entries => entries.forEach(entry => { if(entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); } }), {threshold:0.08});
  document.querySelectorAll('.reveal').forEach(el => { el.classList.add('animate'); observer.observe(el); });
}
