<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $senha = $_POST['senha'];
    
    $query = "SELECT id, nome, email, senha, nivel FROM usuarios WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_nivel'] = $user['nivel'];
            
            
                header('Location: index.php');
                
            exit;
        } else {
            $erro = 'Senha incorreta!';
        }
    } else {
        $erro = 'Email não encontrado!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Login - Loja Gamer</title>
    <!-- <link rel="stylesheet" href="style.css"> -->
     
    <style>
body {
    background-image: url('img/teste3.jpg');
     max-width: 100%;
     height: 500px;
     aspect-ratio: 1 / 1 ;
     object-fit: cover;
     background-repeat: no-repeat;
     background-size: cover;
     
     

}
        .form-login { max-width: 400px; margin: 50px auto; padding: 30px; background: #000000; border-radius: 10px; color: #ffffff;}
        input[type="email"], input[type="password"] { width: 96%; padding: 7px; margin: 10px 0; background: #ffff; color: #140707; border: 1px solid #666; border-radius: 5px; }
        .btn { background: #ff4500; width: 100%; padding: 12px; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .erro { color: #ff4444; text-align: center; margin: 10px 0; }
        /* .cadastro-link { text-align: center; margin-top: 20px; color: #ff0303; } */
        /* Estilo base do link */
.link-daanger {
  color: rgb(139, 0, 0);       /* Cor vermelha escura */
  text-decoration: none;   /* Mantém o sublinhado */
  transition: color 0.3s, opacity 0.3s; /* Transição suave */
}

/* Quando passa o mouse (Hover) */
.link-daanger:hover {
  color: rgb(200, 0, 0);       /* Muda para um vermelho mais claro */
  opacity: 0.8;                /* Ou fica levemente transparente */
  text-decoration: none;       /* Remove o sublinhado no hover (opcional) */
}

/* Quando o link é clicado (Active) */
.link-daanger:active {
  color: rgb(100, 0, 0);       /* Fica ainda mais escuro ao clicar */
}
/* Estilo base do link */
.verde {
  color: rgb(36, 224, 42);       /* Cor vermelha escura */
  text-decoration: underline;   /* Mantém o sublinhado */
  transition: color 0.3s, opacity 0.3s; /* Transição suave */
}

/* Quando passa o mouse (Hover) */
.verde:hover {
  color: rgb(36, 224, 42);       /* Muda para um vermelho mais claro */
  opacity: 0.8;                /* Ou fica levemente transparente */
  text-decoration: none;       /* Remove o sublinhado no hover (opcional) */
}

/* Quando o link é clicado (Active) */
.verde:active {
  color: rgb(36, 224, 42);       /* Fica ainda mais escuro ao clicar */
}
    </style>
</head>
<body>
    <div class="form-login">
        <h2>🔐 Entrar na Conta</h2>
        <?php if ($erro): ?><div class="erro"><?php echo $erro; ?></div><?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit" name="login" class="btn">Entrar</button>
        </form>
        <div>
         <a href="cadastro.php" class="link-daanger">Não tem conta? Cadastre-se grátis!</a>
            
        </div>
        <hr>
        <a href="index.php" class="verde">← Voltar à Loja</a>
    </div>
</body>
</html>
