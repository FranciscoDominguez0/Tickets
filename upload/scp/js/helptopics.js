$(document).ready(function() {
    // Reiniciar formulario al cerrar modal
    $('#addTopicModal').on('hidden.bs.modal', function() {
        $('#topicForm')[0].reset();
        $('#topicId').val('');
        $('.modal-title').text('Agregar Tema de Ayuda');
    });
    
    // Guardar tema
    $('#saveTopic').click(function() {
        var formData = $('#topicForm').serialize();
        var topicId = $('#topicId').val();
        
        // Validación básica
        if (!$('#topicName').val().trim()) {
            alert('Por favor, ingrese el nombre del tema');
            return;
        }
        
        if (!$('#topicDescription').val().trim()) {
            alert('Por favor, ingrese la descripción del tema');
            return;
        }
        
        $.ajax({
            url: 'save_topic.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#addTopicModal').modal('hide');
                    location.reload();
                } else {
                    alert(response.message || 'Error al guardar el tema');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Error al guardar el tema. Por favor, inténtelo de nuevo.');
            }
        });
    });
    
    // Editar tema
    $('.edit-topic').click(function() {
        var topicId = $(this).data('id');
        
        $.ajax({
            url: 'get_topic.php',
            method: 'GET',
            data: {id: topicId},
            dataType: 'json',
            success: function(response) {
                if (response.topic_id) {
                    $('#topicId').val(response.topic_id);
                    $('#topicName').val(response.name);
                    $('#topicDescription').val(response.description);
                    $('#topicDept').val(response.dept_id || '');
                    $('#topicActive').prop('checked', response.is_active == 1);
                    $('.modal-title').text('Editar Tema de Ayuda');
                    $('#addTopicModal').modal('show');
                } else {
                    alert(response.message || 'Error al cargar el tema');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Error al cargar el tema. Por favor, inténtelo de nuevo.');
            }
        });
    });
    
    // Eliminar tema
    $('.delete-topic').click(function() {
        var topicId = $(this).data('id');
        var topicName = $(this).closest('tr').find('td:nth-child(2)').text();
        
        if(confirm('¿Está seguro de eliminar el tema "' + topicName + '"? Esta acción no se puede deshacer.')) {
            $.ajax({
                url: 'delete_topic.php',
                method: 'POST',
                data: {id: topicId},
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error al eliminar el tema');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error al eliminar el tema. Por favor, inténtelo de nuevo.');
                }
            });
        }
    });
    
    // Prevenir envío del formulario con Enter
    $('#topicForm').on('submit', function(e) {
        e.preventDefault();
        $('#saveTopic').click();
    });
    
    // Mejorar experiencia de usuario con tooltips
    $('.edit-topic').attr('title', 'Editar tema');
    $('.delete-topic').attr('title', 'Eliminar tema');
});
