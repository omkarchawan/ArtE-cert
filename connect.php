<?php
// Database connection
$host = 'localhost';
$user = 'root';          // replace with your DB username
$password = '';          // replace with your DB password
$dbname = 'artecert';    // replace with your DB name

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $whatsapp = $conn->real_escape_string($_POST['whatsapp']);

    // Handle file upload
    if (isset($_FILES['artwork_path']) && $_FILES['artwork_path']['error'] === 0) {
        $fileTmp = $_FILES['artwork_path']['tmp_name'];
        $fileName = basename($_FILES['artwork_path']['name']);
        $fileSize = $_FILES['artwork_path']['size'];
        $fileType = mime_content_type($fileTmp);

        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($fileType, $allowedTypes) || $fileSize > 2 * 1024 * 1024) {
            die("Invalid file type or size.");
        }

        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $targetPath = $uploadDir . uniqid() . "_" . $fileName;
        if (move_uploaded_file($fileTmp, $targetPath)) {
            $stmt = $conn->prepare("INSERT INTO registration (name, email, whatsapp, artwork_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $whatsapp, $targetPath);

            if ($stmt->execute()) {
                echo "success";
            } else {
                echo "Database error.";
            }
            $stmt->close();
        } else {
            echo "File upload failed.";
        }
    } else {
        echo "Artwork file missing or error.";
    }
}

$conn->close();
?>
