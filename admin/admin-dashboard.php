<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "artecert");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
    $delete_query = "DELETE FROM registration WHERE id = '$delete_id'";
    mysqli_query($conn, $delete_query);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$result = mysqli_query($conn, "SELECT *, 
    DATE_FORMAT(formatted_time, '%d-%m-%Y %H:%i:%s') AS display_time 
    FROM registration ORDER BY formatted_time DESC");
$participants = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ArtE-cert</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #6a5acd;
            --secondary: #ff7f50;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .logout-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .logout-btn:hover {
            background: #5a4acd;
        }
        .dashboard-header {
            padding: 2rem;
            text-align: center;
            background: white;
            margin: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .dashboard-header h1 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .participants-count {
            font-size: 1.2rem;
            color: var(--dark);
        }
        .participants-container {
            padding: 0 1rem 2rem;
        }
        .participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .participant-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .participant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: var(--primary);
            color: white;
            padding: 1rem;
        }
        .card-header h3 {
            font-size: 1.2rem;
        }
        .card-body {
            padding: 1.5rem;
        }
        .card-field {
            margin-bottom: 1rem;
        }
        .card-field strong {
            display: block;
            color: var(--dark);
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        .button-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .view-artwork-btn,
        .delete-card-btn {
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        .view-artwork-btn {
            background: var(--secondary);
        }
        .view-artwork-btn:hover {
            background: #e66f40;
        }
        .delete-card-btn {
            background: crimson;
        }
        .delete-card-btn:hover {
            background: darkred;
        }
        .timestamp {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #eee;
        }
        .timestamp i {
            color: var(--primary);
        }
        .time-display {
            font-family: monospace;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
        }
        .modal-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100%;
            padding: 2rem;
        }
        .modal-image-container {
            max-width: 90%;
            max-height: 90vh;
            text-align: center;
        }
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 5px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
        }
        .close-modal {
            position: fixed;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 35px;
            cursor: pointer;
            transition: color 0.3s;
        }
        .close-modal:hover {
            color: var(--secondary);
        }
        @media (max-width: 768px) {
            .participants-grid {
                grid-template-columns: 1fr;
            }
            .modal-image {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">ArtE-cert Admin</div>
    <a href="?logout=1" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</nav>

<div class="dashboard-header">
    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
    <div class="participants-count">
        Total Participants: <?= count($participants) ?>
    </div>
</div>

<div class="participants-container">
    <div class="participants-grid">
        <?php foreach ($participants as $participant): ?>
            <div class="participant-card">
                <div class="card-header">
                    <h3><?= htmlspecialchars($participant['name']) ?></h3>
                </div>
                <div class="card-body">
                    <div class="card-field">
                        <strong>Email:</strong>
                        <?= htmlspecialchars($participant['email']) ?>
                    </div>
                    <div class="card-field">
                        <strong>WhatsApp:</strong>
                        <?= htmlspecialchars($participant['whatsapp']) ?>
                    </div>
                    <div class="card-field button-group">
                        <button class="view-artwork-btn"
                                onclick="showArtwork('../uploads/<?= htmlspecialchars(basename($participant['artwork_path'])) ?>')">
                            <i class="fas fa-image"></i> View Artwork
                        </button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this participant?');">
                            <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($participant['id']); ?>">
                            <button type="submit" class="delete-card-btn">
                                <i class="fas fa-trash-alt"></i> Delete Card
                            </button>
                        </form>
                    </div>
                    <div class="card-field timestamp">
                        <i class="far fa-clock"></i>
                        <span class="time-display">
                            <?= htmlspecialchars($participant['display_time'] ?? 'Date not available') ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="artworkModal" class="modal">
    <span class="close-modal" onclick="closeModal()">&times;</span>
    <div class="modal-content">
        <div class="modal-image-container">
            <img id="modalArtworkImage" class="modal-image" src="" alt="Artwork">
        </div>
    </div>
</div>

<script>
    function showArtwork(imageUrl) {
        const modal = document.getElementById('artworkModal');
        const modalImg = document.getElementById('modalArtworkImage');
        modalImg.src = '';
        modal.style.display = "block";

        modalImg.onload = function () {};
        modalImg.onerror = function () {
            modalImg.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="%23f5f5f5"/><text x="200" y="150" font-family="Arial" font-size="16" text-anchor="middle" fill="%23ff0000">Image not found: ' + imageUrl + '</text></svg>';
        };
        modalImg.src = imageUrl;
    }

    function closeModal() {
        document.getElementById('artworkModal').style.display = "none";
    }

    window.onclick = function (event) {
        const modal = document.getElementById('artworkModal');
        if (event.target === modal) {
            closeModal();
        }
    };

    document.onkeydown = function (evt) {
        evt = evt || window.event;
        if (evt.key === "Escape") {
            closeModal();
        }
    };
</script>
</body>
</html>
