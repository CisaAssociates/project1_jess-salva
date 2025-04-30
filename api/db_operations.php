<?php
// db_operations.php
// Ensure config.php (or the file containing create_db_connection and log_message) is included first
// require_once __DIR__ . '/config.php'; // Adjust path as needed

/**
 * Loads all active face encodings for active users from the database.
 * Returns an array containing two elements: [encodings_array, names_array] or [null, null] on failure.
 */
function load_face_encodings_from_db(): array {
    $known_face_encodings = [];
    $known_face_names = [];
    $pdo = create_db_connection();

    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in load_face_encodings_from_db");
        return [null, null];
    }

    log_message('INFO', "Loading multiple face encodings per user from database...");
    try {
        $query = "
            SELECT
                u.user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                fe.face_encoding,
                fe.encoding_id
            FROM users u
            JOIN faceencodings fe ON u.user_id = fe.user_id
            WHERE u.is_active = TRUE
            ORDER BY u.user_id, fe.created_at DESC
        ";
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll();

        $user_encoding_counts = [];
        foreach ($results as $row) {
            try {
                $encoding_bytes = $row['face_encoding'];
                if (empty($encoding_bytes)) {
                     log_message('WARNING', "Skipping empty encoding (ID: " . ($row['encoding_id'] ?? 'N/A') . ") for user " . $row['full_name']);
                     continue;
                }

                // *** CRITICAL ASSUMPTION: Unpack binary data ***
                // Assumes Python stored numpy float64 array as little-endian doubles ('E*').
                // Adjust format string if needed based on how data is actually stored.
                $unpacked_data = unpack('E*', $encoding_bytes);

                if ($unpacked_data === false) {
                     log_message('WARNING', "Failed to unpack encoding (ID: " . ($row['encoding_id'] ?? 'N/A') . ") for user " . $row['full_name']);
                     continue;
                }
                 // unpack returns 1-based index, convert to 0-based PHP array
                $face_encoding_array = array_values($unpacked_data);
                $encoding_length = count($face_encoding_array);

                // Check encoding dimension (e.g., 128)
                if ($encoding_length === 128) { // MODIFY 128 if your model uses a different dimension
                    $known_face_encodings[] = $face_encoding_array; // Add PHP array of floats
                    $known_face_names[] = $row['full_name'];
                    $user_name = $row['full_name'];
                    $user_encoding_counts[$user_name] = ($user_encoding_counts[$user_name] ?? 0) + 1;
                } else {
                    log_message('WARNING', "Skipping invalid encoding (ID: " . ($row['encoding_id'] ?? 'N/A') . ") for user " . $row['full_name'] . " (unpacked length " . $encoding_length . ")");
                }
            } catch (Exception $unpack_err) {
                log_message('ERROR', "Error processing encoding (ID: " . ($row['encoding_id'] ?? 'N/A') . ") for user " . $row['full_name'] . ": " . $unpack_err->getMessage());
            }
        }
        // Optional: Log counts
        // if (!empty($user_encoding_counts)) { ... }

    } catch (PDOException $e) {
        log_message('ERROR', "Error fetching face encodings: " . $e->getMessage());
        return [null, null];
    } finally {
        $pdo = null; // Close connection
    }

    log_message('INFO', "Total known encodings loaded: " . count($known_face_encodings));
    return [$known_face_encodings, $known_face_names];
}

/**
 * Gets the user_id for a given full name. Returns null if not found or on error.
 */
function get_user_id(string $name): ?int {
    $pdo = create_db_connection();
    if ($pdo === null) return null;
    $user_id = null;
    try {
        $query = "SELECT user_id FROM users WHERE CONCAT(first_name, ' ', last_name) = :name AND is_active = TRUE";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':name' => $name]);
        $result = $stmt->fetchColumn();
        $user_id = ($result !== false) ? (int)$result : null;
    } catch (PDOException $e) {
        log_message('ERROR', "Error getting user ID for '{$name}': " . $e->getMessage());
    } finally {
        $pdo = null;
    }
    return $user_id;
}

/**
 * Checks if the user already has an active RFID card. Defaults true on error.
 */
function check_user_has_active_card(?int $user_id): bool {
    if ($user_id === null) return false;
    $pdo = create_db_connection();
    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in check_user_has_active_card");
        return true; // Safe default
    }
    $has_card = false;
    try {
        $query = "SELECT COUNT(*) FROM rfidcards WHERE user_id = :user_id AND is_active = TRUE";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            $has_card = true;
            log_message('DEBUG', "DB Check: User ID {$user_id} has {$count} active card(s).");
        } else {
             log_message('DEBUG', "DB Check: User ID {$user_id} has no active cards.");
        }
    } catch (PDOException $e) {
        log_message('ERROR', "Error checking active card for user ID {$user_id}: " . $e->getMessage());
        $has_card = true; // Safe default on error
    } finally {
        $pdo = null;
    }
    return $has_card;
}

/**
 * Checks if the card ID is already actively assigned to a *different* user.
 * Returns array: [bool $active_elsewhere, ?array $owner_info (assoc: user_id, owner_name)]
 * Defaults to [true, null] on error.
 */
function check_card_is_active_elsewhere(?string $card_id, ?int $current_user_id): array {
    if ($card_id === null || $current_user_id === null) return [false, null];
    $pdo = create_db_connection();
    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in check_card_is_active_elsewhere");
        return [true, null]; // Safe default
    }
    $active_elsewhere = false;
    $owner_info = null;
    try {
        $query = "
            SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
            FROM rfidcards r JOIN users u ON r.user_id = u.user_id
            WHERE r.card_id = :card_id AND r.is_active = TRUE AND r.user_id != :current_user_id
            LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':card_id' => $card_id, ':current_user_id' => $current_user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $active_elsewhere = true;
            $owner_info = $result;
            log_message('WARNING', "DB Check: Card ID {$card_id} is already active for User ID {$result['user_id']} ({$result['owner_name']}).");
        } else {
            log_message('DEBUG', "DB Check: Card ID {$card_id} is not active for another user.");
        }
    } catch (PDOException $e) {
        log_message('ERROR', "Error checking if card {$card_id} is active elsewhere: " . $e->getMessage());
        $active_elsewhere = true; // Safe default on error
    } finally {
        $pdo = null;
    }
    return [$active_elsewhere, $owner_info];
}

/**
 * Calls the RegisterRFIDCard stored procedure after performing safety checks.
 * Returns array: [bool $success, string $message]
 */
function call_register_rfid_procedure(?string $card_id, ?int $user_id): array {
    if (empty($card_id) || $user_id === null) {
        log_message('ERROR', "Cannot register card: Missing Card ID or User ID.");
        return [false, "Missing Card ID or User ID"];
    }
    $pdo = create_db_connection();
    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in call_register_rfid_procedure");
        return [false, "Database connection failed"];
    }
    $success = false;
    $message = "";
    try {
        $pdo->beginTransaction();

        // Check 1: Is it active for someone else?
        $check1_sql = "SELECT user_id FROM rfidcards WHERE card_id = :card_id AND is_active = TRUE AND user_id != :user_id LIMIT 1";
        $stmt1 = $pdo->prepare($check1_sql);
        $stmt1->execute([':card_id' => $card_id, ':user_id' => $user_id]);
        $existing_active_other = $stmt1->fetchColumn();
        if ($existing_active_other !== false) {
            $message = "Card {$card_id} is already actively assigned to another user (ID: {$existing_active_other})";
            log_message('WARNING', $message);
            $pdo->rollBack();
            return [false, $message];
        }

        // Check 2: Does it exist for a different user at all (even inactive)?
        $check2_sql = "SELECT user_id, is_active FROM rfidcards WHERE card_id = :card_id LIMIT 1";
        $stmt2 = $pdo->prepare($check2_sql);
        $stmt2->execute([':card_id' => $card_id]);
        $existing_card = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($existing_card && (int)$existing_card['user_id'] !== $user_id) {
            $isActiveStr = $existing_card['is_active'] ? 'TRUE' : 'FALSE';
            $message = "Card {$card_id} exists for different user (ID: {$existing_card['user_id']}, Active: {$isActiveStr}). Cannot re-assign via procedure.";
            log_message('WARNING', $message);
            $pdo->rollBack();
            return [false, $message];
        }

        // Call Procedure
        log_message('INFO', "Calling RegisterRFIDCard procedure for Card: {$card_id}, User ID: {$user_id}");
        $proc_sql = "CALL RegisterRFIDCard(:card_id, :user_id)";
        $stmt_proc = $pdo->prepare($proc_sql);
        $stmt_proc->execute([':card_id' => $card_id, ':user_id' => $user_id]);
        $stmt_proc->closeCursor();

        // Verify
        $verify_sql = "SELECT COUNT(*) FROM rfidcards WHERE card_id = :card_id AND user_id = :user_id AND is_active = TRUE";
        $stmt_verify = $pdo->prepare($verify_sql);
        $stmt_verify->execute([':card_id' => $card_id, ':user_id' => $user_id]);
        if ((int)$stmt_verify->fetchColumn() > 0) {
            log_message('INFO', "Verification successful for card {$card_id}.");
            $success = true;
            $message = "Card {$card_id} successfully registered/activated for user ID {$user_id}.";
            $pdo->commit();
        } else {
            $message = "Procedure ran for card {$card_id}, but verification failed. Check procedure logic.";
            log_message('WARNING', $message);
            $pdo->rollBack();
            $success = false;
        }
    } catch (PDOException $e) {
        $message = "DB error calling RegisterRFIDCard: " . $e->getMessage();
        log_message('ERROR', $message);
        if ($pdo->inTransaction()) $pdo->rollBack();
        $success = false;
    } catch (Exception $proc_err) {
        $message = "Unexpected error during RegisterRFIDCard call: " . $proc_err->getMessage();
        log_message('ERROR', $message);
        if ($pdo->inTransaction()) $pdo->rollBack();
        $success = false;
    } finally {
        $pdo = null;
    }
    return [$success, $message];
}


/**
 * Verifies if the given RFID card ID belongs to the specified user name and both are active.
 * Returns false on error or no match.
 */
function verify_rfid_card(?string $rfid_card_id, ?string $user_name): bool {
    if (empty($rfid_card_id) || empty($user_name) || $user_name === "Unknown") {
        log_message('WARNING', "RFID verification skipped: Invalid card ID ('{$rfid_card_id}') or user name ('{$user_name}').");
        return false;
    }
    $pdo = create_db_connection();
    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in verify_rfid_card");
        return false; // Assume no match
    }
    $match = false;
    try {
        $query = "
            SELECT COUNT(*) FROM rfidcards r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.card_id = :card_id AND CONCAT(u.first_name, ' ', u.last_name) = :user_name
            AND r.is_active = TRUE AND u.is_active = TRUE";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':card_id' => $rfid_card_id, ':user_name' => $user_name]);
        if ((int)$stmt->fetchColumn() > 0) {
            $match = true;
            log_message('INFO', "DB check: Card '{$rfid_card_id}' MATCHES active user '{$user_name}'.");
        } else {
            log_message('INFO', "DB check: Card '{$rfid_card_id}' NO MATCH for active user '{$user_name}'.");
        }
    } catch (PDOException $e) {
        log_message('ERROR', "Error verifying RFID card '{$rfid_card_id}' for '{$user_name}': " . $e->getMessage());
        $match = false; // Assume no match on error
    } finally {
        $pdo = null;
    }
    return $match;
}

/**
 * Logs an access attempt using the LogAccessAttempt stored procedure.
 * Returns array: [bool $success, string $message]
 */
function log_access_attempt(
    ?string $user_name,
    bool $face_recognized,
    ?string $rfid_card_id_used,
    bool $access_granted,
    ?float $confidence_score
): array {

    // Get user ID (uses its own connection)
    $user_id = null;
    if (!empty($user_name) && $user_name !== "Unknown") {
        $user_id = get_user_id($user_name); // Can return null
        if ($user_id === null) {
            log_message('WARNING', "Could not find active user_id for '{$user_name}' during logging.");
        }
    }

    // Validate/Process confidence score
    $processed_confidence = null;
    if ($confidence_score !== null) {
        if (is_numeric($confidence_score)) {
             $confidence_float = (float)$confidence_score;
             if (!($confidence_float >= 0.0 && $confidence_float <= 100.0)) { // Clamp if needed
                 log_message('WARNING', "Clamping confidence score {$confidence_float} to 0-100 range.");
                 $processed_confidence = max(0.0, min(100.0, $confidence_float));
             } else {
                 $processed_confidence = $confidence_float;
             }
        } else {
             log_message('WARNING', "Invalid non-numeric confidence score '{$confidence_score}'. Logging as NULL.");
        }
    }

    $pdo = create_db_connection();
    if ($pdo === null) {
        log_message('ERROR', "DB connection failed in log_access_attempt");
        return [false, "Database connection failed during logging"];
    }

    $success = false;
    $message = "";
    try {
        $pdo->beginTransaction();
        $current_device_id = DEVICE_ID;

        log_message('INFO', sprintf(
            "Logging attempt: User=%s(ID:%s), FaceRec=%s, RFID=%s, Granted=%s, Conf=%s, Device=%s",
             $user_name ?? 'NULL', $user_id ?? 'NULL', $face_recognized ? 'T' : 'F',
             $rfid_card_id_used ?? 'NULL', $access_granted ? 'T' : 'F',
             $processed_confidence ?? 'NULL', $current_device_id ?? 'NULL'
        ));

        $sql = "CALL LogAccessAttempt(:user_id, :face_rec, :rfid, :granted, :conf, :device)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':face_rec', $face_recognized, PDO::PARAM_BOOL);
        $stmt->bindValue(':rfid', $rfid_card_id_used, $rfid_card_id_used === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':granted', $access_granted, PDO::PARAM_BOOL);
        // Bind float/decimal as string for wider compatibility, or use PARAM_STR explicitly
        $stmt->bindValue(':conf', $processed_confidence, $processed_confidence === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':device', $current_device_id, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->closeCursor();

        $pdo->commit();
        $success = true;
        $message = "Access attempt logged successfully.";

    } catch (PDOException $e) {
        $message = "DB Error logging access attempt: " . $e->getMessage();
        log_message('ERROR', $message);
        if ($pdo->inTransaction()) $pdo->rollBack();
        $success = false;
    } catch (Exception $proc_err) {
        $message = "Unexpected error during LogAccessAttempt call: " . $proc_err->getMessage();
        log_message('ERROR', $message);
         if ($pdo->inTransaction()) $pdo->rollBack();
        $success = false;
    } finally {
        $pdo = null;
    }
    return [$success, $message];
}