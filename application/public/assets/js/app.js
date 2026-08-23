const toggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('#main-nav');
if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('is-open', !open);
  });
}

const roleInputs = document.querySelectorAll('input[name="role"]');
const studentOnly = document.querySelector('.student-only');
const syncRole = () => {
  if (!studentOnly) return;
  const selected = document.querySelector('input[name="role"]:checked');
  studentOnly.hidden = selected?.value !== 'student';
  const field = studentOnly.querySelector('input');
  if (field) field.required = selected?.value === 'student';
};
roleInputs.forEach((input) => input.addEventListener('change', syncRole));
syncRole();
