CREATE DATABASE umbrella;
USE umbrella;
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    preco DECIMAL(10,2),
    imagem VARCHAR(200),
    descricao TEXT
);
