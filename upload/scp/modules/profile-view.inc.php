<?php
/**
 * Vista: Mi perfil (solo HTML + variables PHP)
 * Variables: $profile_staff, $profile_errors, $profile_success, $has_dark_mode_column, $has_phone_column
 *             $profile_dept_name, $profile_role_name, $profile_total_tickets, $profile_open_tickets
 */
if (!$profile_staff) {
    echo '<div class="alert alert-danger">No se pudo cargar el perfil.</div>';
    return;
}
$p = $profile_staff;
$initials = strtoupper(substr($p['firstname'], 0, 1) . substr($p['lastname'], 0, 1));
?>
<div class="profile-page">
    <h1 class="profile-title"><i class="bi bi-person-gear"></i> Mi Perfil</h1>

    <?php if ($profile_success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert" style="border-radius: 10px;">
            <i class="bi bi-check-circle-fill me-2 text-success"></i><?php echo html($profile_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($profile_errors) && isset($profile_errors[0])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert" style="border-radius: 10px;">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?php echo html($profile_errors[0]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- SECCIÓN DE AVATAR (Iniciales circulares compactas, como al inicio) -->
    <div class="profile-avatar-section">
        <div class="profile-avatar" aria-hidden="true">
            <span class="profile-avatar-inner"><?php echo $initials; ?></span>
        </div>
        <div class="profile-avatar-info">
            <h2 class="profile-avatar-name"><?php echo html($p['firstname'] . ' ' . $p['lastname']); ?></h2>
            <div class="profile-avatar-role">
                <i class="bi bi-person-badge text-danger"></i> @<?php echo html($p['username']); ?> &bull; Agente de Soporte
            </div>
        </div>
    </div>

    <!-- ROW DE ESTADÍSTICAS COMPACTO -->
    <div class="profile-stats-row">
        <div class="profile-stat-widget widget-total">
            <i class="bi bi-ticket-perforated"></i>
            <div class="profile-stat-details">
                <span class="profile-stat-num"><?php echo $profile_total_tickets; ?></span>
                <span class="profile-stat-lbl">Asignados</span>
            </div>
        </div>
        <div class="profile-stat-widget widget-pending">
            <i class="bi bi-hourglass-split"></i>
            <div class="profile-stat-details">
                <span class="profile-stat-num"><?php echo $profile_open_tickets; ?></span>
                <span class="profile-stat-lbl">Pendientes</span>
            </div>
        </div>
        <div class="profile-stat-widget widget-login">
            <i class="bi bi-clock-history"></i>
            <div class="profile-stat-details">
                <span class="profile-stat-num" style="font-size: 0.9rem; font-weight: 700;">
                    <?php echo !empty($p['last_login']) ? date('d/m/Y H:i', strtotime($p['last_login'])) : 'Primer ingreso'; ?>
                </span>
                <span class="profile-stat-lbl">Último acceso</span>
            </div>
        </div>
    </div>

    <!-- FORMULARIO PRINCIPAL -->
    <form action="profile.php" method="post" class="profile-form" autocomplete="off" id="profile-form">
        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">

        <ul class="nav nav-tabs profile-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                    Cuenta
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                    Preferencias
                </button>
            </li>
        </ul>

        <div class="tab-content profile-tab-content" id="profileTabContent">
            <!-- Pestaña Cuenta -->
            <div class="tab-pane fade show active" id="account" role="tabpanel">
                <div class="profile-card">
                    <h3 class="profile-section-title">Información de la Cuenta</h3>
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
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($profile_errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo html($p['email']); ?>" maxlength="255" required>
                            <?php if (isset($profile_errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo html($profile_errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_phone_column): ?>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo html($p['phone'] ?? ''); ?>" maxlength="50" placeholder="Ej. 6621-0000">
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr class="profile-hr" style="margin: 1.5rem 0; border-top: 1px solid #e2e8f0;">

                    <!-- ASIGNACIÓN Y ROL -->
                    <h3 class="profile-section-title">Rol y Departamento</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="profile-info-widget">
                                <div class="profile-info-widget-icon">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div class="profile-info-widget-content">
                                    <div class="profile-info-widget-title">Rol Asignado</div>
                                    <div class="profile-info-widget-sub"><?php echo html(ucfirst($profile_role_name ?: 'Agente')); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info-widget">
                                <div class="profile-info-widget-icon">
                                    <i class="bi bi-diagram-3"></i>
                                </div>
                                <div class="profile-info-widget-content">
                                    <div class="profile-info-widget-title">Departamento</div>
                                    <div class="profile-info-widget-sub"><?php echo html($profile_dept_name ?: 'Soporte General'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="profile-hr" style="margin: 1.5rem 0; border-top: 1px solid #e2e8f0;">

                    <!-- ACCESO Y CONTRASEÑA -->
                    <h3 class="profile-section-title">Autenticación</h3>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Nombre de usuario</label>
                            <input type="text" class="form-control bg-light" value="<?php echo html($p['username']); ?>" readonly disabled>
                        </div>
                        <div class="col-md-6 text-md-end pt-3">
                            <button type="button" class="btn btn-outline-primary" id="btn-change-password" data-bs-toggle="modal" data-bs-target="#modalChangePassword">
                                <i class="bi bi-key-fill"></i> Cambiar contraseña
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña Preferencias -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <div class="profile-card">
                    <h3 class="profile-section-title">Región y Zona Horaria</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Zona horaria del sistema</label>
                            <input type="text" class="form-control bg-light" value="<?php echo html(TIMEZONE); ?>" readonly disabled>
                        </div>
                    </div>

                    <hr class="profile-hr" style="margin: 1.5rem 0; border-top: 1px solid #e2e8f0;">

                    <!-- TEMA DE LA INTERFAZ -->
                    <h3 class="profile-section-title">Apariencia</h3>
                    <p class="text-muted small">Selecciona el tema visual para tu sesión de trabajo.</p>

                    <div class="profile-theme-selector-grid">
                        <label class="profile-theme-option">
                            <input type="radio" name="dark_mode" value="0" <?php echo (int)($p['dark_mode'] ?? 0) === 0 ? 'checked' : ''; ?>>
                            <div class="profile-theme-card">
                                <div class="profile-theme-card-icon theme-light-icon">
                                    <i class="bi bi-sun-fill"></i>
                                </div>
                                <div class="profile-theme-card-text">
                                    <h4 class="profile-theme-card-title">Tema Claro</h4>
                                    <p class="profile-theme-card-sub">Clásico y luminoso.</p>
                                </div>
                            </div>
                        </label>
                        <label class="profile-theme-option">
                            <input type="radio" name="dark_mode" value="1" <?php echo (int)($p['dark_mode'] ?? 0) === 1 ? 'checked' : ''; ?>>
                            <div class="profile-theme-card">
                                <div class="profile-theme-card-icon theme-dark-icon">
                                    <i class="bi bi-moon-stars-fill"></i>
                                </div>
                                <div class="profile-theme-card-text">
                                    <h4 class="profile-theme-card-title">Tema Oscuro</h4>
                                    <p class="profile-theme-card-sub">Deep Black & Red corporativo.</p>
                                </div>
                            </div>
                        </label>
                    </div>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="profile.php" method="post" id="form-change-password">
                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalChangePasswordLabel">
                        <i class="bi bi-shield-lock-fill"></i> Cambiar contraseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password" placeholder="Ingresa tu contraseña actual">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password" placeholder="Repite la nueva contraseña">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
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
