<?php
// Cria e guarda a conexão com o MySQL
class Database {
    // Dados de acesso (padrão do XAMPP: root sem senha)
    private string $host = "localhost";
    private string $db_name = "senai_conecta";
    private string $username = "root";
    private string $password = "";
    // Conexão (null até ser criada)
    public ?PDO $conn = null;

    // Cria a conexão só na primeira vez e reutiliza nas próximas
    public function getConnection(): PDO {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    // Erros viram exceções; resultados vêm como array associativo (nome da coluna)
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            // Falha na conexão: responde erro 500 em JSON
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["erro" => "Falha na conexão com o banco de dados."]);
                exit;
            }
        }
        return $this->conn;
    }
}