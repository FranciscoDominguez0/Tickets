<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'helptopics';

// Lógica para controlar el estado inicial del sidebar (similar al panel de administrador)
$collapseSidebarMenu = false;
if (!isset($_SESSION['agent_sidebar_menu_seen'])) {
    $_SESSION['agent_sidebar_menu_seen'] = 1;
    $collapseSidebarMenu = true;
}

// Variables para mensajes (usar mensajes flash en sesión para PRG)
$msg = '';
$error = '';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Procesamiento de acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['do'] ?? '';
    
    switch ($action) {
        case 'create':
            $name = trim($_POST['topic'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
            $isActive = isset($_POST['isactive']) ? 1 : 0;
            
            if (empty($name)) {
                $error = 'El nombre del tema es requerido';
            } else {
                global $mysqli;
                $sql = "INSERT INTO help_topics (name, description, dept_id, is_active, created) 
                        VALUES (?, ?, ?, ?, NOW())";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ssii', $name, $description, $deptId, $isActive);
                if ($stmt->execute()) {
                    $msg = 'Tema de ayuda creado exitosamente';
                } else {
                    $error = 'Error al crear el tema de ayuda';
                }
            }
            break;
            
        case 'update':
            $topicId = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['topic'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
            $isActive = isset($_POST['isactive']) ? 1 : 0;
            
            if (empty($name)) {
                $error = 'El nombre del tema es requerido';
            } elseif ($topicId <= 0) {
                $error = 'ID de tema inválido';
            } else {
                global $mysqli;
                $sql = "UPDATE help_topics SET name = ?, description = ?, dept_id = ?, is_active = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ssiii', $name, $description, $deptId, $isActive, $topicId);
                if ($stmt->execute()) {
                    $msg = 'Tema de ayuda actualizado exitosamente';
                } else {
                    $error = 'Error al actualizar el tema de ayuda';
                }
            }
            break;
            
        case 'mass_process':
            $ids = $_POST['ids'] ?? [];
            $massAction = $_POST['a'] ?? '';
            
            if (empty($ids) || !is_array($ids)) {
                $error = 'Debe seleccionar al menos un tema';
            } else {
                $ids = array_map('intval', $ids);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $types = str_repeat('i', count($ids));
                
                global $mysqli;
                switch ($massAction) {
                    case 'enable':
                        $sql = "UPDATE help_topics SET is_active = 1 WHERE id IN ($placeholders)";
                        $stmt = $mysqli->prepare($sql);
                        $stmt->bind_param($types, ...$ids);
                        if ($stmt->execute()) {
                            $msg = count($ids) . ' temas habilitados exitosamente';
                        } else {
                            $error = 'Error al habilitar temas';
                        }
                        break;
                        
                    case 'disable':
                        $sql = "UPDATE help_topics SET is_active = 0 WHERE id IN ($placeholders)";
                        $stmt = $mysqli->prepare($sql);
                        $stmt->bind_param($types, ...$ids);
                        if ($stmt->execute()) {
                            $msg = count($ids) . ' temas deshabilitados exitosamente';
                        } else {
                            $error = 'Error al deshabilitar temas';
                        }
                        break;
                        
                    case 'delete':
                        // Verificar que no se eliminen todos los temas activos
                        $activeCount = 0;
                        $countSql = "SELECT COUNT(*) as count FROM help_topics WHERE is_active = 1";
                        $countResult = $mysqli->query($countSql);
                        if ($countResult) {
                            $activeCount = (int)$countResult->fetch_assoc()['count'];
                        }
                        
                        $selectedActiveCount = 0;
                        $selectedActiveSql = "SELECT COUNT(*) as count FROM help_topics WHERE id IN ($placeholders) AND is_active = 1";
                        $selectedResult = $mysqli->prepare($selectedActiveSql);
                        $selectedResult->bind_param($types, ...$ids);
                        $selectedResult->execute();
                        if ($selectedResult) {
                            $selectedActiveCount = (int)$selectedResult->get_result()->fetch_assoc()['count'];
                        }
                        
                        if ($selectedActiveCount >= $activeCount) {
                            $error = 'Debe mantener al menos un tema activo';
                        } else {
                            $sql = "DELETE FROM help_topics WHERE id IN ($placeholders)";
                            $stmt = $mysqli->prepare($sql);
                            $stmt->bind_param($types, ...$ids);
                            if ($stmt->execute()) {
                                $msg = count($ids) . ' temas eliminados exitosamente';
                            } else {
                                $error = 'Error al eliminar temas';
                            }
                        }
                        break;
                        
                    default:
                        $error = 'Acción no reconocida';
                }
            }
            break;
    }
    // Al terminar el POST, guardar mensaje en sesión y redirigir (PRG)
    if (!headers_sent()) {
        if (!empty($msg)) {
            $_SESSION['flash_msg'] = $msg;
        }
        if (!empty($error)) {
            $_SESSION['flash_error'] = $error;
        }
        header('Location: helptopics.php');
        exit;
    }
}

// Obtener temas de ayuda
global $mysqli;
$sql = "SELECT ht.*, d.name as dept_name 
          FROM help_topics ht 
          LEFT JOIN departments d ON ht.dept_id = d.id 
          ORDER BY ht.is_active DESC, ht.name ASC";
$result = $mysqli->query($sql);
$topics = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
}

// Obtener departamentos
$deptResult = $mysqli->query("SELECT * FROM departments ORDER BY name");
$departments = [];
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Editar tema específico
$editingTopic = null;
if (isset($_GET['id'])) {
    $topicId = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT * FROM help_topics WHERE id = ?");
    $stmt->bind_param('i', $topicId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editingTopic = $result ? $result->fetch_assoc() : null;
}

// Capturar contenido HTML
ob_start();
?>

<!-- Hero Section con estilo similar a otras subopciones -->
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-question-circle"></i></span>
            <div>
                <h1>Temas de Ayuda</h1>
                <p>Administrar categorías y temas de soporte del sistema</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success"><?php echo count(array_filter($topics, fn($t) => $t['is_active'])); ?> Activos</span>
            <span class="badge bg-secondary"><?php echo count(array_filter($topics, fn($t) => !$t['is_active'])); ?> Inactivos</span>
            <span class="badge bg-info"><?php echo count($topics); ?> Total</span>
        </div>
    </div>
</div>

<!-- Mensajes de éxito/error -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Sección principal -->
<div class="row">
    <!-- Lista de temas -->
    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-list-ul"></i> Lista de Temas de Ayuda</strong>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#topicModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Tema
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (count($topics) > 0): ?>
                <!-- Formulario de acciones masivas -->
                <form method="post" action="helptopics.php" id="massActionForm">
                    <input type="hidden" name="do" value="mass_process">
                    
                    <!-- Tabla de temas -->
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Nombre del Tema</th>
                                    <th>Departamento</th>
                                    <th>Estado</th>
                                    <th width="120">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topics as $topic): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?php echo $topic['id']; ?>" class="form-check-input topic-checkbox">
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo html($topic['name']); ?></div>
                                        <?php if ($topic['description']): ?>
                                        <div class="text-muted small"><?php echo html(substr($topic['description'], 0, 80)) . '...'; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($topic['dept_name']): ?>
                                        <span class="badge bg-light text-dark"><?php echo html($topic['dept_name']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
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
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="helptopics.php?id=<?php echo $topic['id']; ?>" 
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($topic['is_active']): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="massAction('disable', [<?php echo $topic['id']; ?>])">
                                                            <i class="bi bi-pause-circle text-warning"></i> Deshabilitar
                                                        </a>
                                                    </li>
                                                    <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="massAction('enable', [<?php echo $topic['id']; ?>])">
                                                            <i class="bi bi-play-circle text-success"></i> Habilitar
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="massAction('delete', [<?php echo $topic['id']; ?>])">
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
                    
                    <!-- Acciones masivas -->
                    <div class="border-top p-3 bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="btn-group" role="group">
                                    <button type="submit" name="a" value="enable" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-circle"></i> Habilitar
                                    </button>
                                    <button type="submit" name="a" value="disable" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pause-circle"></i> Deshabilitar
                                    </button>
                                    <button type="submit" name="a" value="delete" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('¿Está seguro de eliminar los temas seleccionados? Esta acción no se puede deshacer.')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <small class="text-muted">
                                    Seleccionados: <span id="selectedCount" class="fw-bold">0</span> de <?php echo count($topics); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <!-- Estado vacío -->
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                    </div>
                    <h5 class="text-muted">No hay temas de ayuda configurados</h5>
                    <p class="text-muted">Cree su primer tema de ayuda para comenzar a organizar el soporte</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#topicModal">
                        <i class="bi bi-plus-circle"></i> Crear Primer Tema
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reemplazado: el formulario de edición inline ahora es un modal que se abre si ?id=... -->
</div>

<!-- Modal para crear nuevo tema -->
<div class="modal fade" id="topicModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="helptopics.php">
                <input type="hidden" name="do" value="create">
                <?php csrfField(); ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Nuevo Tema de Ayuda
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Tema <span class="text-danger">*</span></label>
                                <input type="text" name="topic" class="form-control" required
                                       placeholder="Ej: Problemas técnicos">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Departamento</label>
                                <select name="dept_id" class="form-select">
                                    <option value="">Seleccionar departamento</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo html($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Descripción detallada del tema de ayuda"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="isactive" id="modalIsactive" checked>
                                    <label class="form-check-label" for="modalIsactive">
                                        Tema Activo
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="ispublic" id="modalIspublic" checked>
                                    <label class="form-check-label" for="modalIspublic">
                                        Tema Público
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Crear Tema
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editingTopic): ?>
<!-- Modal para editar tema (se abre si ?id=...) -->
<div class="modal fade" id="editTopicModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="helptopics.php">
                <input type="hidden" name="do" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$editingTopic['id']; ?>">
                <?php csrfField(); ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Editar Tema de Ayuda
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Tema <span class="text-danger">*</span></label>
                                <input type="text" name="topic" class="form-control" required
                                       value="<?php echo html($editingTopic['name']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Departamento</label>
                                <select name="dept_id" class="form-select">
                                    <option value="">Seleccionar departamento</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($editingTopic['dept_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo html($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo html($editingTopic['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="isactive" id="editIsactive" <?php echo $editingTopic['is_active'] ? 'checked' : ''; ?> >
                                    <label class="form-check-label" for="editIsactive">Tema Activo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Actualizar Tema
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.HELP_TOPICS_AUTO_OPEN_EDIT_MODAL = true;</script>
<?php endif; ?>

<script src="js/helptopics_page.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/helptopics_page.js'); ?>"></script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>