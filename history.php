<?php
include 'koneksi.php';

// Ambil ID inventory dan ID vessel dari URL
$inventory_id = isset($_GET['id']) ? $_GET['id'] : '';
$vessel_id = isset($_GET['v_id']) ? $_GET['v_id'] : '';

if (!$inventory_id) {
    die("ID Inventory tidak ditemukan.");
}

// 1. Ambil data detail barangnya
$query_item = mysqli_query($conn, "SELECT i.*, m.component_name, v.vessel_name 
                                   FROM inventory i 
                                   JOIN main_components m ON i.main_component_id = m.id 
                                   JOIN vessels v ON i.vessel_id = v.id
                                   WHERE i.id = '$inventory_id'");
$item = mysqli_fetch_assoc($query_item);

// 2. Ambil data log/riwayat perubahan stok
$query_logs = mysqli_query($conn, "SELECT * FROM stock_logs 
                                   WHERE inventory_id = '$inventory_id' 
                                   ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Log - <?php echo $item['part_name']; ?></title>
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --bg-canvas: #f8fafc;
            --primary: #2563eb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
        }

        /* Navbar */
        .navbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--text-dark) !important;
            letter-spacing: -0.5px;
        }

        /* Header Info Card */
        .info-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .label-pill {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 4px;
        }

        .part-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .meta-item {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 8px;
        }

        /* Stock Stats */
        .stock-badge-container {
            background: #eff6ff;
            border-left: 4px solid var(--primary);
            padding: 12px 20px;
            border-radius: 8px;
        }

        /* Table Log Card */
        .card-table {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .table thead th {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            background-color: #fafafa;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .table tbody td {
            padding: 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #fcfcfc;
        }

        /* Status & Badges */
        .badge-type {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .type-in { background-color: #f0fdf4; color: #16a34a; }
        .type-out { background-color: #fef2f2; color: #dc2626; }

        .qty-box {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .btn-back {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #f8fafc;
            color: var(--text-dark);
            border-color: #cbd5e1;
        }

        .remarks-text {
            color: var(--text-muted);
            line-height: 1.5;
            max-width: 300px;
        }
    </style>
</head>
<body>

<nav class="navbar sticky-top mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i data-lucide="anchor" class="me-2 text-primary" size="24"></i> 
            SHIP MANAGEMENT
        </a>
        <a href="inventory.php?vessel_id=<?php echo $vessel_id; ?>" class="btn-back d-flex align-items-center shadow-sm">
            <i data-lucide="chevron-left" size="16" class="me-1"></i> Kembali ke Inventory
        </a>
    </div>
</nav>

<div class="container-fluid px-4">
    <!-- Header Info Item -->
    <div class="info-card">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="label-pill">Transaction History</span>
                <h1 class="part-title"><?php echo $item['part_name']; ?></h1>
                
                <div class="d-flex flex-wrap gap-2">
                    <div class="meta-item">
                        <i data-lucide="hash" size="14" class="me-2 text-primary"></i> 
                        <?php echo $item['part_number'] ?: 'No Part Number'; ?>
                    </div>
                    <div class="meta-item">
                        <i data-lucide="box" size="14" class="me-2 text-primary"></i> 
                        <?php echo $item['component_name']; ?>
                    </div>
                    <div class="meta-item">
                        <i data-lucide="ship" size="14" class="me-2 text-primary"></i> 
                        <?php echo $item['vessel_name']; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="stock-badge-container d-flex justify-content-between align-items-center">
                    <div>
                        <span class="label-pill mb-0 text-primary">Stok Saat Ini</span>
                        <h2 class="fw-bold mb-0"><?php echo $item['current_qty']; ?> <small class="fw-medium text-muted fs-6">Unit</small></h2>
                    </div>
                    <i data-lucide="layers" class="text-primary opacity-25" size="32"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- History Log Table -->
    <div class="card-table">
        <div class="p-3 border-bottom bg-white">
            <h6 class="fw-bold mb-0 d-flex align-items-center">
                <i data-lucide="list-ordered" class="me-2 text-primary" size="18"></i>
                Log Riwayat Stok
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Tanggal & Waktu</th>
                        <th>Tipe</th>
                        <th class="text-center">Penyesuaian</th>
                        <th class="text-center">Stok Awal</th>
                        <th class="text-center">Stok Akhir</th>
                        <th class="pe-4">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (mysqli_num_rows($query_logs) > 0) {
                        while($log = mysqli_fetch_assoc($query_logs)): 
                            $isIn = ($log['type'] == 'IN');
                    ?>
                        <tr>
                            <td class="ps-4">
                                <span class="d-block fw-medium"><?php echo date('d M Y', strtotime($log['created_at'])); ?></span>
                                <span class="small text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?> WIB</span>
                            </td>
                            <td>
                                <?php if($isIn): ?>
                                    <span class="badge-type type-in">
                                        <i data-lucide="arrow-down-to-dot" size="14"></i> Stock In
                                    </span>
                                <?php else: ?>
                                    <span class="badge-type type-out">
                                        <i data-lucide="arrow-up-from-dot" size="14"></i> Stock Out
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center qty-box <?php echo $isIn ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($isIn ? '+' : '-') . $log['qty_change']; ?>
                            </td>
                            <td class="text-center text-muted"><?php echo $log['previous_qty']; ?></td>
                            <td class="text-center">
                                <span class="fw-bold px-2 py-1 rounded bg-light"><?php echo $log['current_qty']; ?></span>
                            </td>
                            <td class="pe-4">
                                <div class="remarks-text small italic">
                                    <?php echo $log['remarks'] ? htmlspecialchars($log['remarks']) : '<span class="opacity-50">- No remarks -</span>'; ?>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted small'><i data-lucide='inbox' class='d-block mx-auto mb-2 opacity-25' size='40'></i>Belum ada catatan transaksi untuk item ini.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="mt-5 py-4 border-top text-center text-muted small">
    &copy; <?php echo date('Y'); ?> Ship Management System - Stock Auditor Log
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Lucide Icons
    lucide.createIcons();
</script>
</body>
</html>