<?php
session_start();
include 'config.php';

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    session_unset();
    session_destroy();
    // Opcional: remover cookies de login aqui
    header("Location: admin.php"); // Redireciona para o login
    exit;
}

// Login Admin Simples
if (!isset($_SESSION['admin']) && !isset($_POST['senha'])) {
    ?>
    <!DOCTYPE html><html><body>
    <form method="POST">
        <h2>Admin Login</h2>
        <input type="password" name="senha" placeholder="Senha Admin" required>
        <button type="submit">Entrar</button>
    </form></body></html>
    <?php exit;
}

if (isset($_POST['senha']) && $_POST['senha'] === 'admin123') {
    $_SESSION['admin'] = true;
} elseif (!isset($_SESSION['admin'])) {
    die('Acesso negado!');
}
// GERENCIAMENTO USUÁRIOS
if (isset($_POST['acao_user']) && isset($_SESSION['admin_id'])) {
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    switch ($_POST['acao_user']) {
        case 'ativar':
            mysqli_query($conn, "UPDATE usuarios SET ativo = 1 WHERE id = $user_id");
            mysqli_query($conn, "INSERT INTO usuario_logs (user_id, admin_id, acao, detalhes, ip) VALUES ($user_id, $admin_id, 'ativado', 'Admin reativou conta', '$ip')");
            break;
            
        case 'inativar':
            mysqli_query($conn, "UPDATE usuarios SET ativo = 0 WHERE id = $user_id");
            mysqli_query($conn, "INSERT INTO usuario_logs (user_id, admin_id, acao, detalhes, ip) VALUES ($user_id, $admin_id, 'banido', 'Admin baniu conta', '$ip')");
            break;
            
        case 'reset_senha':
            $nova_senha = password_hash('123456', PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE usuarios SET senha = '$nova_senha' WHERE id = $user_id");
            mysqli_query($conn, "INSERT INTO usuario_logs (user_id, admin_id, acao, detalhes, ip) VALUES ($user_id, $admin_id, 'reset_senha', 'Senha resetada para 123456', '$ip')");
            break;
    }
    header('Location: admin.php#usuarios');
    exit;
}
// EXCLUSÃO DE USUÁRIOS
if (isset($_POST['deletar_usuario']) && isset($_SESSION['admin_id'])) {
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['admin_id'];
    $confirmado = $_POST['confirmar'] ?? 0;
    
    if (!$confirmado) {
        // Mostra confirmação
        $_SESSION['confirm_del_user'] = $user_id;
        $_SESSION['confirm_del_msg'] = "Confirmar exclusão do usuário ID $user_id? Todos pedidos serão excluídos.";
        header('Location: admin.php#confirm_del_user');
        exit;
    }
    
    // Backup dados antes de deletar
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $user_id"));
    $pedidos_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pedidos WHERE user_id = $user_id"))['total'];
    
    // Log da exclusão
    $ip = $_SERVER['REMOTE_ADDR'];
    mysqli_query($conn, "INSERT INTO usuario_logs (user_id, admin_id, acao, detalhes, ip) VALUES ($user_id, $admin_id, 'excluido', 'Excluído por admin. Pedidos: $pedidos_count', '$ip')");
    
    // Deleta CASCAATA: pedidos → itens → usuário
    mysqli_query($conn, "DELETE ip FROM itens_pedido ip JOIN pedidos p ON ip.pedido_id = p.id WHERE p.user_id = $user_id");
    mysqli_query($conn, "DELETE FROM pedidos WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM usuarios WHERE id = $user_id");
    
    // Limpa confirmação
    unset($_SESSION['confirm_del_user'], $_SESSION['confirm_del_msg']);
    
    // Mensagem sucesso
    $_SESSION['msg_sucesso'] = "Usuário ID $user_id excluído com $pedidos_count pedidos relacionados!";
    header('Location: admin.php#usuarios');
    exit;
}

// HANDLERS DE PEDIDOS
if (isset($_POST['deletar_pedido'])) {
    $pedido_id = (int)$_POST['deletar_pedido'];
    mysqli_query($conn, "DELETE FROM itens_pedido WHERE pedido_id = $pedido_id");
    mysqli_query($conn, "DELETE FROM pedidos WHERE id = $pedido_id");
    header('Location: admin.php#pedidos');
    exit;
}

if (isset($_POST['acao_pedido'])) {
    $pedido_id = (int)$_POST['acao_pedido'];
    $novo_status = mysqli_real_escape_string($conn, $_POST['novo_status']);
    mysqli_query($conn, "UPDATE pedidos SET status = '$novo_status' WHERE id = $pedido_id");
    header('Location: admin.php#pedidos');
    exit;
}

// CRUD Produtos
if (isset($_POST['acao'])) {
    switch ($_POST['acao']) {
        case 'add':
            $nome = mysqli_real_escape_string($conn, $_POST['nome']);
            $preco = floatval($_POST['preco']);
            $imagem = $_FILES['imagem']['name'];
            $descricao = mysqli_real_escape_string($conn, $_POST['descricao']);
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], "img/" . $imagem)) {
                mysqli_query($conn, "INSERT INTO produtos (nome, preco, imagem, descricao) VALUES ('$nome', $preco, '$imagem', '$descricao')");
            }
            break;
        case 'edit':
            $id = $_POST['id'];
            $nome = mysqli_real_escape_string($conn, $_POST['nome']);
            $preco = $_POST['preco'];
            $descricao = mysqli_real_escape_string($conn, $_POST['descricao']);
            mysqli_query($conn, "UPDATE produtos SET nome='$nome', preco=$preco, descricao='$descricao' WHERE id=$id");
            break;
        case 'delete':
            $id = $_POST['id'];
            $produto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT imagem FROM produtos WHERE id=$id"));
            unlink("img/" . $produto['imagem']);
            mysqli_query($conn, "DELETE FROM produtos WHERE id=$id");
            break;
    }
}

// Listar Produtos
$produtos = mysqli_query($conn, "SELECT * FROM produtos ORDER BY id DESC");
$ultimos_pedidos = mysqli_query($conn, "SELECT * FROM pedidos ORDER BY data DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin - Loja Gamer</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #222; color: #fff; font-family: Arial; }
        .section { margin: 20px; padding: 20px; background: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 8px; text-align: left; }
        form { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        input, textarea, button { padding: 8px; background: #444; color: #fff; border: 1px solid #666; }
        button { background: #ff4500; cursor: pointer; }
        .produtos td:last-child button { background: #dc3545; margin-right: 5px; }
        .edit-form { background: #444; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
<header class="bg-danger">
        <h1><i class="fa-solid fa-wrench"></i> Painel Admin</h1>
        <header class="bg-danger text-white"><nav class="navbar navbar-lg">
                    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="navbarNav" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
      <ul class="navbar-nav">
        <li class="nav-item">
          <h3><a class="nav-link active" aria-current="page" href="index.php"><- Voltar a loja</a></h3>
        </li>
        <li class="nav-item">
          <h3><a class="nav-link active" aria-current="page" href="admin.php?logout=1"  >Sair</a></h3>
        </li>
        <!-- <li class="nav-item">
            <h3><a class="nav-link active" aria-current="page" href="login.php">Login</a></h3>
        </li> -->
      </ul>
</div>
</nav>
    </header>

    <!-- Adicionar Produto -->
    <div class="section rounded bg-dark">
        <h2>➕ Novo Produto</h2>
        <form  method="POST" enctype="multipart/form-data">
            <div class="row">
                <input type="hidden" name="acao" value="add">
                <div class="form-group col-md-6">
                    <label for="InputPro">Produto</label>
      <input type="text" class="form-control" id="InputPro" name="nome" placeholder="Nome do Produto" required>
            <!-- <input type="text" name="nome" placeholder="Nome do Produto" required> -->
            </div>
            <div class="form-group col-md-6">
                <label for="InputPre">Preço</label>
            <input type="number" class="form-control" id="InputPre" name="preco" step="0.01" placeholder="Preço" required>
        </div>
        <div class="form-group col-md-6">
            <label for="InputImg">Imagem</label>
            <input type="file" class="form-control" id="InputImg" name="imagem" placeholder="Imagem" accept="image/*" required>
        </div>
        <div class="row gap-1">
        <div class="col-10">
            <label for="InputDes">Descrição</label>
            <textarea name="descricao" class="form-control" id="InputDes" placeholder="Descrição"></textarea>
        </div>
        
        
            <div colspan="2"><button class="btn btn-danger mb-3" type="submit">Adicionar</button></div>
        
        </div>
            </div>
        </form>
    </div>

    <!-- Listar/Editar Produtos -->
    <div class="section rounded bg-dark">
        <h2>📦 Produtos (<?=mysqli_num_rows($produtos)?>)</h2>
        
            <!-- <tr><th>ID</th><th>Imagem</th><th>Nome</th><th>Preço</th><th>Descrição</th><th>Ações</th></tr> -->
            <!-- <div class="container text-center bg-dark fade-in "> -->
            
                <?php while($p = mysqli_fetch_assoc($produtos)): ?>
            <div class="container text-center bg-dark">
              <div class="row justify-content-center gap-1">

                <div class="card col-2">

                    <?=$p['id']?>

                    <img class="card-img" src="img/<?=$p['imagem']?>" width="50" onerror="this.src='img/default.jpg'"></div>

                        <div class="card col-6">

                        <h5 class="card-title text-center"><?=$p['nome']?></h5>

                        <p class="card-text">

                        <strong> R$ <?=number_format($p['preco'],2)?></strong>

                        <p class="card-text"><?=substr($p['descricao'],0,50)?>...</p>

            </div>
                    <form method="POST" class="edit-form bg-dark" style="display:inline;">
                        <input type="hidden" name="acao" value="edit">
                        <div class="row justify-content-center gap-1">
                        <input type="hidden"  name="id" value="<?=$p['id']?>">
                        <div class="form-group col-md-6">
                        <input type="text" class="form-control" name="nome" value="<?=$p['nome']?>" size="15"></div>
                        <div class="form-group col-md-6">
                        <input type="number" class="form-control" name="preco" value="<?=$p['preco']?>" step="0.01" size="8"></div>
                        <div class="row justify-content-center gap-1">
                            <div class="form-group col-12">
                        <textarea name="descricao" class="form-control" rows="2"><?=$p['descricao']?></textarea></div>
                        <button class="btn btn-success col-3">Salvar</button>
                    </div>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="delete">
                        <input type="hidden" name="id" value="<?=$p['id']?>">
                        <button class="btn btn-danger" onclick="return confirm('Deletar?')"><i class='fas fa-trash' style="color: #ffff;"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </div>
 
    </div>
    <br>

    <!-- Últimos Pedidos -->
    <!-- Últimos Pedidos -->
<div class="section rounded bg-dark">
    <h2>📋 Últimos Pedidos</h2>
    
    <div class="row">
        <?php while($ped = mysqli_fetch_assoc($ultimos_pedidos)): ?>
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <label>ID:</label> <?=$ped['id']?>
                </div>
                <div class="card-body bg-white text-dark">
                    <p class="card-text"><?=$ped['nome_cliente']?></p>
                    <label>User/Email:</label> (<?=$ped['email']?>)
                    <p class="card-text"><strong><label>Preço:</label> R$ <?=number_format($ped['total'],2)?></strong></p>
                    <div class="card-text"><?=date('d/m / H:i', strtotime($ped['data']))?> - <?=$ped['status']?></div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    
    <p><a href="relatorio_pedidos.php">Ver todos ➔</a></p>
</div>
</body>

</html>
<br>
<!-- GERENCIAMENTO DE USUÁRIOS --> 
<div class="section rounded bg-dark">
    <h2 style="color: #ffff;"><i class="fa-solid fa-people-group" style="color: #ffff;"></i>Usuários  (<?=mysqli_num_rows(mysqli_query($conn, "SELECT id FROM usuarios"))?>)</h2>
    
    <!-- Filtro -->
    <?php 
    $filtro = $_GET['filtro'] ?? 'todos';
    $where = '';
    if ($filtro == 'ativos') $where = 'WHERE ativo = 1';
    elseif ($filtro == 'inativos') $where = 'WHERE ativo = 0';
    $usuarios = mysqli_query($conn, "SELECT u.*, COUNT(p.id) as pedidos FROM usuarios u LEFT JOIN pedidos p ON u.id = p.id $where GROUP BY u.id ORDER BY u.criado_em DESC");
    ?>
    
    <div style="margin-bottom: 15px;">
        <a class="btn btn-dark rounded" href="?filtro=todos" style="margin-right:10px; padding:5px 10px; background:#444; color:#fff; text-decoration:none;">Todos</a>
        <a class="btn btn-success rounded" href="?filtro=ativos" style="margin-right:10px; padding:5px 10px; background:#44ff44; color:#000;">Ativos</a>
        <a class="btn btn-danger rounded" href="?filtro=inativos" style="margin-right:10px; padding:5px 10px; background:#ff4444; color:#fff;">Inativos</a>
    </div>
    
    <div class="row">
        <?php while($user = mysqli_fetch_assoc($usuarios)): ?>
            <div class="col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <strong>ID: <?=$user['id']?></strong>
                        <span>
                            <?php if($user['ativo']): ?>
                                <span style="color:#44ff44;"><i class="fa-solid fa-check" style="color:#44ff44; background-color: #000; border-radius: 50%; padding: 2px;"></i> Ativo</span>
                            <?php else: ?>
                                <span style="color:#ff4444;"><i class="fa-solid fa-x" style="color:red"></i> Inativo</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-2">
                            <div class="card-img" style="background-color: #333; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;"><i class="fa-solid fa-user" style="font-size: 24px; color: #fff;"></i></div>
                        </div>
                        <h6 class="card-title text-center"><strong><?=htmlspecialchars($user['nome'])?></strong></h6>
                        <p class="card-text mb-1"><small class="text-muted"><?=htmlspecialchars($user['email'])?></small></p>
                        <p class="card-text mb-1">Pedidos: <strong><?=$user['pedidos']?></strong></p>
                        <p class="card-text mb-2">Data: <?=date('d/m/y', strtotime($user['criado_em']))?></p>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <!-- Ativar/Banir -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao_user" value="<?=$user['ativo'] ? 'inativar' : 'ativar'?>">
                                <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                <button class="btn btn-<?=($user['ativo'] ? 'dark' : 'success')?> btn-sm rounded" style="padding:3px 8px; font-size:12px;">
                                    <?=($user['ativo'] ? '<i class="fa-solid fa-x" style="font-size:12px;"></i> Banir' : '<i class="fa-solid fa-check"></i> Ativar')?>
                                </button>
                            </form>
                            
                            <!-- Reset Senha -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao_user" value="reset_senha">
                                <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                <button class="btn btn-warning btn-sm rounded" style="padding:3px 8px; font-size:12px;" onclick="return confirm('Resetar senha para 123456?')"><i class="fa-solid fa-key" style="font-size:12px;"></i> Reset</button>
                            </form>
                            
                            <!-- Excluir -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="deletar_usuario" value="<?=$user['id']?>">
                                <input type="hidden" name="confirmar" value="0">
                                <button class="btn btn-danger btn-sm rounded" type="submit" style="padding:3px 8px; font-size:12px;" onclick="return confirm('ATENÇÃO: Excluir usuário <?=$user['nome']?> (ID <?=$user['id']?>)?\nPedidos (<?=$user['pedidos']?>) serão PERDIDOS permanentemente!')"><i class="fa-solid fa-skull-crossbones"></i> Excluir</button>
                            </form>
                            
                            <!-- Detalhes -->
                            <a href="user_detalhes.php?id=<?=$user['id']?>" class="btn btn-primary btn-sm rounded" style="padding:3px 8px; font-size:12px;"><i class="fa-solid fa-eye" style="font-size:12px"></i> Ver</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<script>
document.querySelectorAll('button[onclick*="EXCLUIR"]').forEach(btn => {
    btn.style.fontWeight = 'bold';
    btn.style.border = '2px solid #ff0000';
});

// Auto-hide mensagem sucesso após 5s
setTimeout(() => {
    const msg = document.querySelector('.msg-sucesso');
    if (msg) msg.style.display = 'none';
}, 5000);
</script>
<!-- GERENCIAMENTO DE PEDIDOS -->
<div class="section bg-dark rounded">
    <h2><i class="fa-solid fa-box-open"></i> Pedidos (<?=mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pedidos"))?>)</h2>
    
    <!-- Filtros e Busca -->
    <div style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group">
                <input type="text" class="form-control" name="busca" placeholder="Buscar por ID/Cliente" value="<?=$_GET['busca']??''?>" style="padding:8px;">
            </div>
            <div class="form-group">
                <select class="form-control" name="status" style="padding:8px;">
                    <option value="">Todos Status</option>
                    <option value="Pendente" <?=($_GET['status']??'')=='Pendente'?'selected':''?>>Pendente</option>
                    <option value="Enviado" <?=($_GET['status']??'')=='Enviado'?'selected':''?>>Enviado</option>
                    <option value="Entregue" <?=($_GET['status']??'')=='Entregue'?'selected':''?>>Entregue</option>
                    <option value="Cancelado" <?=($_GET['status']??'')=='Cancelado'?'selected':''?>>Cancelado</option>
                </select>
            </div>
            <style>
                .btn-custoom {
                    background-color: #ffa200;
                    color: #fff;
                    border: none;
                }
            </style>

            <button class="btn btn-custoom" type="submit" style="padding:8px 15px;"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
            <a class="btn btn-success" href="export_pedidos.php" style="padding:8px 15px;"><i class="fa-solid fa-chart-simple"></i> Export CSV</a>
            <a class="btn btn-secondary" href="?reset=1" style="padding:8px 15px;" onclick="return confirm('Limpar filtros?')"><i class="fa-solid fa-recycle"></i> Limpar</a>
            <label style="margin-left: auto;"><input type="checkbox" id="selecionar-todos"> Selecionar Todos</label>
        </form>
        </div>
    </div>
    
    <!-- Seleção em Massa -->
    <form method="POST" id="form-pedidos">
        <div style="margin-bottom: 10px;">
            <select name="acao_massa">
                <option value="">Ação em Massa</option>
                <option value="enviar">Marcar como Enviado</option>
                <option value="entregue">Marcar como Entregue</option>
                <option value="cancelar">Cancelar Pedidos</option>
                <option value="deletar">🗑️ Deletar Pedidos</option>
            </select>
            <button class="btn btn-danger" type="submit" style="background:#dc3545;color:#fff;border:none;padding:5px 15px;" onclick="return confirm('Confirmar ação?')">Aplicar</button>
        </div>
    
        <?php
        // Filtros
        $where = [];
        if ($_GET['busca'] ?? '') {
            $busca = mysqli_real_escape_string($conn, $_GET['busca']);
            $where[] = "(p.id LIKE '%$busca%' OR u.nome LIKE '%$busca%' OR u.email LIKE '%$busca%')";
        }
        if ($_GET['status'] ?? '') $where[] = "p.status = '" . mysqli_real_escape_string($conn, $_GET['status']) . "'";
        
        $sql = "SELECT p.*, u.nome as cliente_nome, u.email FROM pedidos p 
                LEFT JOIN usuarios u ON p.id = u.id " . 
                (count($where) ? 'WHERE ' . implode(' AND ', $where) : '') . 
                " ORDER BY p.data DESC LIMIT 50";
        $pedidos = mysqli_query($conn, $sql);
        ?>
        
        <div class="row">
            <?php while($pedido = mysqli_fetch_assoc($pedidos)):
                $itens = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as qtd FROM itens_pedido WHERE pedido_id = {$pedido['id']}"));
            ?>
            <div class="col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <strong>#<?=$pedido['id']?></strong>
                        <input type="checkbox" name="pedidos[]" value="<?=$pedido['id']?>">
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?=htmlspecialchars($pedido['cliente_nome'] ?? 'Convidado')?></h6>
                        <p class="card-text mb-1"><small class="text-muted"><?=htmlspecialchars($pedido['email'] ?? '')?></small></p>
                        <p class="card-text mb-1"><strong>Total: R$ <?=number_format($pedido['total'], 2)?></strong></p>
                        <p class="card-text mb-1">Itens: <?=$itens['qtd']?></p>
                        <p class="card-text mb-1">Data: <?=date('d/m H:i', strtotime($pedido['data']))?></p>
                        <p class="card-text mb-2">
                            <span class="status status-<?=$pedido['status']?>">
                                <?=match($pedido['status']) {
                                    'Pendente' => '⏳ Pendente',
                                    'Enviado' => '📤 Enviado',
                                    'Entregue' => '✅ Entregue',
                                    'Cancelado' => '❌ Cancelado',
                                    default => $pedido['status']
                                }?>
                            </span>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <!-- Muda Status Individual -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao_pedido" value="<?=$pedido['id']?>">
                                <select name="novo_status" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto;">
                                    <option value="Pendente" <?=($pedido['status']=='Pendente'?'selected':'')?>>⏳ Pendente</option>
                                    <option value="Enviado" <?=($pedido['status']=='Enviado'?'selected':'')?>>📤 Enviado</option>
                                    <option value="Entregue" <?=($pedido['status']=='Entregue'?'selected':'')?>>✅ Entregue</option>
                                    <option value="Cancelado" <?=($pedido['status']=='Cancelado'?'selected':'')?>>❌ Cancelado</option>
                                </select>
                            </form>
                            <!-- Deletar -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="deletar_pedido" value="<?=$pedido['id']?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Deletar pedido #<?=$pedido['id']?>?')">🗑️</button>
                            </form>
                            <a href="pedidos_detalhes.php?id=<?=$pedido['id']?>" class="btn btn-primary btn-sm">👁️</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </form>
</div>

<style>
.status-Pendente { color: #ffc107; }
.status-Enviado { color: #17a2b8; }
.status-Entregue { color: #28a745; }
.status-Cancelado { color: #dc3545; }
</style>

<script>
document.getElementById('selecionar-todos').onclick = function() {
    document.querySelectorAll('input[name="pedidos[]"]').forEach(cb => cb.checked = this.checked);
}
</script>
