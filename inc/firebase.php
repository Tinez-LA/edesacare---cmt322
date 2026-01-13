<?php
// firebase.php - Robust Firebase REST wrapper

// ------------------- CONFIG -------------------
const FIREBASE_API_KEY = "AIzaSyBr478W8ldgqHTPXymvCmAR9sy3qy1FWKw";
const FIREBASE_PROJECT_ID = 'usm-edesacare';
const FIRESTORE_URL = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents";

// Enable PHP errors (only for localhost)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------- HELPER: CURL POST -------------------
function firebase_post($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);

    if ($result === false) {
        return ['error' => ['message' => curl_error($ch)]];
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response from Firebase']];
    }

    return $decoded;
}

// ------------------- AUTH -------------------
function firebase_signup($email, $password) {
    $url = "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=" . FIREBASE_API_KEY;
    return firebase_post($url, [
        "email" => $email,
        "password" => $password,
        "returnSecureToken" => true
    ]);
}

function firebase_signin($email, $password) {
    $url = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=" . FIREBASE_API_KEY;
    return firebase_post($url, [
        "email" => $email,
        "password" => $password,
        "returnSecureToken" => true
    ]);
}

function firebase_send_reset($email) {
    $url = "https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=" . FIREBASE_API_KEY;
    return firebase_post($url, [
        "requestType" => "PASSWORD_RESET",
        "email" => $email
    ]);
}

// ------------------- TOKEN REFRESH -------------------
function firebase_refresh_token($refreshToken) {
    $url = "https://securetoken.googleapis.com/v1/token?key=" . FIREBASE_API_KEY;
    $postData = [
        "grant_type" => "refresh_token",
        "refresh_token" => $refreshToken
    ];
    $response = firebase_post($url, $postData);

    if (!isset($response['id_token'])) {
        return ['error' => ['message' => 'Unable to refresh ID token']];
    }

    // update session
    $_SESSION['idToken'] = $response['id_token'];
    $_SESSION['refreshToken'] = $response['refresh_token'];

    return $response;
}

// ------------------- FIRESTORE -------------------
function firestore_set($collection, $document, $fields, $idToken) {
    // Refresh token if missing
    if (empty($idToken) && !empty($_SESSION['refreshToken'])) {
        $refresh = firebase_refresh_token($_SESSION['refreshToken']);
        if (isset($refresh['id_token'])) {
            $idToken = $refresh['id_token'];
        }
    }

    if (empty($idToken)) {
        return ['error' => ['message' => 'Missing or invalid authentication (no valid ID token).']];
    }

    // 🔹 Firestore REST API endpoint
    $baseUrl = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents";
    $url = "$baseUrl/$collection/$document";

    // 🔹 Add update mask to prevent overwriting entire document
    $fieldPaths = implode('&updateMask.fieldPaths=', array_keys($fields));
    $url .= "?updateMask.fieldPaths=" . $fieldPaths;

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $idToken
    ];

    // 🔹 Proper Firestore JSON structure
    $data = ["fields" => []];
    foreach ($fields as $k => $v) {
        if (is_numeric($v)) {
            $data["fields"][$k] = ["integerValue" => intval($v)];
        } else {
            $data["fields"][$k] = ["stringValue" => strval($v)];
        }
    }

    // 🔹 Send PATCH request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);
    if ($result === false) {
        return ['error' => ['message' => curl_error($ch)]];
    }

    $decoded = json_decode($result, true);

    // 🔹 Auto refresh if token expired
    if (isset($decoded['error']['status']) && $decoded['error']['status'] === 'UNAUTHENTICATED') {
        if (!empty($_SESSION['refreshToken'])) {
            $refresh = firebase_refresh_token($_SESSION['refreshToken']);
            if (isset($refresh['id_token'])) {
                return firestore_set($collection, $document, $fields, $refresh['id_token']);
            }
        }
    }

    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response from Firestore']];
    }

    return $decoded;
}

function firestore_get($collection, $document, $idToken) {
    if (empty($idToken) && !empty($_SESSION['refreshToken'])) {
        $refresh = firebase_refresh_token($_SESSION['refreshToken']);
        if (isset($refresh['id_token'])) {
            $idToken = $refresh['id_token'];
        }
    }

    if (empty($idToken)) {
        return ['error' => ['message' => 'Missing or invalid authentication (no valid ID token).']];
    }

    $url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/$collection/$document";
    $headers = ["Authorization: Bearer " . $idToken];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);

    if ($result === false) {
        return ['error' => ['message' => curl_error($ch)]];
    }

    $decoded = json_decode($result, true);

    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response from Firestore']];
    }

    return $decoded;
}

function firestore_get_collection($collection, $idToken) {
    $projectId = FIREBASE_PROJECT_ID;
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/$collection";

    $headers = [
        "Authorization: Bearer $idToken",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status == 200) {
        $data = json_decode($response, true);
        $documents = [];
        foreach ($data['documents'] ?? [] as $doc) {
            $fields = [];
            foreach ($doc['fields'] ?? [] as $key => $value) {
                $fields[$key] = reset($value);
            }
            // Extract document ID from the name field
            $docName = $doc['name'] ?? '';
            $docId = substr($docName, strrpos($docName, '/') + 1);
            $fields['id'] = $docId;
            $documents[] = $fields;
        }
        return $documents;
    } else {
        return ['error' => json_decode($response, true) ?: ['message' => "HTTP $status error"]];
    }
}

function firestore_add($collection, $fields, $idToken) {
    // Refresh token if missing
    if (empty($idToken) && !empty($_SESSION['refreshToken'])) {
        $refresh = firebase_refresh_token($_SESSION['refreshToken']);
        if (isset($refresh['id_token'])) {
            $idToken = $refresh['id_token'];
        }
    }

    if (empty($idToken)) {
        return ['error' => ['message' => 'Missing or invalid authentication (no valid ID token).']];
    }

    $url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/$collection";

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $idToken
    ];

    // Proper Firestore JSON structure
    $data = ["fields" => []];
    foreach ($fields as $k => $v) {
        if (is_numeric($v)) {
            $data["fields"][$k] = ["integerValue" => intval($v)];
        } else {
            $data["fields"][$k] = ["stringValue" => strval($v)];
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);
    if ($result === false) {
        return ['error' => ['message' => curl_error($ch)]];
    }

    $decoded = json_decode($result, true);

    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response from Firestore']];
    }

    return $decoded;
}

?>
