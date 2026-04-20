CREATE TABLE usuario_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_nivel INT,
    acao VARCHAR(50),
    detalhes TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
