<?php
// Formulario: Abrir un nuevo ticket (tickets.php?a=open&uid=X)
// Usuario preseleccionado cuando se viene desde users.php?id=X
$preUser = $preSelectedUser ?? null;
$selected_uid = $preUser ? (int)$preUser['id'] : (isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
$selected_dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
$selected_staff_id = isset($_POST['staff_id']) && is_numeric($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
$selected_topic_id = isset($_POST['topic_id']) && is_numeric($_POST['topic_id']) ? (int) $_POST['topic_id'] : 0;
$open_departments = $open_departments ?? [];
$open_priorities = $open_priorities ?? [];
$open_staff = $open_staff ?? [];
$open_user_query = $open_user_query ?? '';
$open_user_results = $open_user_results ?? [];
$open_hasTopics = $open_hasTopics ?? false;
$open_topics = $open_topics ?? [];
?>

<div class="ticket-open-wrap">
    <a href="users.php<?php echo $selected_uid ? '?id=' . $selected_uid : ''; ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
    <h1>Abrir un nuevo Ticket</h1>

    <?php if (!empty($open_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo implode(' ', array_map('htmlspecialchars', $open_errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="tickets.php?a=open<?php echo $open_uid ? '&uid=' . $open_uid : ''; ?>" id="form-open-ticket">
        <input type="hidden" name="do" value="open">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
        <input type="hidden" name="user_id" value="<?php echo $selected_uid ? (int)$selected_uid : ''; ?>">

        <!-- Usuarios y colaboradores -->
        <div class="ticket-open-section">
            <div class="section-title">Usuarios y colaboradores</div>
            <div class="mb-3">
                <label class="form-label">Usuario: <span class="required">*</span></label>
                <div class="ticket-open-user-display">
                    <span class="user-text" id="open_user_display">
                        <?php
                        if ($preUser) {
                            echo html(trim($preUser['firstname'] . ' ' . $preUser['lastname']) . ' <' . $preUser['email'] . '>');
                        }
                        if (!$selected_uid) echo '<span class="text-muted">Seleccione un usuario</span>';
                        ?>
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-change" id="btn_change_user" data-bs-toggle="modal" data-bs-target="#modalUserSearch">Cambiar</button>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Aviso de Ticket:</label>
                <select class="form-select form-control" disabled style="max-width: 280px;">
                    <option>Alertar a todos</option>
                </select>
            </div>
        </div>

        <!-- Información y opciones del Ticket -->
        <div class="ticket-open-section">
            <div class="section-title">Información y opciones del Ticket</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fuente del Ticket:</label>
                    <select class="form-select" name="source" id="open_source">
                        <option value="web">Web</option>
                        <option value="phone">Teléfono</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Departamento: <span class="required">*</span></label>
                    <select name="dept_id" class="form-select" id="open_dept_id" required>
                        <option value="" <?php echo $selected_dept_id <= 0 ? 'selected' : ''; ?>>— Seleccionar departamento —</option>
                        <?php foreach ($open_departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)$d['id'] === $selected_dept_id ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tema:</label>
                    <select name="topic_id" class="form-select" id="open_topic_id">
                        <option value="0" <?php echo $selected_topic_id === 0 ? 'selected' : ''; ?>>— General —</option>
                        <?php if ($open_hasTopics && !empty($open_topics)): ?>
                            <?php foreach ($open_topics as $tp): ?>
                                <option value="<?php echo (int)$tp['id']; ?>" data-dept-id="<?php echo (int)($tp['dept_id'] ?? 0); ?>" <?php echo (int)$tp['id'] === $selected_topic_id ? 'selected' : ''; ?>><?php echo html($tp['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prioridad:</label>
                    <select name="priority_id" class="form-select">
                        <?php foreach ($open_priorities as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === 2 ? 'selected' : ''; ?>><?php echo html($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Asignar a:</label>
                    <select name="staff_id" class="form-select" id="open_staff_id" <?php echo $selected_dept_id > 0 ? '' : 'disabled'; ?>>
                        <option value="0">— Sin asignar —</option>
                        <?php foreach ($open_staff as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" data-dept-id="<?php echo (int)($s['dept_id'] ?? 0); ?>" <?php echo (int)$s['id'] === $selected_staff_id ? 'selected' : ''; ?>><?php echo html(trim($s['firstname'] . ' ' . $s['lastname'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Asunto: <span class="required">*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="Asunto del ticket" required value="<?php echo html($_POST['subject'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Respuesta inicial -->
        <div class="ticket-open-section">
            <div class="section-title">Respuesta</div>
            <p class="text-muted small mb-2">Respuesta inicial para el ticket (opcional).</p>
            <div class="mb-3">
                <textarea name="body" id="open_body" class="form-control" placeholder="Respuesta inicial para el ticket" rows="6"></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-submit"><i class="bi bi-plus-lg me-1"></i> Abrir Ticket</button>
            <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
  (function () {
    var deptSel = document.getElementById('open_dept_id');
    var staffSel = document.getElementById('open_staff_id');
    var topicSel = document.getElementById('open_topic_id');
    if (!deptSel || !staffSel) return;

    var applyStaffFilter = function () {
      var deptId = (deptSel.value || '').toString();
      var hasDept = deptId !== '' && !isNaN(parseInt(deptId, 10));
      staffSel.disabled = !hasDept;

      var opts = staffSel.querySelectorAll('option');
      opts.forEach(function (opt) {
        if (!opt || opt.value === '0') return;
        var sdept = (opt.getAttribute('data-dept-id') || '').toString();
        opt.hidden = !hasDept || sdept !== deptId;
      });

      if (!hasDept) {
        staffSel.value = '0';
        return;
      }

      var selected = staffSel.options[staffSel.selectedIndex];
      if (selected && selected.hidden) staffSel.value = '0';
    };

    deptSel.addEventListener('change', applyStaffFilter);

    if (topicSel) {
      topicSel.addEventListener('change', function () {
        var opt = topicSel.options[topicSel.selectedIndex];
        if (!opt) return;
        var tdept = (opt.getAttribute('data-dept-id') || '').toString();
        if (tdept !== '' && tdept !== '0') {
          deptSel.value = tdept;
          applyStaffFilter();
        }
      });
    }

    applyStaffFilter();
  })();
</script>

<!-- Modal: Buscar usuario (sin listar todos) -->
<div class="modal fade" id="modalUserSearch" tabindex="-1" aria-labelledby="modalUserSearchLabel" aria-hidden="true" data-open-default="<?php echo $open_user_query !== '' ? '1' : '0'; ?>">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalUserSearchLabel">Buscar o seleccionar un usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3">Buscar usuarios por email, teléfono o nombre.</div>

        <form method="get" action="tickets.php" class="mb-3">
          <input type="hidden" name="a" value="open">
          <div class="input-group">
            <input type="text" class="form-control" name="uq" id="open_user_query" placeholder="Buscar por email, teléfono o nombre" value="<?php echo html($open_user_query); ?>">
            <button class="btn btn-primary" type="submit">Buscar</button>
          </div>
        </form>

        <?php if ($open_user_query !== '' && empty($open_user_results)): ?>
          <div class="text-muted">No se encontraron usuarios con ese criterio.</div>
        <?php endif; ?>

        <?php if (!empty($open_user_results)): ?>
          <div class="list-group">
            <?php foreach ($open_user_results as $u): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong><?php echo html(trim($u['firstname'] . ' ' . $u['lastname'])); ?></strong>
                  &lt;<?php echo html($u['email']); ?>&gt;
                  <?php if (!empty($u['phone'])): ?>
                    <span class="text-muted ms-2"><?php echo html($u['phone']); ?></span>
                  <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="tickets.php?a=open&uid=<?php echo (int)$u['id']; ?>">Seleccionar</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
