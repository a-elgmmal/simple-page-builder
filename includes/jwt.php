<?php
/**
 * JWT (JSON Web Token) Helper Class
 * Implements RFC 7519 for token generation and validation
 */
class SimplePageBuilder_JWT {
    
    /**
     * Encode data to base64url
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Decode base64url data
     */
    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Generate JWT token
     * 
     * @param array $payload Token payload data
     * @param string $secret Secret key for signing
     * @param int $expiration Expiration time in seconds (default: 1 hour)
     * @return string JWT token
     */
    public static function encode($payload, $secret, $expiration = 3600) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $now = time();
        $payload['iat'] = $now; // Issued at
        $payload['exp'] = $now + $expiration; // Expiration
        $payload['nbf'] = $now; // Not before
        
        $header_encoded = self::base64url_encode(json_encode($header));
        $payload_encoded = self::base64url_encode(json_encode($payload));
        
        $signature_input = $header_encoded . '.' . $payload_encoded;
        $signature = hash_hmac('sha256', $signature_input, $secret, true);
        $signature_encoded = self::base64url_encode($signature);
        
        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }
    
    /**
     * Decode and validate JWT token
     * 
     * @param string $token JWT token
     * @param string $secret Secret key for verification
     * @return array|WP_Error Decoded payload or error
     */
    public static function decode($token, $secret) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return new WP_Error('invalid_token', 'Invalid token format', ['status' => 401]);
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Decode header
        $header = json_decode(self::base64url_decode($header_encoded), true);
        if (!$header || !isset($header['alg']) || $header['alg'] !== 'HS256') {
            return new WP_Error('invalid_token', 'Invalid token algorithm', ['status' => 401]);
        }
        
        // Verify signature
        $signature_input = $header_encoded . '.' . $payload_encoded;
        $signature = hash_hmac('sha256', $signature_input, $secret, true);
        $signature_expected = self::base64url_encode($signature);
        
        if (!hash_equals($signature_expected, $signature_encoded)) {
            return new WP_Error('invalid_token', 'Invalid token signature', ['status' => 401]);
        }
        
        // Decode payload
        $payload = json_decode(self::base64url_decode($payload_encoded), true);
        if (!$payload) {
            return new WP_Error('invalid_token', 'Invalid token payload', ['status' => 401]);
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return new WP_Error('expired_token', 'Token has expired', ['status' => 401]);
        }
        
        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return new WP_Error('invalid_token', 'Token not yet valid', ['status' => 401]);
        }
        
        return $payload;
    }
    
    /**
     * Get JWT secret from options or generate one
     */
    public static function get_secret() {
        $secret = get_option('spb_jwt_secret');
        if (empty($secret)) {
            // Generate a secure random secret
            $secret = bin2hex(random_bytes(32));
            update_option('spb_jwt_secret', $secret);
        }
        return $secret;
    }
    
    /**
     * Get JWT expiration time from settings
     */
    public static function get_expiration() {
        $expiration = get_option('spb_jwt_expiration', 3600); // Default: 1 hour
        return (int) $expiration;
    }
}

