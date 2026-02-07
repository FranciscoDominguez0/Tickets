<?php
// Procesamiento de acciones
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['do'] ?? '';
    
    switch ($action) {
        case 'create':
            $name = $_POST['topic'] ?? '';
            $description = $_POST['description'] ?? '';
            $deptId = $_POST['dept_id'] ?? null;
            $isActive = isset($_POST['isactive']) ? 1 : 0;
            $isPublic = isset($_POST['ispublic']) ? 1 : 0;
            $priority = $_POST['priority'] ?? 2;
            
            if (empty($name)) {
                $error = 'El nombre del tema es requerido';
            } else {
                $sql = "INSERT INTO help_topics (name, description, dept_id, is_active, is_public, priority, created) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
                execute($sql, [$name, $description, $deptId, $isActive, $isPublic, $priority]);
                $msg = 'Tema de ayuda creado exitosamente';
            }
            break;
            
        case 'update':
            $topicId = $_POST['topic_id'] ?? null;
            $name = $_POST['topic'] ?? '';
            $description = $_POST['description'] ?? '';
            $deptId = $_POST['dept_id'] ?? null;
            $isActive = isset($_POST['isactive']) ? 1 : 0;
            $isPublic = isset($_POST['ispublic']) ? 1 : 0;
            $priority = $_POST['priority'] ?? 2;
            
            if (empty($name)) {
                $error = 'El nombre del tema es requerido';
            } else {
                $sql = "UPDATE help_topics SET name = ?, description = ?, dept_id = ?, is_active = ?, 
                        is_public = ?, priority = ? WHERE topic_id = ?";
                execute($sql, [$name, $description, $deptId, $isActive, $isPublic, $priority, $topicId]);
                $msg = 'Tema de ayuda actualizado exitosamente';
            }
            break;
            
        case 'mass_process':
            $ids = $_POST['ids'] ?? [];
            $massAction = $_POST['a'] ?? '';
            
            if (empty($ids)) {
                $error = 'Debe seleccionar al menos un tema';
            } else {
                switch ($massAction) {
                    case 'enable':
                        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                        $sql = "UPDATE help_topics SET is_active = 1 WHERE topic_id IN ($placeholders)";
                        execute($sql, $ids);
                        $msg = count($ids) . ' temas habilitados exitosamente';
                        break;
                        
                    case 'disable':
                        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                        $sql = "UPDATE help_topics SET is_active = 0 WHERE topic_id IN ($placeholders)";
                        execute($sql, $ids);
                        $msg = count($ids) . ' temas deshabilitados exitosamente';
                        break;
                        
                    case 'delete':
                        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                        $sql = "DELETE FROM help_topics WHERE topic_id IN ($placeholders)";
                        execute($sql, $ids);
                        $msg = count($ids) . ' temas eliminados exitosamente';
                        break;
                }
            }
            break;
    }
}

// Obtener temas
$topics = fetchAll("SELECT ht.*, d.name as dept_name 
                  FROM help_topics ht 
                  LEFT JOIN department d ON ht.dept_id = d.dept_id 
                  ORDER BY ht.name");

$departments = fetchAll("SELECT * FROM department ORDER BY name");

// Editar tema específico
$editingTopic = null;
if (isset($_GET['id'])) {
    $editingTopic = fetchOne("SELECT * FROM help_topics WHERE topic_id = ?", [$_GET['id']]);
}
?>

<div class="page-header">
    <h1>Temas de Ayuda</h1>
    <p>Administrar temas de ayuda y categorías del sistema</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <i class="bi bi-check-circle"></i> <?php echo $msg; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> 
                    Temas de Ayuda 
                    <span class="badge bg-secondary"><?php echo count($topics); ?></span>
                </h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#topicModal">
                    <i class="bi bi-plus-circle"></i> Añadir nuevo tema de ayuda
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (count($topics) > 0): ?>
                <form method="post" action="settings.php?t=helptopics">
                    <input type="hidden" name="do" value="mass_process">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Tema de Ayuda</th>
                                    <th>Estado</th>
                                    <th>Tipo</th>
                                    <th>Prioridad</th>
                                    <th>Departamento</th>
                                    <th>Última Actualización</th>
                                    <th width="80">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topics as $topic): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?php echo $topic['topic_id']; ?>" 
                                               class="form-check-input topic-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo html($topic['name']); ?></strong>
                                        <?php if ($topic['description']): ?>
                                        <br><small class="text-muted"><?php echo html($topic['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($topic['is_active']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($topic['is_public']): ?>
                                            <span class="badge bg-info">Público</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Interno</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorities = [1 => 'Alta', 2 => 'Normal', 3 => 'Baja'];
                                        $priorityColors = [1 => 'danger', 2 => 'primary', 3 => 'secondary'];
                                        $color = $priorityColors[$topic['priority']] ?? 'secondary';
                                        $text = $priorities[$topic['priority']] ?? 'Normal';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo $text; ?></span>
                                    </td>
                                    <td><?php echo html($topic['dept_name'] ?: 'Sin asignar'); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($topic['created'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="settings.php?t=helptopics&id=<?php echo $topic['topic_id']; ?>" 
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" title="Más">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($topic['is_active']): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="massAction('disable', [<?php echo $topic['topic_id']; ?>])">
                                                            <i class="bi bi-pause-circle"></i> Deshabilitar
                                                        </a>
                                                    </li>
                                                    <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="massAction('enable', [<?php echo $topic['topic_id']; ?>])">
                                                            <i class="bi bi-play-circle"></i> Habilitar
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="massAction('delete', [<?php echo $topic['topic_id']; ?>])">
                                                            <i class="bi bi-trash"></i> Eliminar
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($topics) > 0): ?>
                    <div class="card-footer bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="btn-group">
                                    <button type="submit" name="a" value="enable" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-check-circle"></i> Habilitar
                                    </button>
                                    <button type="submit" name="a" value="disable" class="btn btn-outline-warning btn-sm">
                                        <i class="bi bi-pause-circle"></i> Deshabilitar
                                    </button>
                                    <button type="submit" name="a" value="delete" class="btn btn-outline-danger btn-sm" 
                                            onclick="return confirm('¿Está seguro de eliminar los temas seleccionados?')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    Seleccionados: <span id="selectedCount">0</span> de <?php echo count($topics); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No hay temas de ayuda</h5>
                    <p class="text-muted">Cree su primer tema de ayuda para comenzar</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#topicModal">
                        <i class="bi bi-plus-circle"></i> Crear primer tema
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para crear/editar tema -->
<div class="modal fade" id="topicModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="settings.php?t=helptopics">
                <input type="hidden" name="do" value="<?php echo $editingTopic ? 'update' : 'create'; ?>">
                <?php if ($editingTopic): ?>
                <input type="hidden" name="topic_id" value="<?php echo $editingTopic['topic_id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-<?php echo $editingTopic ? 'pencil' : 'plus-circle'; ?>"></i>
                        <?php echo $editingTopic ? 'Editar Tema de Ayuda' : 'Añadir Nuevo Tema de Ayuda'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="topic" class="form-label">Nombre del Tema <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="topic" name="topic" required
                                       value="<?php echo $editingTopic ? html($editingTopic['name']) : ''; ?>"
                                       placeholder="Ej: Problemas técnicos">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dept_id" class="form-label">Departamento</label>
                                <select class="form-select" id="dept_id" name="dept_id">
                                    <option value="">Seleccionar departamento</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>" 
                                            <?php echo ($editingTopic && $editingTopic['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                                        <?php echo html($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Descripción detallada del tema de ayuda"><?php echo $editingTopic ? html($editingTopic['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Prioridad</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="1" <?php echo ($editingTopic && $editingTopic['priority'] == 1) ? 'selected' : ''; ?>>Alta</option>
                                    <option value="2" <?php echo ($editingTopic && $editingTopic['priority'] == 2) ? 'selected' : ''; ?>>Normal</option>
                                    <option value="3" <?php echo ($editingTopic && $editingTopic['priority'] == 3) ? 'selected' : ''; ?>>Baja</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="isactive" name="isactive" 
                                       <?php echo ($editingTopic && $editingTopic['is_active']) || !$editingTopic ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isactive">
                                    Activo
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="ispublic" name="ispublic" 
                                       <?php echo ($editingTopic && $editingTopic['is_public']) || !$editingTopic ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ispublic">
                                    Público
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $editingTopic ? 'check' : 'plus'; ?>"></i>
                        <?php echo $editingTopic ? 'Actualizar' : 'Crear'; ?> Tema
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Seleccionar/deseleccionar todos
    $('#selectAll').change(function() {
        $('.topic-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
    });
    
    // Actualizar contador de seleccionados
    $('.topic-checkbox').change(function() {
        updateSelectedCount();
    });
    
    function updateSelectedCount() {
        var count = $('.topic-checkbox:checked').length;
        $('#selectedCount').text(count);
    }
    
    // Acciones masivas individuales
    window.massAction = function(action, ids) {
        if (action === 'delete' && !confirm('¿Está seguro de eliminar este tema?')) {
            return false;
        }
        
        var form = $('<form>', {
            method: 'post',
            action: 'settings.php?t=helptopics'
        });
        
        form.append($('<input>', {type: 'hidden', name: 'do', value: 'mass_process'}));
        form.append($('<input>', {type: 'hidden', name: 'a', value: action}));
        
        $.each(ids, function(index, id) {
            form.append($('<input>', {type: 'hidden', name: 'ids[]', value: id}));
        });
        
        form.appendTo('body').submit();
    };
});
</script>
