<?php
header('Content-Type: application/json');
function createDbConnection() {
    include("../db_config.php");

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

function loadFaceEncodingsFromDB() {
    $knownFaceEncodings = [];
    $knownFaceNames = [];

    $conn = createDbConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'DB connection failed', 'encodings' => [], 'names' => []];
    }

    $query = "
        SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
               fe.face_encoding, fe.encoding_id
        FROM users u
        JOIN faceencodings fe ON u.user_id = fe.user_id
        WHERE u.is_active = TRUE
        ORDER BY u.user_id, fe.created_at DESC
    ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $encodingBinary = $row['face_encoding'];
            $fullName = $row['full_name'];
            $encodingId = $row['encoding_id'];

            try {
                $unpacked = unpack('d*', $encodingBinary); // Unpack 64-bit float array
                if (count($unpacked) === 128) {
                    $knownFaceEncodings[] = array_values($unpacked); // Ensure JSON serializable
                    $knownFaceNames[] = $fullName;
                } else {
                    error_log("Skipping invalid encoding (ID: $encodingId) for user $fullName");
                }
            } catch (Exception $e) {
                error_log("Error processing encoding (ID: $encodingId) for user $fullName: " . $e->getMessage());
            }
        }

        $stmt->close();
    } else {
        error_log("Query failed: " . $conn->error);
        return ['success' => false, 'message' => 'Query failed', 'encodings' => [], 'names' => []];
    }

    $conn->close();

    return [
        'known_face_encodings' => $knownFaceEncodings,
        'known_face_names' => $knownFaceNames
    ];
}

echo json_encode(loadFaceEncodingsFromDB());
