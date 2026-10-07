// P08.08 — progressive-enhancement photo uploader: local preview, upload
// progress and a clear error state. Without JavaScript the plain form still
// submits (the server owns validation and the draft/publish invariant).
export function initMediaUploader(form) {
  if (!(form instanceof HTMLFormElement)) return;
  const scope = form.parentElement ?? form;
  const fileInput = form.querySelector('input[type="file"]');
  const preview = scope.querySelector('[data-upload-preview]');
  const previewImg = scope.querySelector('[data-upload-preview-img]');
  const previewName = scope.querySelector('[data-upload-name]');
  const progress = scope.querySelector('[data-upload-progress]');
  const status = scope.querySelector('[data-upload-status]');
  const error = scope.querySelector('[data-upload-error]');
  const button = form.querySelector('[type="submit"]');
  if (!fileInput || !preview || !error) return;

  const allowed = /^image\/(jpeg|png|webp)$/;
  const setStatus = (text) => { if (status) status.textContent = text; };
  const clearError = () => { error.textContent = ''; error.hidden = true; };
  const showError = (text) => { error.textContent = text; error.hidden = false; setStatus(text); };

  fileInput.addEventListener('change', () => {
    clearError();
    const file = fileInput.files?.[0];
    if (!file) { preview.hidden = true; return; }
    if (!allowed.test(file.type)) {
      preview.hidden = true;
      showError('Choose a JPEG, PNG or WebP photo.');
      return;
    }
    if (previewImg) previewImg.src = URL.createObjectURL(file);
    if (previewName) previewName.textContent = file.name;
    preview.hidden = false;
    setStatus(file.name + ' ready to upload.');
  });

  form.addEventListener('submit', (event) => {
    if (typeof window.FormData !== 'function' || typeof window.XMLHttpRequest !== 'function') return;
    const file = fileInput.files?.[0];
    if (!file) return; // let native validation handle the empty case
    event.preventDefault();
    clearError();
    if (progress) { progress.hidden = false; progress.value = 0; }
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', (progressEvent) => {
      if (!progress || !progressEvent.lengthComputable) return;
      progress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100);
      setStatus('Uploading… ' + progress.value + '%');
    });
    xhr.addEventListener('load', () => {
      const url = xhr.responseURL || '';
      // Success redirects to the new media item; a validation failure redirects
      // back to the index, so distinguish on the final URL, not the status.
      if (xhr.status >= 200 && xhr.status < 400 && /\/admin\/media\/[0-9a-f-]{36}$/i.test(url)) {
        setStatus('Uploaded. Opening…');
        window.location.assign(url);
        return;
      }
      if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
      showError(errorMessage(xhr));
    });
    xhr.addEventListener('error', () => {
      if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
      showError('Upload failed. Check your connection and try again.');
    });
    xhr.send(new FormData(form));
  });
}

function errorMessage(xhr) {
  try {
    const payload = JSON.parse(xhr.responseText);
    const message = payload?.error?.message ?? payload?.message;
    if (typeof message === 'string' && message !== '') return message;
  } catch { /* HTML response (e.g. a redirect back to the index) */ }
  const fromHtml = htmlError(xhr.responseText);
  if (fromHtml !== '') return fromHtml;
  if (xhr.status === 413) return 'That photo is too large.';
  if (xhr.status === 415) return 'Upload must be a JPEG, PNG or WebP image.';
  return 'Upload failed. Check the photo and try again.';
}

function htmlError(html) {
  if (typeof html !== 'string' || html === '') return '';
  try {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const item = doc.querySelector('[role="alert"] ul li') ?? doc.querySelector('[role="alert"] strong');
    return (item?.textContent ?? '').trim();
  } catch {
    return '';
  }
}
