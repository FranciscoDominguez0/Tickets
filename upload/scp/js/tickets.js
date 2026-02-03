// Inicialización del editor y adjuntos en la vista de ticket
document.addEventListener('DOMContentLoaded', function() {
  // Editor de respuesta del ticket
  if (typeof jQuery !== 'undefined' && jQuery().summernote && document.getElementById('reply_body')) {
    jQuery('#reply_body').summernote({
      height: 260,
      lang: 'es-ES',
      placeholder: 'Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea.',
      toolbar: [
        ['style', ['style', 'paragraph']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontname', ['fontname']],
        ['color', ['color']],
        ['fontsize', ['fontsize']],
        ['insert', ['link', 'picture', 'video', 'table', 'hr']],
        ['view', ['codeview', 'fullscreen']],
        ['para', ['ul', 'ol', 'paragraph']]
      ],
      fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
      fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '24', '36']
    });
  }

  // Adjuntos en respuesta de ticket
  var zone = document.getElementById('attach-zone');
  var input = document.getElementById('attachments');
  var list = document.getElementById('attach-list');
  function updateList() {
    if (!list || !input) return;
    list.innerHTML = '';
    if (input.files && input.files.length) {
      for (var i = 0; i < input.files.length; i++) {
        list.innerHTML += '<span class=\"d-inline-block me-2 mb-1\"><i class=\"bi bi-paperclip\"></i> ' + input.files[i].name + '</span> ';
      }
    }
  }
  if (input && zone && list) {
    input.addEventListener('change', updateList);
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function() { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e) {
      e.preventDefault();
      zone.classList.remove('dragover');
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        updateList();
      }
    });
  }
  var btnReset = document.getElementById('btn-reset');
  if (btnReset) {
    btnReset.addEventListener('click', function() {
      if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
        jQuery('#reply_body').summernote('reset');
      }
      if (input && list) {
        input.value = '';
        list.innerHTML = '';
      }
    });
  }

  // Abrir ticket: enfocar búsqueda de usuario al abrir modal
  var userModal = document.getElementById('modalUserSearch');
  if (userModal) {
    userModal.addEventListener('shown.bs.modal', function() {
      var q = document.getElementById('open_user_query');
      if (q) q.focus();
    });

    // Si venimos de una búsqueda (uq != ''), abrir el modal automáticamente
    if (userModal.dataset.openDefault === '1' && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      var modalInstance = bootstrap.Modal.getOrCreateInstance(userModal);
      modalInstance.show();
    }
  }

  if (typeof jQuery !== 'undefined' && jQuery().summernote && document.getElementById('open_body')) {
    jQuery('#open_body').summernote({
      height: 200,
      lang: 'es-ES',
      placeholder: 'Respuesta inicial para el ticket',
      toolbar: [
        ['style', ['bold', 'italic', 'underline']],
        ['para', ['ul', 'ol']],
        ['insert', ['link']]
      ]
    });
  }
});

