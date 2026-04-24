<?php
/**
 * Página de Créditos y Autoría
 */
?>
<div class="credits-container">
    <div class="credits-card">
        <div class="credits-header">
            <div class="credits-icon">
                <i class="bi bi-code-slash"></i>
            </div>
            <h1 class="credits-title">Información del Sistema</h1>
        </div>
        
        <div class="credits-body">
            <div class="credits-author-box">
                <p class="credits-label">Desarrollado por</p>
                <h2 class="credits-name">Francisco Dominguez</h2>
                <div class="credits-badge">Junior Developer</div>
            </div>
            
            <div class="credits-contact-info">
                <div class="contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span>6621-8585</span>
                </div>
                <div class="contact-item">
                    <i class="bi bi-envelope-at-fill"></i>
                    <span>dominguezf225@gmail.com</span>
                </div>
            </div>
            
            <div class="credits-specs">
                <div class="spec-item">
                    <span class="spec-label">Plataforma</span>
                    <span class="spec-value">Sistema de Tickets v2.0</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Tecnología</span>
                    <span class="spec-value">PHP 8.x / MySQL / Bootstrap 5</span>
                </div>
            </div>
            
            <div class="credits-quote">
                "Dedicado a construir soluciones eficientes y modernas para la gestión de soporte técnico."
            </div>
        </div>
        
        <div class="credits-footer">
            &copy; <?php echo date('Y'); ?> &bull; Todos los derechos reservados
        </div>
    </div>
</div>

<style>
.credits-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 70vh;
    padding: 20px;
    animation: creditsIn 0.6s cubic-bezier(0.23, 1, 0.32, 1) both;
}

@keyframes creditsIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.credits-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    border: 1px solid #f1f5f9;
}

.credits-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    padding: 40px 20px;
    text-align: center;
    color: white;
}

.credits-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #60a5fa;
    opacity: 0.9;
}

.credits-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    opacity: 0.8;
}

.credits-body {
    padding: 40px;
    text-align: center;
}

.credits-author-box {
    margin-bottom: 20px;
}

.credits-label {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
}

.credits-name {
    font-size: 2rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 10px 0;
    background: linear-gradient(to right, #0f172a, #334155);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.credits-badge {
    display: inline-block;
    padding: 4px 12px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.credits-contact-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
    margin: 20px 0;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #334155;
    font-weight: 600;
    font-size: 1rem;
}

.contact-item i {
    color: #3b82f6;
    font-size: 1.1rem;
}

.credits-specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 30px 0;
    padding: 20px 0;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

.spec-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.spec-label {
    font-size: 0.7rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
}

.spec-value {
    font-size: 0.85rem;
    color: #334155;
    font-weight: 600;
}

.credits-quote {
    font-style: italic;
    color: #64748b;
    font-size: 0.95rem;
    line-height: 1.6;
    max-width: 320px;
    margin: 0 auto;
}

.credits-footer {
    padding: 20px;
    background: #f8fafc;
    color: #94a3b8;
    font-size: 0.75rem;
    text-align: center;
    font-weight: 500;
}
</style>
