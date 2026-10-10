<?php
// Gera e valida tokens JWT (assinados com HMAC-SHA256)
class JWTHelper {
    // Chave secreta da assinatura (só o servidor conhece)
    private static string $secret = "CHAVE_SECRETA_GUILHERME_SENAI_CONECTA";

    // Cria o token: cabeçalho.dados.assinatura (base64 seguro para URL)
    public static function encode(array $payload): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Assinatura: impede que alguém altere os dados do token
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    // Valida o token: devolve os dados ou null se for inválido/expirado
    public static function decode(string $jwt): ?array {
        $tokenParts = explode('.', $jwt);
        // O token precisa ter 3 partes separadas por ponto
        if (count($tokenParts) !== 3) return null;

        $header = self::base64UrlDecode($tokenParts[0]);
        $payload = self::base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        // Recalcula a assinatura; se for diferente da recebida, o token foi alterado
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        if ($base64UrlSignature !== $signatureProvided) return null;

        // Verifica a expiração (campo exp)
        $payloadData = json_decode($payload, true);
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) return null;

        return $payloadData;
    }

    // Base64 seguro para URL (troca + e / por - e _, remove =)
    private static function base64UrlEncode(string $text): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    // Caminho inverso do base64UrlEncode
    private static function base64UrlDecode(string $text): string {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $text));
    }
}