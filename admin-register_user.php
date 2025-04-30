<?php
ob_start(); // Start output buffering
include("db_config.php");
session_start();

// Redirect if user data is not in session
if (!isset($_SESSION['user_data'])) {
    header("Location: index.php"); // Or your login page
    exit();
}

foreach ($_SESSION['user_data'] as $key => $value) {
    $$key = $value; // Extracts user_id, email, first_name, last_name, etc.
}

if($role !== "Admin"){
    header("Location: index.php"); // Or your login page
    exit();
}

// --- Configuration ---
$targetDir = "uploads/";
$pythonExecutable = "C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python38\\python.exe"; // Adjust if needed
$pythonScript = "api_process_face.py"; // Assuming it's in the same directory or in PATH

// --- Database Connection ---
$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Could not connect to the database. Please check server logs and configuration.");
}

// --- Global Variables ---
$errorMessage = ''; // Combined error messages for form redisplay
$statusMessage = ''; // Combined message for the status page
$processingErrorOccurred = false; // Flag if errors happened AFTER validation
$displayForm = true; // Show form by default or on validation error
$displayStatusPage = false; // Show status page only on processing attempt (success or fail)
$processedUserId = null; // Store the ID of the user processed

// --- Process POST Request (Combined Signup and Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Gather Input ---
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $croppedImageData = $_POST['cropped_image_data'] ?? null;

    $validationErrors = [];

    // --- 2. Validate All Input ---
    if (empty($firstName)) $validationErrors[] = "First name is required.";
    if (empty($lastName)) $validationErrors[] = "Last name is required.";
    if ($email === false) $validationErrors[] = "Invalid email format.";
    elseif (empty($email)) $validationErrors[] = "Email is required.";
    if (empty($role)) $validationErrors[] = "Role is required.";
    if (empty($password)) $validationErrors[] = "Password is required.";
    elseif (strlen($password) < 8) $validationErrors[] = "Password must be at least 8 characters long.";
    if ($password !== $confirmPassword) $validationErrors[] = "Passwords do not match.";
    if (empty($croppedImageData) || strpos($croppedImageData, 'data:image/') !== 0) {
        $validationErrors[] = "A profile picture must be selected and cropped.";
    }

    // Check Email Uniqueness (Only if email is valid so far)
    if ($email && empty(array_filter($validationErrors, fn($err) => str_contains($err, 'Email')))) {
        try {
            $stmtCheck = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->rowCount() > 0) {
                $validationErrors[] = "The email address is already registered.";
            }
        } catch (PDOException $e) {
            error_log("Email check failed: " . $e->getMessage());
            $validationErrors[] = "Could not verify email uniqueness. Please try again.";
        }
    }

    // --- 3. Process if NO validation errors ---
    if (empty($validationErrors)) {
        $displayForm = false; // Don't show form again if processing starts
        $displayStatusPage = true; // Prepare to show status page
        $userCreated = false;
        $imageSaved = false;
        $pythonOutput = null;

        // --- Start Transactional Block (Conceptual: User Insert + Image + Python) ---
        try {
            // === STEP A: Insert the newly created user info ===
           

            $stmt = $pdo->prepare("INSERT INTO Users (first_name, last_name, email, role, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $email, $role, $password]);
            $userCreated = true; // Mark user as created within this attempt

            // === STEP B: Get that ID ===
            $processedUserId = $pdo->lastInsertId(); // Store the ID
            if (!$processedUserId) throw new Exception("Failed to retrieve new user ID after insertion.");

            $statusMessage = "Account created successfully (User ID: " . htmlspecialchars($processedUserId) . ").<br>";

            // === STEP C: Associate that ID with the picture (Decode & Save) ===
            list($type, $data) = explode(';', $croppedImageData);
            list(, $data)      = explode(',', $data);
            $imageData = base64_decode($data);
            if ($imageData === false) throw new Exception("Image data decoding failed.");

            list(, $extension) = explode('/', $type);
            $extension = strtolower(explode('+', $extension)[0]);
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) throw new Exception("Invalid image type ($extension).");

            if (!file_exists($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) throw new Exception("Failed to create upload directory.");
            } elseif (!is_writable($targetDir)) {
                 throw new Exception("Upload directory is not writable.");
            }

            // Use the retrieved $processedUserId here
            $imageName = 'user_' . $processedUserId . '_' . time() . '.' . $extension;
            $targetFile = rtrim($targetDir, '/') . '/' . basename($imageName);
            if (file_put_contents($targetFile, $imageData) === false) {
                throw new Exception("Failed to save image to disk.");
            }
            $imageSaved = true;
            $statusMessage .= "Profile picture saved as " . htmlspecialchars($imageName) . ".<br>";

            // === STEP D: Use ID and image path for Python script ===
            $safeTargetPath = escapeshellarg($targetFile);
            $safeUserId = escapeshellarg((string)$processedUserId); // Use the retrieved ID
            $command = escapeshellcmd("$pythonExecutable $pythonScript $safeTargetPath $safeUserId");
            $output = shell_exec($command . " 2>&1");
            $pythonOutput = $output; // Store for display

            if ($output === null) {
                error_log("Failed execution: $command");
                throw new Exception("Failed to execute face processing script.");
            }

            // Check Python output for success/errors
            $outputLower = strtolower($output);
            if (strpos($outputLower, 'error') !== false || (strpos($outputLower, 'success') === false && strpos($outputLower, 'processed') === false)) {
                 error_log("Python script issue for user $processedUserId: $output");
                 $statusMessage .= "Notice: Face processing script finished, but output suggests potential issues (check details below).<br>";
                 $processingErrorOccurred = true; // Flag potential issue, but not a full exception
            } else {
                 $statusMessage .= "Face processing completed.<br>";
            }

        // --- Catch potential errors during the process ---
        } catch (PDOException $e) {
            error_log("Database error during user creation: " . $e->getMessage());
            $processingErrorOccurred = true;
            if ($e->getCode() == '23000') { // Duplicate entry
                 $statusMessage = "Error: Could not create account. The email address may have just been registered. Please try again.";
            } else {
                 $statusMessage = "Error: A database error occurred while creating the account. Please try again later.";
            }
            $processedUserId = null; // Ensure ID is null if user creation failed
            $userCreated = false;

        } catch (Exception $e) {
            error_log("Processing error (User: " . ($processedUserId ?? 'N/A') . "): " . $e->getMessage());
            $processingErrorOccurred = true;
            // Craft message based on what succeeded before the error
            if ($userCreated && !$imageSaved) {
                $statusMessage = "Account created (User ID: " . htmlspecialchars($processedUserId) . "), but failed to save profile picture: " . htmlspecialchars($e->getMessage());
            } elseif ($userCreated && $imageSaved) {
                $statusMessage = "Account created (User ID: " . htmlspecialchars($processedUserId) . ") and picture saved, but face processing failed: " . htmlspecialchars($e->getMessage());
            } else { // Error likely during user creation itself or very early
                $statusMessage = "An unexpected error occurred during account setup: " . htmlspecialchars($e->getMessage());
                $processedUserId = null; // Ensure ID is null
                $userCreated = false;
            }
        }

        // Append Python output if it exists
        if ($pythonOutput !== null) {
             $statusMessage .= "<br><b>Face Processing Output:</b><pre>" . htmlspecialchars(trim($pythonOutput)) . "</pre>";
        }

    } else {
        // --- Errors found during validation ---
        $errorMessage = implode("<br>", $validationErrors);
        $errorMessage .= "<br><br><b>Note:</b> Please correct the errors above. If you had selected an image, you will need to re-select and crop it.";
        $displayForm = true; // Ensure form is displayed on validation error
        $displayStatusPage = false;
    }
} // End POST processing

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <title>Create Account</title>
    <style>
        /* Global Resets & Body Styles */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            display: flex; min-height: 100vh; width: 100%; align-items: center; justify-content: center;
            background-color: #eff6ff; /* Fallback */
            background-image: linear-gradient(to bottom right, #eff6ff, #e0e7ff);
            padding: 1.5rem; line-height: 1.6; color: #333;
        }
        .container {
            width: 100%; max-width: 750px; margin: 20px auto; background-color: #fff;
            padding: 30px; border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Form Styles */
        .combined-form h1, #status-container h1 {
            margin-bottom: 2rem; text-align: center; font-size: 1.875rem; line-height: 2.25rem; font-weight: 700; color: #1f2937;
        }
        .form-section { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #eee; }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .input-group > * + * { margin-top: 0.5rem; }
        .input-label { display: block; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .input-wrapper { overflow: hidden; border-radius: 0.5rem; border: 1px solid #e5e7eb; background-color: #ffffff; padding: 0.75rem 1rem; transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .input-wrapper:focus-within { border-color: #357AFF; box-shadow: 0 0 0 1px #357AFF; }
        .input-field { width: 100%; background-color: transparent; font-size: 1rem; line-height: 1.5rem; outline: none; border: none; }
        .input-field::placeholder { color: #9ca3af; }

        /* Image Upload Styles */
        .combined-form input[type="file"] { display: block; margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .combined-form input[type="file"]::file-selector-button { margin-right: 15px; border: none; background: #007bff; padding: 10px 15px; border-radius: 4px; color: #fff; cursor: pointer; transition: background .2s ease-in-out; }
        .combined-form input[type="file"]::file-selector-button:hover { background: #0056b3; }
        .crop-area-container { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
        .crop-area { flex: 2; min-width: 300px; }
        #image-to-crop-container { width: 100%; max-height: 400px; background-color: #eee; min-height: 250px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #888; position: relative; }
        #image-to-crop { display: block; max-width: 100%; opacity: 0; transition: opacity 0.3s; }
        #image-to-crop.loaded { opacity: 1; }
        .preview-area { flex: 1; min-width: 150px; text-align: center; }
        #crop-preview-container { width: 150px; height: 150px; overflow: hidden; margin: 10px auto; border: 1px solid #ccc; border-radius: 50%; background-color: #f8f8f8; }
        .cropper-view-box, .cropper-face { border-radius: 50%; }
        .cropper-view-box { outline: inherit; }

        /* Message Area Styles */
        .message-area { margin-top: 1rem; margin-bottom: 1.5rem; padding: 15px; border-radius: 0.5rem; text-align: left; border: 1px solid transparent; font-size: 0.95em; }
        .message-area.validation-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; } /* For validation errors */
        .message-area.processing-error { background-color: #fff3cd; color: #856404; border-color: #ffeeba; } /* For errors during processing (yellowish) */
        .message-area.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; } /* For full success */
        .message-area b { font-weight: 700; }
        .message-area pre { background-color: #e9ecef; padding: 10px; border: 1px solid #dee2e6; text-align: left; white-space: pre-wrap; word-wrap: break-word; margin-top: 10px; max-height: 200px; overflow-y: auto; font-size: 0.9em; color: #495057;}

        /* Submit Button */
        .submit-button-container { text-align: center; margin-top: 2rem; }
        .submit-button { width: auto; padding: 0.8rem 2.5rem; border-radius: 0.5rem; background-color: #28a745; font-size: 1.1rem; line-height: 1.5rem; font-weight: 600; color: #ffffff; border: none; cursor: pointer; transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out; }
        .submit-button:hover { background-color: #218838; }
        .submit-button:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #28a745; }
        .submit-button:disabled { background-color: #ccc; cursor: not-allowed; opacity: 0.7; }

        /* Status Page Links */
        .status-links { text-align: center; margin-top: 20px; font-size: 0.9em; }
        .status-links a { color: #007bff; text-decoration: none; margin: 0 10px; }
        .status-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">

    <?php // --- DISPLAY COMBINED SIGNUP/UPLOAD FORM ---
    if ($displayForm): ?>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="combined-form" novalidate class="combined-form" enctype="multipart/form-data">
        <h1>Create Your Account</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="message-area validation-error">
                 <?php echo $errorMessage; // Contains safe HTML ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Account Information</h2>
            <div class="form-grid">
                <div class="input-group">
                    <label for="first_name" class="input-label">First Name</label>
                    <div class="input-wrapper">
                        <input required id="first_name" name="first_name" type="text" placeholder="Enter first name" class="input-field" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="input-group">
                    <label for="last_name" class="input-label">Last Name</label>
                    <div class="input-wrapper">
                        <input required id="last_name" name="last_name" type="text" placeholder="Enter last name" class="input-field" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" />
                    </div>
                </div>
                 <div class="input-group">
                    <label for="role" class="input-label">Role</label>
                    <div class="input-wrapper">
                        <input required id="role" name="role" type="text" placeholder="e.g., Student, Teacher" class="input-field" value="<?php echo htmlspecialchars($_POST['role'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="input-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <input required id="email" name="email" type="email" placeholder="Enter email" class="input-field" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="input-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="input-wrapper">
                        <input required id="password" name="password" type="password" placeholder="Min 8 characters" class="input-field" />
                    </div>
                </div>
                <div class="input-group">
                    <label for="confirmPassword" class="input-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <input required id="confirmPassword" name="confirmPassword" type="password" placeholder="Re-enter password" class="input-field" />
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
             <h2>Profile Picture</h2>
             <div>
                 <label for="image-input" class="input-label">Select & Crop Image (Required)</label>
                 <input type="file" id="image-input" accept="image/png, image/jpeg, image/jpg, image/gif">
             </div>
             <div class="crop-area-container">
                 <div class="crop-area">
                     <label class="input-label">Crop Your Image (Square)</label>
                     <div id="image-to-crop-container"><span style="padding: 10px;">Select an image file above</span></div>
                     <img id="image-to-crop" alt="Image to crop" style="display:none;"> </div>
                 <div class="preview-area">
                     <label class="input-label">Preview</label>
                     <div id="crop-preview-container"></div>
                 </div>
            </div>
         </div>

        <input type="hidden" name="cropped_image_data" id="cropped-image-data">

        <div class="submit-button-container">
            <button type="submit" id="submit-button" class="submit-button" disabled>Create Account</button>
         </div>
<!-- 
         <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9em;">
             Already have an account? <a href="./index.php" style="color: #007bff; text-decoration: none;">Sign in</a>
         </p> -->

    </form>
    <?php // --- DISPLAY STATUS PAGE (After Processing Attempt) ---
    elseif ($displayStatusPage): ?>
    <div id="status-container">
         <h1>Account Creation Status</h1>
         <div class="message-area <?php echo $processingErrorOccurred ? 'processing-error' : 'success'; ?>">
             <?php echo $statusMessage; // Contains safe HTML and potentially <pre> block ?>
         </div>
         <p class="status-links">
              <a href="./manage-users.php">Go Home</a>
              <?php
                  // Link to profile only if user creation definitely succeeded
                //   if ($processedUserId && !$processingErrorOccurred) { // Or adjust based on your definition of success
                //       echo " | <a href='/profile.php?user_id=" . $processedUserId . "'>View Profile</a>";
                //   }
              ?>
         </p>
     </div>
     <?php endif; ?>

</div> <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const imageInput = document.getElementById('image-input');
        const imageElement = document.getElementById('image-to-crop');
        const imageContainer = document.getElementById('image-to-crop-container');
        const initialMessageSpan = imageContainer.querySelector('span'); // Get the initial message span
        const previewContainer = document.getElementById('crop-preview-container');
        const croppedImageDataInput = document.getElementById('cropped-image-data');
        const submitButton = document.getElementById('submit-button');
        const form = document.getElementById('combined-form');

        if (!imageInput || !imageElement || !previewContainer || !croppedImageDataInput || !submitButton || !form) {
             console.error("One or more form elements required for image cropping are missing!");
             if(submitButton) submitButton.disabled = true;
             return;
        }

        let cropper = null;
        let imageSelectedAndCropped = false;

        function resetCropperState() {
            if (cropper) cropper.destroy();
            cropper = null;
            imageElement.src = '';
            imageElement.style.display = 'none'; // Hide img tag
            imageElement.classList.remove('loaded'); // Remove loaded class
            imageElement.style.opacity = '0';
            if(imageContainer) {
                imageContainer.style.border = '1px dashed #ccc';
                imageContainer.style.minHeight = '250px';
                imageContainer.style.backgroundColor = '#eee';
                if(initialMessageSpan) initialMessageSpan.style.display = 'inline'; // Show initial message
            }
            if(previewContainer) previewContainer.innerHTML = '';
            croppedImageDataInput.value = '';
            submitButton.disabled = true;
            imageSelectedAndCropped = false;
            console.log("Cropper state reset.");
        }

        function updateCroppedData() {
            if (!cropper) return false;
            
            try {
                const canvas = cropper.getCroppedCanvas({
                    width: 400, height: 400, fillColor: '#fff',
                    imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
                });
                
                if (!canvas) {
                    console.error("Could not get cropped canvas");
                    return false;
                }
                
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                croppedImageDataInput.value = dataUrl;
                imageSelectedAndCropped = true;
                submitButton.disabled = false;
                console.log("Cropped data updated and button enabled");
                return true;
            } catch (error) {
                console.error("Error generating cropped data:", error);
                return false;
            }
        }

        imageInput.addEventListener('change', (event) => {
            const files = event.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file (JPEG, PNG, GIF).');
                    imageInput.value = ''; 
                    resetCropperState(); 
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    alert('Please select an image file smaller than 5MB.');
                    imageInput.value = ''; 
                    resetCropperState(); 
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    if (cropper) cropper.destroy();

                    imageElement.src = e.target.result;
                    imageElement.style.display = 'block'; // Show img tag
                    imageElement.classList.add('loaded'); // Add loaded class
                    
                    if (imageContainer) {
                        imageContainer.style.border = 'none';
                        imageContainer.style.minHeight = 'auto';
                        imageContainer.style.backgroundColor = 'transparent';
                        if (initialMessageSpan) initialMessageSpan.style.display = 'none'; // Hide initial message
                    }

                    imageElement.onload = () => {
                        cropper = new Cropper(imageElement, {
                            aspectRatio: 1 / 1, 
                            viewMode: 1, 
                            dragMode: 'move',
                            background: false, 
                            preview: '#crop-preview-container',
                            responsive: true, 
                            restore: false, 
                            checkOrientation: false, 
                            modal: true, 
                            guides: true, 
                            center: true,
                            highlight: false, 
                            cropBoxMovable: true, 
                            cropBoxResizable: true, 
                            toggleDragModeOnDblclick: false,
                            crop: function(event) {
                                // Update on each crop change (continuously)
                                updateCroppedData();
                            },
                            ready: function() {
                                console.log("Cropper ready.");
                                // Ensure we have initial cropped data as soon as cropper is ready
                                updateCroppedData();
                            }
                        });
                    };
                    
                    imageElement.onerror = () => { 
                        alert('Failed to load image.'); 
                        resetCropperState(); 
                    };
                };
                
                reader.onerror = () => { 
                    alert('Failed to read file.'); 
                    resetCropperState(); 
                };
                
                reader.readAsDataURL(file);
            } else {
                resetCropperState();
            }
        });

        form.addEventListener('submit', (event) => {
            if (!imageSelectedAndCropped || !cropper) {
                alert('Please select and crop a profile picture before submitting.');
                event.preventDefault();
                return;
            }
            
            try {
                // Ensure we have the most current cropped data
                if (!updateCroppedData()) {
                    throw new Error("Failed to update cropped image data");
                }
                
                console.log("Submitting form with cropped data...");
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
                // Let the form submission proceed
            } catch (error) {
                console.error("Error during image cropping/submission:", error);
                alert('Error preparing image for upload: ' + error.message);
                event.preventDefault();
            }
        });
    });
</script>

</body>
</html>
<?php ob_end_flush(); // Send final output ?>