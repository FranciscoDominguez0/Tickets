<?php
/**
 * Vista: Mi perfil (solo HTML + variables PHP)
 * Variables: $profile_staff, $profile_errors, $profile_success, $has_signature_column
 */
if (!$profile_staff) {
    echo '<div class="alert alert-danger">No se pudo cargar el perfil.</div>';
    return;
}
$p = $profile_staff;
?>
<div class="profile-page">
    <h1 class="profile-title">Perfil de mi cuenta</h1>

    <?php if ($profile_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo html($profile_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($profile_errors) && isset($profile_errors[0])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo html($profile_errors[0]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form action="profile.php" method="post" class="profile-form" autocomplete="off" id="profile-form">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">

        <ul class="nav nav-tabs profile-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">Cuenta</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">Preferencias</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="signature-tab" data-bs-toggle="tab" data-bs-target="#signature" type="button" role="tab">Firma</button>
            </li>
        </ul>

        <div class="tab-content profile-tab-content" id="profileTabContent">
            <!-- Pestaña Cuenta -->
            <div class="tab-pane fade show active" id="account" role="tabpanel">
                <div class="profile-card">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar" aria-hidden="true">
                            <span class="profile-avatar-inner"><?php echo strtoupper(substr($p['firstname'], 0, 1) . substr($p['lastname'], 0, 1)); ?></span>
                        </div>
                        <div class="profile-fields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($profile_errors['firstname']) ? 'is-invalid' : ''; ?>" id="firstname" name="firstname" value="<?php echo html($p['firstname']); ?>" maxlength="100" required>
                                    <?php if (isset($profile_errors['firstname'])): ?>
                                        <div class="invalid-feedback"><?php echo html($profile_errors['firstname']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label">Apellido <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($profile_errors['lastname']) ? 'is-invalid' : ''; ?>" id="lastname" name="lastname" value="<?php echo html($p['lastname']); ?>" maxlength="100" required>
                                    <?php if (isset($profile_errors['lastname'])): ?>
                                        <div class="invalid-feedback"><?php echo html($profile_errors['lastname']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label for="email" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control <?php echo isset($profile_errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo html($p['email']); ?>" maxlength="255" required>
                                    <?php if (isset($profile_errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo html($profile_errors['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="profile-hr">

                    <h3 class="profile-section-title">Autenticación</h3>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre de usuario</label>
                            <input type="text" class="form-control bg-light" value="<?php echo html($p['username']); ?>" readonly disabled>
                            <small class="text-muted">El nombre de usuario no se puede cambiar.</small>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-primary" id="btn-change-password" data-bs-toggle="modal" data-bs-target="#modalChangePassword">
                                <i class="bi bi-key"></i> Cambiar contraseña
                            </button>
                        </div>
                    </div>

                    <hr class="profile-hr">

                    <h3 class="profile-section-title">Estado</h3>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="status_readonly" checked disabled>
                        <label class="form-check-label" for="status_readonly">
                            Cuenta activa
                        </label>
                    </div>
                </div>
            </div>

            <!-- Pestaña Preferencias -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <div class="profile-card">
                    <h3 class="profile-section-title">Preferencias</h3>
                    <p class="text-muted">Opciones de visualización y comportamiento del panel.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Registros por página</label>
                            <select class="form-select" name="page_size" disabled>
                                <option value="">Valor por defecto del sistema</option>
                                <?php for ($i = 10; $i <= 50; $i += 10): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> registros</option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted">Disponible en una próxima versión.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zona horaria</label>
                            <input type="text" class="form-control bg-light" value="<?php echo html(TIMEZONE); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña Firma -->
            <div class="tab-pane fade" id="signature" role="tabpanel">
                <div class="profile-card">
                    <h3 class="profile-section-title">Firma</h3>
                    <p class="text-muted">Firma opcional que se puede usar al responder tickets.</p>
                    <?php if ($has_signature_column): ?>
                        <div class="mb-3">
                            <label for="signature" class="form-label">Texto de la firma</label>
                            <textarea class="form-control" id="signature" name="signature" rows="5" placeholder="Ej: Atentamente,&#10;<?php echo html($p['firstname']); ?>"><?php echo html($p['signature'] ?? ''); ?></textarea>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Ejecuta el archivo <code>SQL_PROFILE_ALTER.sql</code> en tu base de datos para habilitar la firma.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-actions">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Guardar cambios
            </button>
            <button type="reset" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise"></i> Restablecer
            </button>
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>

<!-- Modal Cambiar contraseña -->
<div class="modal fade" id="modalChangePassword" tabindex="-1" aria-labelledby="modalChangePasswordLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="profile.php" method="post" id="form-change-password">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalChangePasswordLabel">Cambiar contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                        <small class="text-muted">Mínimo 6 caracteres.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (!empty($profile_password_errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalChangePassword');
    if (modal && typeof bootstrap !== 'undefined') {
        var m = new bootstrap.Modal(modal);
        m.show();
    }
});
</script>
<?php endif; ?>
