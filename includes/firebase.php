<?php
class FirebaseAuth {
    /**
     * Verify a Firebase ID token using Google's REST API
     * @param string $idToken The ID token from client
     * @return array ['success'=>bool, 'uid'=>string, 'phone_number'=>string, 'error'=>string]
     */
    public static function verifyIdToken($idToken) {
        $apiKey = getenv('FIREBASE_API_KEY');
        if (!$apiKey) {
            return ['success' => false, 'error' => 'Firebase API key not configured.'];
        }

        $url = "https://www.googleapis.com/identitytoolkit/v3/relyingparty/getAccountInfo?key=" . $apiKey;
        $payload = json_encode(['idToken' => $idToken]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Firebase verify failed: HTTP $httpCode - $response");
            return ['success' => false, 'error' => 'Unable to verify phone at this time.'];
        }
        
        $data = json_decode($response, true);
        if (isset($data['users'][0])) {
            $user = $data['users'][0];
            return [
                'success' => true,
                'uid' => $user['localId'],
                'phone_number' => $user['phoneNumber'] ?? null,
                'email' => $user['email'] ?? null,
            ];
        }
        return ['success' => false, 'error' => 'Invalid ID token.'];
    }
}
?>