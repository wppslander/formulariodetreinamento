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
    <title>Treinamentos - DigitalSat</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Roboto (Padrão) & Montserrat (Títulos) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <!-- FontAwesome para Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --ds-blue: #0054a6; /* Azul Institucional Estimado */
            --ds-dark-blue: #003366;
            --ds-orange: #f89e34; /* Laranja de destaque comum em e-commerce */
            --ds-gray-bg: #f4f6f9;
            --ds-text: #333333;
        }

        body { 
            background-color: var(--ds-gray-bg); 
            font-family: 'Roboto', sans-serif; 
            color: var(--ds-text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* --- Header --- */
        .site-header {
            background-color: #fff;
            border-bottom: 3px solid var(--ds-blue);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px 0;
            margin-bottom: 40px;
        }
        .site-header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .site-header img {
            max-height: 50px;
        }
        .header-title {
            color: var(--ds-blue);
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: none; /* Escondido em mobile */
        }
        @media(min-width: 768px) {
            .header-title { display: block; }
        }

        /* --- Main Content --- */
        .main-content {
            flex: 1;
        }

        .form-card { 
            background: #fff; 
            border: none;
            border-radius: 8px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            overflow: hidden;
            margin-bottom: 40px;
        }

        .form-card-header {
            background: var(--ds-blue);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .form-card-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }
        .form-card-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .form-card-body {
            padding: 40px;
        }

        /* Inputs Customizados */
        .form-label {
            font-weight: 500;
            color: #555;
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--ds-blue);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.15);
        }

        /* Botão */
        .btn-submit { 
            background-color: var(--ds-orange); 
            color: #fff; 
            padding: 12px 30px; 
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem; 
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 6px; 
            border: none; 
            width: 100%; 
            transition: all 0.3s ease; 
        }
        .btn-submit:hover { 
            background-color: #e08e2d; 
            color: #fff;
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(248, 158, 52, 0.4); 
        }

        /* Seções */
        .section-title {
            color: var(--ds-dark-blue);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            margin-top: 10px;
        }

        /* Footer */
        .site-footer {
            background-color: #333;
            color: #bbb;
            text-align: center;
            padding: 20px 0;
            margin-top: auto;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <!-- Header Estilo Loja -->
    <header class="site-header">
        <div class="container">
            <a href="#">
                <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat">
            </a>
            <div class="header-title">
                Portal do Colaborador
            </div>
        </div>
    </header>

    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Feedback Messages -->
                <?php echo $message; ?>

                <div class="form-card">
                    <div class="form-card-header">
                        <h2><i class="fas fa-graduation-cap me-2"></i>Cadastro de Treinamentos</h2>
                        <p>Registre suas certificações e cursos internos</p>
                    </div>

                    <div class="form-card-body">
                        <!-- 'enctype' obrigatório para envio de arquivos -->
                        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            
                            <!-- Token CSRF -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <!-- Seção 1 -->
                            <h4 class="section-title"><i class="far fa-id-card me-2"></i>Dados do Colaborador</h4>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">E-mail:</label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu@email.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="nome" class="form-label">Nome Completo:</label>
                                    <input type="text" class="form-control" id="nome" name="nome" required minlength="3">
                                </div>
                                <div class="col-md-6">
                                    <label for="filial" class="form-label">Filial:</label>
                                    <select class="form-select" id="filial" name="filial" required>
                                        <option value="" selected disabled>Selecione...</option>
                                        <?php foreach($filiais_permitidas as $f): ?>
                                            <option value="<?php echo $f; ?>"><?php echo ucfirst(str_replace('_', ' ', $f)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="departamento" class="form-label">Departamento:</label>
                                    <input type="text" class="form-control" id="departamento" name="departamento" required>
                                </div>
                            </div>

                            <!-- Seção 2 -->
                            <h4 class="section-title"><i class="fas fa-book-open me-2"></i>Dados do Treinamento</h4>
                            
                            <div class="mb-3">
                                <label for="curso" class="form-label">Nome do Curso / Treinamento:</label>
                                <input type="text" class="form-control" id="curso" name="curso" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block mb-2">Modalidade:</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_treinamento" id="presencial" value="presencial" required>
                                        <label class="form-check-label" for="presencial">Presencial</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_vivo" value="online_vivo">
                                        <label class="form-check-label" for="online_vivo">Online (Ao Vivo)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_gravado" value="online_gravado">
                                        <label class="form-check-label" for="online_gravado">Online (Gravado)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_treinamento" id="outro" value="outro">
                                        <label class="form-check-label" for="outro">Outro</label>
                                    </div>
                                </div>
                                <input type="text" class="form-control mt-2" id="outro_texto" name="outro_texto" placeholder="Especifique qual..." style="display:none;">
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Carga Horária:</label>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="duracao_horas" name="duracao_horas" min="0" required placeholder="0">
                                        <span class="input-group-text">Horas</span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="duracao_minutos" name="duracao_minutos" min="0" max="59" required placeholder="0">
                                        <span class="input-group-text">Minutos</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload -->
                            <div class="mb-4 p-3 bg-light rounded border border-dashed">
                                <label for="comprovante" class="form-label mb-2"><i class="fas fa-paperclip me-1"></i> Certificado ou Comprovante (Opcional):</label>
                                <input class="form-control" type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                                <small class="text-muted d-block mt-1">Aceita PDF, JPG, PNG (Max 5MB).</small>
                            </div>

                            <button type="submit" class="btn btn-submit btn-lg">
                                <i class="fas fa-check-circle me-2"></i> Confirmar Registro
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> DigitalSat - Todos os direitos reservados.</p>
            <small>Departamento de Recursos Humanos</small>
        </div>
    </footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Controle do campo 'Outro'
    const radios = document.querySelectorAll('input[name="tipo_treinamento"]');
    const inputOutro = document.getElementById('outro_texto');

    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (document.getElementById('outro').checked) {
                inputOutro.style.display = 'block';
                inputOutro.required = true;
                inputOutro.focus();
            } else {
                inputOutro.style.display = 'none';
                inputOutro.required = false;
                inputOutro.value = '';
            }
        });
    });

    // 2. Validação Bootstrap
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                } else {
                    const btn = form.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processando...';
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>
</body>
</html>