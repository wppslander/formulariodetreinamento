<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamento de Treinamentos - DigitalSat</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts (Roboto for a clean tech look) -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Roboto', sans-serif;
        }
        .main-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-top: 5px solid #0056b3; /* Assuming a corporate blue based on 'DigitalSat' */
        }
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-container img {
            max-width: 250px;
            height: auto;
        }
        .form-header {
            text-align: center;
            margin-bottom: 40px;
            color: #333;
        }
        .form-header h2 {
            font-weight: 700;
            color: #0056b3;
            font-size: 1.8rem;
        }
        .form-header h4 {
            font-weight: 400;
            color: #555;
            font-size: 1.2rem;
            margin-top: 10px;
        }
        .intro-text {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            font-size: 0.95rem;
            color: #495057;
            margin-bottom: 30px;
            border-left: 4px solid #0056b3;
        }
        .form-label {
            font-weight: 500;
            color: #444;
        }
        .btn-submit {
            background-color: #0056b3;
            color: white;
            padding: 12px 30px;
            font-size: 1rem;
            border-radius: 5px;
            border: none;
            width: 100%;
            transition: background 0.3s;
        }
        .btn-submit:hover {
            background-color: #004494;
        }
        /* Custom radio styling if needed, keeping bootstrap default for cleanliness */
    </style>
</head>
<body>

<div class="container">
    <div class="main-container">
        <!-- Logo -->
        <div class="logo-container">
            <img src="https://loja.digitalsat.com.br/imagem/logo-store?v=68468041b7f0a7698a97772f2c9fda4d" alt="DigitalSat Logo">
        </div>

        <!-- Header -->
        <div class="form-header">
            <h2>Ferramenta interna de cadastro de treinamentos</h2>
            <h4>Levantamento de Treinamentos - 2026</h4>
        </div>

        <!-- Intro Text -->
        <div class="intro-text">
            Prezado(a) funcionário(a), por favor, utilize este formulário para registrar todos os treinamentos e cursos realizados ao longo do ano de 2026.
        </div>

        <!-- Form -->
        <form action="#" method="POST" enctype="multipart/form-data">
            
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="seu.email@digitalsat.com.br">
            </div>

            <div class="mb-3">
                <label for="nome" class="form-label">Nome do Funcionário</label>
                <input type="text" class="form-control" id="nome" name="nome" required placeholder="Nome completo">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="filial" class="form-label">Filial</label>
                    <select class="form-select" id="filial" name="filial" required>
                        <option value="" selected disabled>Selecione a filial...</option>
                        <option value="matriz">Matriz</option>
                        <option value="filial_norte">Filial Norte</option>
                        <option value="filial_sul">Filial Sul</option>
                        <option value="filial_leste">Filial Leste</option>
                        <option value="filial_oeste">Filial Oeste</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="departamento" class="form-label">Departamento</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" required placeholder="Ex: TI, Vendas, RH">
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3 text-muted">Detalhes do Curso</h5>

            <div class="mb-3">
                <label for="curso" class="form-label">Nome do Curso</label>
                <input type="text" class="form-control" id="curso" name="curso" required placeholder="Título do curso realizado">
            </div>

            <div class="mb-3">
                <label for="tema" class="form-label">Principal Tema do Curso</label>
                <input type="text" class="form-control" id="tema" name="tema" required placeholder="Ex: Liderança, Segurança, Programação">
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Tipo de Treinamento</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="presencial" value="presencial" required>
                    <label class="form-check-label" for="presencial">Presencial</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_vivo" value="online_vivo">
                    <label class="form-check-label" for="online_vivo">Online (ao vivo)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="online_gravado" value="online_gravado">
                    <label class="form-check-label" for="online_gravado">Online (gravado)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_treinamento" id="outro" value="outro">
                    <label class="form-check-label" for="outro">Outro</label>
                </div>
                <input type="text" class="form-control mt-2" id="outro_texto" name="outro_texto" placeholder="Se outro, especifique" style="display:none;">
            </div>

            <div class="mb-3">
                <label for="duracao" class="form-label">Duração do Curso (Horas)</label>
                <input type="number" class="form-control" id="duracao" name="duracao" min="1" required placeholder="Ex: 4">
            </div>

            <div class="mb-4">
                <label for="comprovante" class="form-label">Anexar Comprovante</label>
                <input class="form-control" type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.png,.jpeg">
                <div class="form-text">Formatos aceitos: PDF, JPG, PNG.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-submit">Enviar Registro</button>

        </form>
    </div>
</div>

<!-- Simple script to show/hide 'Outro' input -->
<script>
    const radioButtons = document.querySelectorAll('input[name="tipo_treinamento"]');
    const outroInput = document.getElementById('outro_texto');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'outro') {
                outroInput.style.display = 'block';
                outroInput.required = true;
            } else {
                outroInput.style.display = 'none';
                outroInput.required = false;
                outroInput.value = '';
            }
        });
    });
</script>

<footer class="text-center mt-5 mb-5 text-muted" style="font-size: 0.8rem;">
    &copy; 2026 DigitalSat - Todos os direitos reservados.
</footer>

</body>
</html>