<?php
include 'koneksi.php';

// Proses Simpan Data
if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['vessel_name']);
    $type = mysqli_real_escape_string($conn, $_POST['vessel_type']);
    
    $query = mysqli_query($conn, "INSERT INTO vessels (vessel_name, vessel_type) VALUES ('$name', '$type')");
    
    if($query) {
        header("Location: index.php");
    } else {
        $error = "Gagal menambahkan data. Silakan coba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Armada Baru | SMS</title>
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --bg-canvas: #f1f5f9;
            --primary-blue: #2563eb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .form-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            padding: 24px;
        }

        .card-header-custom {
            text-align: center;
            margin-bottom: 2rem;
        }

        .icon-circle {
            width: 56px;
            height: 56px;
            background-color: #eff6ff;
            color: var(--primary-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border: none;
            margin-bottom: 12px;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-link-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .btn-link-back:hover {
            color: var(--text-dark);
        }

        .alert {
            border-radius: 10px;
            font-size: 0.85rem;
            padding: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="card">
        <div class="card-header-custom">
            <div class="icon-circle">
                <i data-lucide="plus" size="28"></i>
            </div>
            <h4 class="fw-bold mb-1">Registrasi Armada</h4>
            <p class="text-muted small">Tambahkan unit kapal atau tugboat baru ke dalam sistem.</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i data-lucide="alert-circle" size="16" class="me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label">
                    <i data-lucide="info" size="14"></i> Nama Kapal
                </label>
                <input type="text" name="vessel_name" class="form-control" placeholder="Contoh: TB. MARITIM JAYA 01" required>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i data-lucide="layers" size="14"></i> Tipe Armada
                </label>
                <select name="vessel_type" class="form-select">
                    <option value="Vessel">Vessel (Kapal Barang/Lainnya)</option>
                    <option value="Tugboat">Tugboat (Kapal Tunda)</option>
                    <option value="Barge">Barge (Tongkang)</option>
                </select>
            </div>

            <div class="pt-2">
                <button type="submit" name="submit" class="btn btn-primary shadow-sm">
                    Simpan Data Armada
                </button>
                <a href="index.php" class="btn-link-back">
                    <i data-lucide="chevron-left" size="16"></i> Kembali ke Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <p class="text-center text-muted mt-4 small" style="font-size: 0.75rem;">
        &copy; <?php echo date('Y'); ?> Ship Management System
    </p>
</div>

<!-- Lucide Icons -->
<script>
    lucide.createIcons();
</script>

</body>
</html>