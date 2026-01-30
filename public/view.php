<?php
/**
 * VIEW.PHP
 * Responsável apenas pela camada de apresentação (HTML/CSS/JS).
 * Recebe variáveis do Controller (como $message) para exibir ao usuário.
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
    
    <!-- Bootstrap 5 CSS (Via CDN para performance e cache) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonte Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Estilos Customizados -->
    <style>
        body { background-color: #f4f6f9; font-family: 'Roboto', sans-serif; color: #333; }
        
        /* Container Principal: Centralizado e com sombra suave */
        .main-container { 
            max-width: 800px; 
            margin: 40px auto; 
            background: #fff; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border-top: 6px solid #0056b3; /* Cor da marca */
        }

        /* Branding */
        .logo-container { text-align: center; margin-bottom: 30px; }
        .logo-container img { max-width: 220px; height: auto; }
        
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-header h2 { font-weight: 700; color: #0056b3; font-size: 1.6rem; }
        
        /* Bloco de texto introdutório */
        .intro-text { 
            background-color: #eef2f7; 
            padding: 20px; 
            border-radius: 8px; 
            font-size: 0.95rem; 
            color: #495057; 
            margin-bottom: 30px; 
            border-left: 4px solid #0056b3; 
        }
        
        /* Botão de Envio com efeito Hover */
        .btn-submit { 
            background-color: #0056b3; 
            color: white; 
            padding: 14px 30px; 
            font-size: 1.1rem; 
            border-radius: 8px; 
            border: none; 
            width: 100%; 
            font-weight: 500; 
            transition: all 0.3s ease; 
        }
        .btn-submit:hover { 
            background-color: #004494; 
            transform: translateY(-1px); 
            box-shadow: 0 4px 12px rgba(0,86,179,0.2); 
        }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="main-container">
        
        <!-- Área de Mensagens (Sucesso/Erro vindos do Controller) -->
        <?php echo $message; ?>

        <!-- Cabeçalho Visual -->
        <div class="logo-container">
            <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat Logo">
        </div>

        <div class="form-header">
            <h2>Cadastro de Treinamentos Internos</h2>
            <p class="text-muted">Ciclo 2026</p>
        </div>

        <div class="intro-text">
            Prezado(a) colaborador(a), utilize este formulário para registrar oficialmente seus treinamentos realizados.
        </div>

        <!-- Início do Formulário -->
        <!-- 'enctype' é obrigatório para envio de arquivos -->
        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            
            <!-- Segurança: Token CSRF (Garante que o post vem deste site) -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Seção 1: Dados do Colaborador -->
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu@email.com">
                </div>
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required minlength="3">
                </div>

                <div class="col-md-6">
                    <label for="filial" class="form-label">Filial</label>
                    <select class="form-select" id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione...</option>
                        <!-- Loop PHP para gerar opções baseadas na configuração -->
                        <?php foreach($filiais_permitidas as $f): ?>
                            <option value="<?php echo $f; ?>"><?php echo ucfirst(str_replace('_', ' ', $f)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="departamento" class="form-label">Departamento</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" required>
                </div>
            </div>

            <hr class="my-4 text-muted">

            <!-- Seção 2: Dados do Treinamento -->
            <h5 class="mb-3 text-primary">Dados do Treinamento</h5>

            <div class="mb-3">
                <label for="curso" class="form-label">Nome do Curso / Treinamento</label>
                <input type="text" class="form-control" id="curso" name="curso" required>
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Modalidade</label>
                <!-- Opções de Radio Button -->
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="presencial" value="presencial" required>
                    <label class="form-check-label" for="presencial">Presencial</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_vivo" value="online_vivo">
                    <label class="form-check-label" for="online_vivo">Online (Ao Vivo)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_gravado" value="online_gravado">
                    <label class="form-check-label" for="online_gravado">Online (Gravado)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="outro" value="outro">
                    <label class="form-check-label" for="outro">Outro</label>
                </div>
                <!-- Campo condicional (aparece via JS se 'Outro' for selecionado) -->
                <input type="text" class="form-control mt-2" id="outro_texto" name="outro_texto" placeholder="Especifique qual..." style="display:none;">
            </div>

            <!-- Inputs de Duração (Horas + Minutos) -->
            <div class="mb-3">
                <label class="form-label">Carga Horária</label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" class="form-control" id="duracao_horas" name="duracao_horas" min="0" required placeholder="0">
                            <span class="input-group-text">Horas</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" class="form-control" id="duracao_minutos" name="duracao_minutos" min="0" max="59" required placeholder="0">
                            <span class="input-group-text">Minutos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload de Arquivo -->
            <div class="mb-4">
                <label for="comprovante" class="form-label">Certificado ou Comprovante (Opcional)</label>
                <input class="form-control" type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                <div class="form-text">Aceita PDF, JPG, PNG (Max 5MB).</div>
            </div>

            <button type="submit" class="btn btn-submit">
                Enviar Registro
            </button>
        </form>
    </div>
    
    <div class="text-center text-muted small">
        &copy; 2026 DigitalSat
    </div>
</div>

<!-- Scripts JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- Lógica de Interatividade ---

    // 1. Mostrar/Esconder campo "Outro"
    const radioButtons = document.querySelectorAll('input[name="tipo_treinamento"]');
    const outroInput = document.getElementById('outro_texto');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'outro') {
                outroInput.style.display = 'block';
                outroInput.required = true; // Torna obrigatório se visível
                outroInput.focus();
            } else {
                outroInput.style.display = 'none';
                outroInput.required = false; // Remove obrigatoriedade se oculto
                outroInput.value = '';
            }
        });
    });

    // 2. Validação Visual do Bootstrap e Bloqueio de Envio Duplo
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    // Se houver erro, impede o envio e mostra os erros visuais
                    event.preventDefault()
                    event.stopPropagation()
                } else {
                    // Se estiver válido: Bloqueia o botão e mostra Spinner
                    const btn = form.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>
</body>
</html>