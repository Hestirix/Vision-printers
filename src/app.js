const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#navigation');
toggle?.addEventListener('click', () => {
  const open = toggle.getAttribute('aria-expanded') !== 'true';
  toggle.setAttribute('aria-expanded', String(open)); nav.classList.toggle('open', open);
});
document.addEventListener('keydown', e => { if(e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') { nav.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); toggle.focus(); } });
document.querySelectorAll('form').forEach(form => {
  form.querySelector('button[type="button"]').disabled = false;
  form.querySelector('button[type="button"]').addEventListener('click', () => {
    if (form.reportValidity()) form.querySelector('.form-status').textContent = 'Submission is not active in this local prototype. Your details have not been sent or saved.';
  });
  form.addEventListener('submit', event => { event.preventDefault(); form.querySelector('.form-status').textContent = 'Submission is not active in this local prototype. Your details have not been sent or saved.'; });
});
const service = new URLSearchParams(location.search).get('service');
const select = document.querySelector('#service');
if(select && [...select.options].some(option => option.value === service)) select.value = service;
const upload = document.querySelector('#artwork');
upload?.addEventListener('change', () => {
  const file = upload.files[0];
  const valid = !file || (file.size <= 10 * 1024 * 1024 && /\.(pdf|png|jpe?g)$/i.test(file.name));
  upload.setCustomValidity(valid ? '' : 'Choose a PDF, PNG or JPG file smaller than 10 MB.');
  document.querySelector('#file-status').textContent = !valid ? 'Choose a PDF, PNG or JPG file smaller than 10 MB.' : file ? 'Reference selected locally. It will not be uploaded.' : '';
});
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver(entries => entries.forEach(entry => { if(entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); } }), {threshold:0.08});
  document.querySelectorAll('.reveal').forEach(el => { el.classList.add('animate'); observer.observe(el); });
}
