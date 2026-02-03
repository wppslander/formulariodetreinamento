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
        /* Estilização do formulário */
        .job-form {
            margin: 0 auto;
            max-width: 800px; /* Mantendo limite de largura para não esticar demais */
            padding: 30px;
            border-radius: 12px;
            font-family: Arial, sans-serif;
            background: #fff; /* Garantindo fundo branco se o body for cinza */
        }
        /* Estilização da linha divisória */
        .job-form hr {
            border: 1px solid #dee2e6;
            margin: 25px 0;
        }
        /* Estilização dos labels */
        .job-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        /* Estilização dos inputs e selects */
        .job-form input[type="text"],
        .job-form input[type="date"],
        .job-form input[type="email"],
        .job-form input[type="tel"],
        .job-form input[type="file"],
        .job-form input[type="number"], /* Adicionado number */
        .job-form select,
        .job-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            background-color: #fff;
            transition: border-color 0.3s ease;
        }
        /* Estilização do select com ícone de seta */
        .job-form select {
            appearance: none;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iY3VycmVudENvbG9yIiB2aWV3Qm94PSIwIDAgMTYgMTYiPjxwYXRoIGQ9Ik04IDlsLTQtNC4xNTMtMS4xNTUgMS1xNUwxIDExLjE1eiI+PC9wYXRoPjxwYXRoIGQ9Ik04IDlsNCA0LjE1IDMuMTUtMy4xNUw4IDExeiI+PC9wYXRoPjwvc3ZnPg==');
            background-position: right 10px center;
            background-repeat: no-repeat;
            background-size: 12px;
        }
        /* Estilização do botão de envio */
        .job-form input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #dc0c15;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        /* Estilização do texto de aceite */
        .job-form .terms-text {
            font-size: 14px;
            color: #555;
        }
        /* Estilização dos links */
        .job-form .terms-text a {
            color: #007bff;
            text-decoration: none;
        }
        /* Estilização dos checkboxes */
        .job-form input[type="checkbox"] {
            margin-right: 5px;
        }
        /* Estilização do textarea */
        .job-form textarea {
            height: 120px;
            resize: vertical;
        }
        h1 {
            color: #dc0c15;
            /*text-align: center;  centraliza o h1 */
            font-size:32px;
            text-align: center;
        }
        /* Centraliza h2 e dá espaçamento */
        h2 {
            text-align: center;
            margin-top: 10px;
            color: #555; 
            font-size:28px
        }
        
        /* Ajustes extras para layout responsivo com Bootstrap */
        body { background-color: #f4f6f9; }
        .logo-container { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="job-form">
        
        <!-- Área de Mensagens (Sucesso/Erro vindos do Controller) -->
        <?php echo $message; ?>

        <!-- Cabeçalho Visual -->
        <div class="logo-container">
            <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat Logo" style="max-height: 50px;">
        </div>

        <div class="form-header">
            <h1>Cadastro de Treinamentos Internos</h1>
            <h2 style="font-size: 1.2rem; margin-top: 0;">Ciclo 2026</h2>
        </div>

        <div class="intro-text mb-4 text-center text-muted">
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
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required placeholder="seu@email.com">
                </div>
                <div class="col-md-6">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" required minlength="3">
                </div>

                <div class="col-md-6">
                    <label for="filial">Filial</label>
                    <select id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione...</option>
                        <!-- Loop PHP para gerar opções baseadas na configuração -->
                        <?php foreach($filiais_permitidas as $f): ?>
                            <option value="<?php echo $f; ?>"><?php echo ucfirst(str_replace('_', ' ', $f)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="departamento">Departamento</label>
                    <input type="text" id="departamento" name="departamento" required>
                </div>
            </div>

            <hr>

            <!-- Seção 2: Dados do Treinamento -->
            <h2>Dados do Treinamento</h2>

            <div class="mb-3">
                <label for="curso">Nome do Curso / Treinamento</label>
                <input type="text" id="curso" name="curso" required>
            </div>

            <div class="mb-3">
                <label>Modalidade</label>
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
                <input type="text" class="mt-2" id="outro_texto" name="outro_texto" placeholder="Especifique qual..." style="display:none;">
            </div>

            <!-- Inputs de Duração (Horas + Minutos) -->
            <div class="mb-3">
                <label>Carga Horária</label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" id="duracao_horas" name="duracao_horas" min="0" required placeholder="0">
                            <span class="input-group-text">Horas</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group">
                            <input type="number" id="duracao_minutos" name="duracao_minutos" min="0" max="59" required placeholder="0">
                            <span class="input-group-text">Minutos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload de Arquivo -->
            <div class="mb-4">
                <label for="comprovante">Certificado ou Comprovante (Opcional)</label>
                <input type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                <div class="terms-text mt-1">Aceita PDF, JPG, PNG (Max 5MB).</div>
            </div>

            <input type="submit" value="Enviar Registro">
        </form>
    </div>
    
    <div class="text-center text-muted small mt-3">
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