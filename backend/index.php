<?php
// CORS: permite que o frontend (outra origem) acesse esta API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// Todas as respostas são em JSON
header("Content-Type: application/json; charset=UTF-8");

// Pré-requisição do navegador (CORS): responde 200 e encerra
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Conexão com o banco e utilitário de token JWT
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt_helper.php';

// $db: conexão | $uri: caminho pedido | $method: GET, POST, DELETE...
$db = (new Database())->getConnection();
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$method = $_SERVER['REQUEST_METHOD'];

// Remove o prefixo da pasta do projeto para sobrar só a rota (ex.: /login)
$basePath = '/senai_conecta/backend';

if (str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}

// Rota vazia vira "/"
if ($uri === '') {
    $uri = '/';
}

// Lê o token "Bearer ..." do cabeçalho Authorization e devolve os dados do usuário (ou null)
function getAuthenticatedUser(): ?array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return JWTHelper::decode($matches[1]);
    }
    return null;
}

// ROUTE: POST /login
if ($uri === '/login' && $method === 'POST') {
    // Lê o JSON enviado pelo frontend
    $data = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $senha = $data['senha'] ?? '';

    // Busca o usuário pelo e-mail
    $stmt = $db->prepare("SELECT * FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Senha correta: gera o token (expira em 8h) e devolve os dados do usuário; senão, erro 401
    if ($user && $senha === $user['senha']) {
        $token = JWTHelper::encode([
            'id_usuario' => $user['id_usuario'],
            'username' => $user['username'],
            'nome' => $user['nome'],
            'exp' => time() + (8 * 3600)
        ]);
        echo json_encode(["token" => $token, "usuario" => [
            "id_usuario" => $user['id_usuario'],
            "nome" => $user['nome'],
            "username" => $user['username'],
            "foto" => $user['foto'],
            "tipo_perfil" => $user['tipo_perfil']
        ]]);
    } else {
        http_response_code(401);
        echo json_encode(["erro" => "E-mail ou senha inválidos."]);
    }
    exit;
}

// ROUTE: POST /cadastro
if ($uri === '/cadastro' && $method === 'POST') {
    // Dados do formulário de cadastro
    $nome = trim($_POST['nome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Todos os campos são obrigatórios (erro 400)
    if (empty($nome) || empty($username) || empty($email) || empty($senha)) {
        http_response_code(400);
        echo json_encode(["erro" => "Todos os campos obrigatórios devem ser preenchidos."]);
        exit;
    }

    // Impede username ou e-mail repetidos (erro 409)
    $stmt = $db->prepare("SELECT id_usuario FROM usuario WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["erro" => "Nome de usuário ou e-mail já cadastrados."]);
        exit;
    }

    // Foto: avatar padrão, ou a foto enviada salva com nome único em /uploads
    $fotoPath = "avatar.png";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $fotoName = uniqid() . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . "/uploads/" . $fotoName);
        $fotoPath = $fotoName;
    }

    // Grava o usuário no banco e responde 201 (criado)
    $stmt = $db->prepare("INSERT INTO usuario (nome, username, email, senha, foto) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nome, $username, $email, $senha, $fotoPath]);

    http_response_code(201);
    echo json_encode(["mensagem" => "Usuário cadastrado com sucesso!"]);
    exit;
}

// ROUTE: GET /publicacoes
// Feed: todas as publicações com autor e total de curtidas
if ($uri === '/publicacoes' && $method === 'GET') {
    // Token é opcional: serve só para marcar o que o visitante já curtiu
    $user = getAuthenticatedUser();
    $currentUserId = $user ? $user['id_usuario'] : 0;

    // JOIN traz o autor; COUNT = total de curtidas; MAX(CASE...) = 1 se o usuário logado curtiu
    $query = "
        SELECT p.*, u.nome, u.username, u.foto AS foto_usuario,
            COUNT(c.id_curtida) AS total_curtidas,
            MAX(CASE WHEN c.id_usuario = :current_user THEN 1 ELSE 0 END) AS curtido_pelo_usuario
        FROM publicacao p
        JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN curtida c ON p.id_publicacao = c.id_publicacao
        GROUP BY p.id_publicacao
        ORDER BY p.datahora_publicacao DESC
    ";

    // Executa passando o id do usuário como parâmetro (seguro contra SQL injection)
    $stmt = $db->prepare($query);
    $stmt->bindValue(':current_user', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Devolve a lista em JSON
    echo json_encode($stmt->fetchAll());
    exit;
}

// ROUTE: POST /publicacoes
// Cria uma publicação (exige token)
if ($uri === '/publicacoes' && $method === 'POST') {
    $user = getAuthenticatedUser();
    // Sem token válido: erro 401
    if (!$user) {
        http_response_code(401);
        echo json_encode(["erro" => "Acesso não autorizado."]);
        exit;
    }

    // Texto e imagem (opcional) vindos do formulário
    $texto = trim($_POST['texto'] ?? '');
    $imagemPath = null;

    // Se enviou imagem: salva com nome único em /uploads
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $imagemName = uniqid() . "." . $ext;
        move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . "/uploads/" . $imagemName);
        $imagemPath = $imagemName;
    }

    // Grava a publicação em nome do usuário logado
    $stmt = $db->prepare("INSERT INTO publicacao (id_usuario, texto, imagem) VALUES (?, ?, ?)");
    $stmt->execute([$user['id_usuario'], $texto, $imagemPath]);

    http_response_code(201);
    echo json_encode(["mensagem" => "Publicação criada com sucesso!"]);
    exit;
}

// ROUTE: POST /curtir
// Curtir/descurtir (alterna): se já curtiu remove a curtida, senão adiciona
if ($uri === '/curtir' && $method === 'POST') {
    $user = getAuthenticatedUser();
    // Sem token válido: erro 401
    if (!$user) {
        http_response_code(401);
        echo json_encode(["erro" => "Acesso não autorizado."]);
        exit;
    }

    // Id da publicação enviado pelo frontend
    $data = json_decode(file_get_contents("php://input"), true);
    $id_publicacao = $data['id_publicacao'] ?? null;

    // Verifica se este usuário já curtiu esta publicação
    $stmt = $db->prepare("SELECT id_curtida FROM curtida WHERE id_publicacao = ? AND id_usuario = ?");
    $stmt->execute([$id_publicacao, $user['id_usuario']]);
    $curtida = $stmt->fetch();

    // Já curtiu: remove | não curtiu: adiciona
    if ($curtida) {
        $delete = $db->prepare("DELETE FROM curtida WHERE id_curtida = ?");
        $delete->execute([$curtida['id_curtida']]);
        echo json_encode(["status" => "removido"]);
    } else {
        $insert = $db->prepare("INSERT INTO curtida (id_publicacao, id_usuario) VALUES (?, ?)");
        $insert->execute([$id_publicacao, $user['id_usuario']]);
        echo json_encode(["status" => "adicionado"]);
    }
    exit;
}

// ROUTE: DELETE /publicacoes/{id}
// Exclui a publicação; só o autor consegue (o WHERE confere o id_usuario)
if (preg_match('/^\/publicacoes\/(\d+)$/', $uri, $matches) && $method === 'DELETE') {
    $user = getAuthenticatedUser();
    // Sem token válido: erro 401
    if (!$user) {
        http_response_code(401);
        echo json_encode(["erro" => "Acesso não autorizado."]);
        exit;
    }

    $id_publicacao = $matches[1];
    $stmt = $db->prepare("DELETE FROM publicacao WHERE id_publicacao = ? AND id_usuario = ?");
    $stmt->execute([$id_publicacao, $user['id_usuario']]);

    // rowCount 0 = não existe ou não é do usuário (erro 403)
    if ($stmt->rowCount() > 0) {
        echo json_encode(["mensagem" => "Publicação excluída com sucesso."]);
    } else {
        http_response_code(403);
        echo json_encode(["erro" => "Ação não permitida ou publicação inexistente."]);
    }
    exit;
}

// ROUTE: GET /usuarios/{id}  -> dados do perfil de um usuário
if (preg_match('/^\/usuarios\/(\d+)$/', $uri, $matches) && $method === 'GET') {
    $idPerfil = (int)$matches[1];   // o número que veio na URL (ex.: /usuarios/4 -> 4)

    // O token é opcional: serve só para marcar quais posts o visitante já curtiu
    $user = getAuthenticatedUser();
    $currentUserId = $user ? (int)$user['id_usuario'] : 0;

    // 1) Dados públicos do usuário (não pedimos email nem senha de propósito)
    $stmt = $db->prepare("SELECT id_usuario, nome, username, foto FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$idPerfil]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        http_response_code(404);
        echo json_encode(["erro" => "Usuário não encontrado."]);
        exit;
    }

    // 2) Quantidade de publicações dele
    $stmt = $db->prepare("SELECT COUNT(*) FROM publicacao WHERE id_usuario = ?");
    $stmt->execute([$idPerfil]);
    $perfil['total_publicacoes'] = (int)$stmt->fetchColumn();

    // 3) Curtidas RECEBIDAS: a tabela curtida não diz de quem é o post,
    //    então ligamos com publicacao e contamos só as publicações DELE
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM curtida c
        JOIN publicacao p ON p.id_publicacao = c.id_publicacao
        WHERE p.id_usuario = ?
    ");
    $stmt->execute([$idPerfil]);
    $perfil['total_curtidas_recebidas'] = (int)$stmt->fetchColumn();

    // 4) Publicações dele: é a mesma query do feed, com um WHERE a mais
    $stmt = $db->prepare("
        SELECT p.*, u.nome, u.username, u.foto AS foto_usuario,
            COUNT(c.id_curtida) AS total_curtidas,
            MAX(CASE WHEN c.id_usuario = :current_user THEN 1 ELSE 0 END) AS curtido_pelo_usuario
        FROM publicacao p
        JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN curtida c ON p.id_publicacao = c.id_publicacao
        WHERE p.id_usuario = :autor
        GROUP BY p.id_publicacao
        ORDER BY p.datahora_publicacao DESC
    ");
    $stmt->bindValue(':current_user', $currentUserId, PDO::PARAM_INT);
    $stmt->bindValue(':autor', $idPerfil, PDO::PARAM_INT);
    $stmt->execute();
    $perfil['publicacoes'] = $stmt->fetchAll();

    echo json_encode($perfil);
    exit;
}

// ROUTE: GET /usuarios?busca=texto  -> pesquisa usuários por username ou nome
if ($uri === '/usuarios' && $method === 'GET') {
    $busca = trim($_GET['busca'] ?? '');
    $busca = ltrim($busca, '@');          // aceita "@maria" ou "maria"

    // Sem texto não há o que pesquisar
    if ($busca === '') {
        echo json_encode([]);
        exit;
    }

    // No LIKE, % e _ são curingas. Escapamos para a pessoa não conseguir usá-los
    $busca = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busca);
    $termo = '%' . $busca . '%';          // %texto% = "contém o texto em qualquer posição"

    // Só dados públicos (sem email e sem senha), no máximo 8 resultados
    $stmt = $db->prepare("
        SELECT id_usuario, nome, username, foto
        FROM usuario
        WHERE username LIKE :termo_username OR nome LIKE :termo_nome
        ORDER BY username
        LIMIT 8
    ");
    $stmt->execute([':termo_username' => $termo, ':termo_nome' => $termo]);

    echo json_encode($stmt->fetchAll());
    exit;
}

// Nenhuma rota combinou: erro 404
http_response_code(404);
echo json_encode(["erro" => "Rota não encontrada."]);
