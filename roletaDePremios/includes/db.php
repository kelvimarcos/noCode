<?php
/**
 * ============================================================
 *  DON SPIN — Conexão PDO (Singleton)
 *  Todas as queries usam prepared statements.
 *  Nunca concatene dados do usuário em queries SQL.
 * ============================================================
 */

require_once __DIR__ . '/config.php';

/**
 * Retorna a instância única da conexão PDO.
 * Lança PDOException em caso de falha (capturada pelos endpoints).
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            // Lança exceções em vez de retornar false — erros são sempre capturados
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Retorna dados como arrays associativos por padrão
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Desativa emulação de prepared statements — usa prepared real do servidor
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Não converte inteiros para string (PHP 8.1+)
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção, nunca expõe detalhes do erro de banco
            if (APP_ENV === 'production') {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erro interno do servidor.']);
                exit;
            }
            throw $e; // Em desenvolvimento, propaga para ver o erro
        }
    }

    return $pdo;
}
