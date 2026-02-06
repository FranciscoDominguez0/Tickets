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

?>

<!-- Módulo de gestión de temas de ayuda -->
<div class="page-header">
    <h1>Temas de Ayuda</h1>
    <p>Gestión de temas de ayuda y categorías del sistema</p>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Temas de Ayuda Disponibles</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTopicModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Tema
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="topicsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Departamento</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Obtener temas de ayuda de la base de datos
                            $topics = fetchAll("SELECT ht.*, d.name as dept_name 
                                            FROM help_topics ht 
                                            LEFT JOIN department d ON ht.dept_id = d.dept_id 
                                            ORDER BY ht.name");
                            
                            foreach ($topics as $topic):
                            ?>
                            <tr>
                                <td><?php echo $topic['topic_id']; ?></td>
                                <td><?php echo html($topic['name']); ?></td>
                                <td><?php echo html($topic['description']); ?></td>
                                <td><?php echo html($topic['dept_name'] ?: 'Sin asignar'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $topic['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $topic['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($topic['created'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-topic" data-id="<?php echo $topic['topic_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-topic" data-id="<?php echo $topic['topic_id']; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar tema -->
<div class="modal fade" id="addTopicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Tema de Ayuda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="topicForm">
                    <input type="hidden" id="topicId" name="topic_id">
                    <div class="mb-3">
                        <label for="topicName" class="form-label">Nombre del Tema</label>
                        <input type="text" class="form-control" id="topicName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="topicDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="topicDescription" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="topicDept" class="form-label">Departamento</label>
                        <select class="form-select" id="topicDept" name="dept_id">
                            <option value="">Seleccionar departamento</option>
                            <?php
                            $departments = fetchAll("SELECT * FROM department ORDER BY name");
                            foreach ($departments as $dept):
                            ?>
                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo html($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="topicActive" name="is_active" checked>
                            <label class="form-check-label" for="topicActive">
                                Activo
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveTopic">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Script para gestión de temas -->
<script>
$(document).ready(function() {
    // Guardar tema
    $('#saveTopic').click(function() {
        var formData = $('#topicForm').serialize();
        var topicId = $('#topicId').val();
        var url = topicId ? 'save_topic.php' : 'save_topic.php';
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            success: function(response) {
                location.reload();
            },
            error: function() {
                alert('Error al guardar el tema');
            }
        });
    });
    
    // Editar tema
    $('.edit-topic').click(function() {
        var topicId = $(this).data('id');
        // Cargar datos del tema y mostrar en el modal
        $.ajax({
            url: 'get_topic.php',
            method: 'GET',
            data: {id: topicId},
            success: function(data) {
                var topic = JSON.parse(data);
                $('#topicId').val(topic.topic_id);
                $('#topicName').val(topic.name);
                $('#topicDescription').val(topic.description);
                $('#topicDept').val(topic.dept_id);
                $('#topicActive').prop('checked', topic.is_active == 1);
                $('#addTopicModal').modal('show');
            }
        });
    });
    
    // Eliminar tema
    $('.delete-topic').click(function() {
        if(confirm('¿Está seguro de eliminar este tema?')) {
            var topicId = $(this).data('id');
            $.ajax({
                url: 'delete_topic.php',
                method: 'POST',
                data: {id: topicId},
                success: function() {
                    location.reload();
                },
                error: function() {
                    alert('Error al eliminar el tema');
                }
            });
        }
    });
});
</script>

<?php require_once 'layout.php'; ?>