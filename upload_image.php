<?php
// Include database configuration (used for optional user check)
// Ensure this file defines $host, $db, $user, $pass correctly
include("db_config.php");

// --- Configuration ---
$targetDir = "uploads/"; // Directory to save uploaded images (ensure web server can write here!)

// !!! IMPORTANT: VERIFY THESE PATHS ON YOUR SERVER !!!
$pythonExecutable = "C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python38\\python.exe"; // Adjust for your server (Linux example: /usr/bin/python3)
$pythonScript = "process_face.py"; // Path to the Python script (ensure web server can execute this)

// --- Database Connection (Optional User Check) ---
$pdo = null; // Initialize pdo
try {
    // Establish PDO connection using variables from db_config.php
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log database connection errors instead of exposing details to users
    error_log("Database connection failed: " . $e->getMessage());
    // Provide a generic error message to the user
    die("Could not connect to the database. Please check configuration or try again later.");
}

// --- Script Logic ---
$message = ''; // To store feedback messages for the user
$errorOccurred = false; // Flag to track if any errors happened
$displayForm = false; // Flag to control whether the HTML form is shown
$userId = null; // Variable to store the validated user ID

// 1. Determine User ID (Needed for both GET display and POST processing)
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get user_id from POST data, validate as positive integer
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
} else { // Assumed GET request
    // Get user_id from GET query string, validate as positive integer
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if (!$userId) {
        // If user_id is missing or invalid in GET request
        $message = "Error: User ID is missing or invalid in the URL.";
        $errorOccurred = true;
        // Don't display the form if no valid user ID on GET
    } else {
        // If user_id is valid in GET request, prepare to display the form
        $displayForm = true;
        $message = "Please select an image file to upload and crop.";
    }
}

// 2. Process POST Request (Image Upload and Python Trigger)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get Base64 encoded cropped image data from POST
    $croppedImageData = $_POST['cropped_image_data'] ?? null;

    // Re-validate User ID received via POST (even if it was in the hidden field)
    if (!$userId) {
        $message = "Error: Invalid or missing User ID submitted.";
        $errorOccurred = true;
        $displayForm = true; // Redisplay form with error
    }
    // Validate Cropped Image Data
    elseif (empty($croppedImageData) || strpos($croppedImageData, 'data:image/') !== 0) {
        $message = "Error: No cropped image data received or data is invalid.";
        $errorOccurred = true;
        $displayForm = true; // Redisplay form with error
        // Attempt to get user_id again from original hidden field for redisplay context
        $userId = filter_input(INPUT_POST, 'user_id_original', FILTER_VALIDATE_INT);
    }
    // --- Proceed if initial validations pass ---
    else {
        // Optional: Verify User Exists in DB (can prevent processing for non-existent users)
        // Remove this block if user validation is handled elsewhere (e.g., in the API endpoint)
        try {
            $stmtCheck = $pdo->prepare("SELECT user_id FROM Users WHERE user_id = :user_id"); // Use named placeholder
            $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->rowCount() == 0) {
                $message = "Error: User with ID " . htmlspecialchars($userId) . " not found in the database.";
                $errorOccurred = true;
                // Decide if you want to display the form again or just show the error
                // $displayForm = true;
            }
        } catch (PDOException $e) {
            error_log("Error checking user existence (User ID: $userId): " . $e->getMessage());
            $message = "Error verifying user due to a database issue. Please try again.";
            $errorOccurred = true;
            // Decide if you want to display the form again or just show the error
            // $displayForm = true;
        }

        // --- Process Cropped Image if User Verified (and no previous errors) ---
        if (!$errorOccurred) {
            try {
                // ---- Decode Base64 Image Data ----
                // Example: data:image/png;base64,iVBORw0KGgo...
                // Extract image type (e.g., image/png) and base64 data
                if (!preg_match('/^data:image\/(png|jpe?g);base64,(.*)$/', $croppedImageData, $matches)) {
                    throw new Exception("Invalid image data format received.");
                }
                $imageType = $matches[1]; // png or jpeg
                $base64Data = $matches[2];
                $imageData = base64_decode($base64Data);

                if ($imageData === false) {
                    throw new Exception("Base64 decoding failed.");
                }

                // Determine file extension based on mime type
                $extension = ($imageType === 'jpeg' || $imageType === 'jpg') ? 'jpg' : 'png';

                // ---- Prepare Target Path and Filename ----
                // Ensure the target directory exists
                if (!file_exists($targetDir)) {
                    // Attempt to create directory recursively with permissive permissions (adjust if needed)
                    if (!mkdir($targetDir, 0777, true)) {
                        throw new Exception("Failed to create upload directory: " . $targetDir);
                    }
                }
                // Ensure the target directory is writable by the web server
                if (!is_writable($targetDir)) {
                    throw new Exception("Upload directory is not writable: " . $targetDir);
                }

                // Create a unique filename to avoid collisions
                $imageName = 'user_' . $userId . '_' . time() . '.' . $extension;
                $targetFile = $targetDir . basename($imageName); // Full path to save the image

                // ---- Save Decoded Image Data to File ----
                if (file_put_contents($targetFile, $imageData) === false) {
                    throw new Exception("Failed to save the cropped image to disk at " . $targetFile);
                }

                // Initial success message after saving the file
                $message = "Cropped image uploaded successfully as " . htmlspecialchars($imageName) . ".<br>Initiating face processing...<br>";

                // ---- Execute Python Script to Process Image and Call API ----
                // Securely escape arguments for shell command
                $safeTargetPath = escapeshellarg($targetFile);
                $safeUserId = escapeshellarg($userId);
                // Construct the command
                // Ensure $pythonExecutable and $pythonScript paths are correct for your server!
                $command = escapeshellcmd("$pythonExecutable $pythonScript $safeTargetPath $safeUserId");

                // Execute the command and capture the output
                // Note: shell_exec returns the full output as a string, or null on error/empty output.
                // Consider using exec() for more control over return status and output lines.
                $output = shell_exec($command);

                // Append Python script output to the message
                if ($output !== null) {
                    $trimmedOutput = trim($output);
                    $message .= "Processing Output: <pre>" . htmlspecialchars($trimmedOutput) . "</pre>";

                    // Check Python script output for success/error indicators (adjust keywords as needed)
                    if (stripos($trimmedOutput, 'sent successfully to api') !== false) {
                        $message .= "<br><strong style='color:green;'>Face encoding data successfully sent to API.</strong>";
                    } elseif (stripos($trimmedOutput, 'error') !== false || stripos($trimmedOutput, 'failed') !== false) {
                        $message .= "<br><strong style='color:red;'>Warning: Error reported during face processing or API call (see details above).</strong>";
                        $errorOccurred = true; // Mark as error if Python reported one
                    } else {
                        $message .= "<br>Processing finished (check output above for details).";
                    }
                } else {
                    // Handle cases where shell_exec fails or returns nothing
                    $message .= "<br><strong style='color:red;'>Error: Failed to execute or get output from the face processing script. Check server logs and script permissions.</strong>";
                    error_log("Failed to execute command or received null output: $command");
                    $errorOccurred = true;
                }

                // Optional: Clean up the saved image file after processing
                // Uncomment the line below if you don't need to keep the uploaded file
                // if (file_exists($targetFile)) { unlink($targetFile); }

            } catch (Exception $e) {
                // Catch errors during image decoding, saving, or directory operations
                $message = "Error processing image: " . htmlspecialchars($e->getMessage());
                error_log("Image processing error for user $userId: " . $e->getMessage());
                $errorOccurred = true;
                // Decide if you want to display the form again on processing error
                // $displayForm = true;
            }
        }
    }
} // End POST processing

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Crop Face Image</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            padding: 20px;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 700px;
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        input[type="file"] {
            display: block;
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Style the file input button */
        input[type="file"]::file-selector-button {
            margin-right: 15px;
            border: none;
            background: #007bff;
            padding: 10px 15px;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            transition: background .2s ease-in-out;
        }

        input[type="file"]::file-selector-button:hover {
            background: #0056b3;
        }

        .crop-area {
            margin-bottom: 20px;
        }

        #image-to-crop-container {
            width: 100%;
            max-height: 400px;
            /* Limit initial display height */
            margin-bottom: 20px;
            background-color: #eee;
            /* Placeholder background */
            min-height: 200px;
            /* Minimum height */
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            position: relative; /* Needed for Cropper modal */
        }

        /* This is the image Cropper will work on */
        #image-to-crop {
            display: block;
            /* Needed by Cropper.js */
            max-width: 100%;
            /* Ensure it fits container */
            opacity: 0;
            /* Hide initially until loaded */
            transition: opacity 0.3s;
        }

        #image-to-crop.loaded {
            opacity: 1;
        }

        .preview-area {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        #crop-preview-container {
            width: 150px;
            /* Example preview size */
            height: 150px;
            /* Example preview size */
            overflow: hidden;
            /* Crucial for preview */
            margin: 10px auto;
            border: 1px solid #ccc;
            border-radius: 50%;
            /* Make preview circular if desired */
            background-color: #f8f8f8;
        }

        .submit-button {
            display: block;
            width: 100%;
            padding: 12px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 20px;
        }

        .submit-button:hover {
            background-color: #218838;
        }

        .submit-button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        /* Cropper.js specific styles override */
        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
            /* Make the crop selection circular */
        }

        /* Ensure preview updates correctly with circular mask */
        .cropper-view-box {
            outline: inherit;
            /* Use container's outline potentially */
        }

        /* Status/Message Styles */
        .message-area {
            margin-top: 25px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            word-wrap: break-word; /* Ensure long messages wrap */
        }

        .message-area.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-area.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
         .message-area.info { /* Style for initial instructions */
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
            white-space: pre-wrap;
            /* Wrap long lines */
            word-wrap: break-word;
            /* Break words if necessary */
            margin-top: 10px;
            max-height: 200px; /* Limit height of output box */
            overflow-y: auto; /* Add scrollbar if needed */
        }
    </style>
</head>

<body>

    <div class="container">

        <?php // Display the form only if needed (valid GET or error on POST requiring redisplay)
        if ($displayForm || ($errorOccurred && $_SERVER['REQUEST_METHOD'] == 'POST' && $displayForm)) : ?>

            <h1>Upload & Crop Image</h1>
            <p style="text-align: center; margin-bottom: 20px;">For User ID: <strong><?php echo htmlspecialchars($userId ?: 'N/A'); ?></strong></p>

            <?php // Display initial message or error before the form if needed
            if (!empty($message)) :
                $initialMsgClass = $errorOccurred ? 'error' : 'info'; // Use 'info' for non-errors
                // Display message here if it's relevant before the form (e.g., initial instructions or POST error)
                 if ($displayForm || $errorOccurred) { // Show message if displaying form or if error occurred
                     echo "<div class='message-area $initialMsgClass'>" . $message . "</div>";
                 }
            endif; ?>

            <form id="upload-form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId ?: ''); ?>">
                <input type="hidden" name="user_id_original" value="<?php echo htmlspecialchars($userId ?: ''); ?>">

                <div>
                    <label for="image-input">Select Image File:</label>
                    <input type="file" id="image-input" accept="image/png, image/jpeg, image/jpg">
                </div>

                <div class="crop-area">
                    <label>Crop Your Image (1:1 Aspect Ratio):</label>
                    <div id="image-to-crop-container">
                        <img id="image-to-crop" alt="Image to crop">
                    </div>
                </div>

                <div class="preview-area">
                    <label>Preview:</label>
                    <div id="crop-preview-container">
                        </div>
                </div>

                <input type="hidden" name="cropped_image_data" id="cropped-image-data">

                <button type="submit" id="submit-button" class="submit-button" disabled>Crop & Upload Image</button>
            </form>

        <?php // Display Status/Result Page (if not displaying the form)
        else: ?>
            <h1>Image Upload Status</h1>
            <?php
            // Display the final feedback message after processing or critical error
            if (!empty($message)) {
                // Determine message class based on whether an error occurred during POST processing
                $messageClass = $errorOccurred ? 'error' : 'success';
                echo "<div class='message-area $messageClass'>" . $message . "</div>";
            } else {
                 // Fallback message if somehow $message is empty but form isn't shown
                 echo "<div class='message-area info'>Processing complete or page accessed directly without action.</div>";
            }
            ?>
            <p style="text-align: center; margin-top: 20px;">
                <a href="javascript:history.back()">Go Back</a> |
                <a href="/">Go Home</a>
                <?php
                // Try to get user ID from POST or GET to provide a link back to upload for the same user
                $displayUserId = filter_input(INPUT_POST, 'user_id_original', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
                if ($displayUserId) {
                    echo " | <a href='" . htmlspecialchars($_SERVER['PHP_SELF']) . "?user_id=" . $displayUserId . "'>Upload another for User ID " . $displayUserId . "</a>";
                }
                ?>
            </p>
        <?php endif; ?>

    </div> <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Get references to DOM elements
            const imageInput = document.getElementById('image-input');
            const imageElement = document.getElementById('image-to-crop');
            const imageContainer = document.getElementById('image-to-crop-container');
            const previewContainer = document.getElementById('crop-preview-container');
            const croppedImageDataInput = document.getElementById('cropped-image-data');
            const submitButton = document.getElementById('submit-button');
            const form = document.getElementById('upload-form');

            let cropper = null; // Variable to hold the Cropper instance
            let originalImageURL = null; // Store original selected image URL as Data URL

            // Event listener for file input changes
            imageInput.addEventListener('change', (event) => {
                const files = event.target.files;
                if (files && files.length > 0) {
                    const file = files[0];
                    // Basic client-side type check
                    if (!file.type.startsWith('image/')) {
                        alert('Please select a valid image file (JPEG or PNG).');
                        imageInput.value = ''; // Reset file input
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        // Store the result (Data URL) to reload image if needed
                        originalImageURL = e.target.result;
                        imageElement.src = originalImageURL;

                        // Prepare the image element for Cropper
                        imageElement.style.opacity = '0'; // Keep hidden until load event fires
                        imageContainer.style.border = 'none'; // Remove placeholder border
                        imageContainer.style.minHeight = 'auto'; // Reset min height
                        imageContainer.style.backgroundColor = 'transparent'; // Remove placeholder bg

                        // Wait for image to be loaded into the <img> tag before initializing Cropper
                        imageElement.onload = () => {
                            imageElement.classList.add('loaded'); // Add class to fade in
                            imageElement.style.opacity = '1'; // Show image now

                            // Destroy previous cropper instance if it exists
                            if (cropper) {
                                cropper.destroy();
                            }

                            // Initialize Cropper.js
                            cropper = new Cropper(imageElement, {
                                aspectRatio: 1 / 1, // Square aspect ratio for faces
                                viewMode: 1, // Restrict crop box to canvas
                                dragMode: 'move', // Allow moving the image behind the crop box
                                background: false, // Make cropper background transparent
                                preview: previewContainer, // Link to preview element CSS selector
                                responsive: true, // Resize cropper with window
                                restore: false, // Don't restore previous crop on re-init
                                checkOrientation: false, // Usually false for Data URLs
                                modal: true, // Show dark overlay outside crop box
                                guides: true, // Show dashed guides within crop box
                                center: true, // Show center indicator
                                highlight: false, // Don't highlight crop box on hover
                                cropBoxMovable: true, // Allow moving the crop box
                                cropBoxResizable: true, // Allow resizing the crop box
                                toggleDragModeOnDblclick: false, // Disable drag mode toggle
                            });

                            submitButton.disabled = false; // Enable submit button once image is ready
                        };
                        imageElement.onerror = () => {
                            alert('Failed to load the selected image.');
                            submitButton.disabled = true;
                        };
                    };
                    reader.onerror = () => {
                        alert('Failed to read the selected file.');
                        submitButton.disabled = true;
                    };
                    reader.readAsDataURL(file); // Read file as Base64 Data URL
                } else {
                    // No file selected, reset state
                    if (cropper) cropper.destroy();
                    cropper = null;
                    imageElement.src = '';
                    imageElement.classList.remove('loaded');
                    imageElement.style.opacity = '0';
                    imageContainer.style.border = '1px dashed #ccc'; // Restore placeholder border
                    imageContainer.style.minHeight = '200px'; // Restore min height
                    imageContainer.style.backgroundColor = '#eee'; // Restore placeholder bg
                    submitButton.disabled = true;
                    croppedImageDataInput.value = '';
                    if (previewContainer) previewContainer.innerHTML = ''; // Clear preview
                }
            });

            // Event listener for form submission
            if (form) { // Check if form exists (it might not on the status page)
                form.addEventListener('submit', (event) => {
                    if (cropper && !submitButton.disabled) {
                        // Get cropped data as a Base64 Data URL
                        const canvas = cropper.getCroppedCanvas({
                            width: 400, // Desired output width (adjust as needed)
                            height: 400, // Desired output height (adjust as needed)
                            fillColor: '#fff', // Background color for JPEG
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high',
                        });

                        if (!canvas) {
                            alert('Could not get cropped canvas. Please try again.');
                            event.preventDefault(); // Prevent form submission
                            return;
                        }

                        // Convert canvas to JPEG Data URL (more common for photos)
                        // Use 'image/png' if you need transparency
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.9); // Quality 0.9 (adjust as needed)

                        // Put the Base64 data into the hidden input field
                        croppedImageDataInput.value = dataUrl;

                        // Disable button to prevent multiple submissions
                        console.log("Submitting cropped data...");
                        submitButton.disabled = true;
                        submitButton.textContent = 'Uploading...';

                        // Allow the form to submit naturally
                    } else {
                        // This should ideally not happen if button is disabled correctly
                        alert('Please select and crop an image first.');
                        event.preventDefault(); // Prevent submission if cropper not ready or button disabled
                    }
                });
            }
        });
    </script>

</body>
</html>
