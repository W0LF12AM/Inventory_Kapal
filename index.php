<?php 
include 'koneksi.php'; 

// Mengambil ID Vessel dari URL
$vessel_id = isset($_GET['vessel_id']) ? $_GET['vessel_id'] : '';

// --- LOGIKA SQL AMAN ---
$filter = "WHERE 1=1";
$filter_logs = ""; 
if ($vessel_id) {
    $filter .= " AND vessel_id = '$vessel_id'";
    $filter_logs = " AND i.vessel_id = '$vessel_id'";
    
    $v_query = mysqli_query($conn, "SELECT vessel_name FROM vessels WHERE id = '$vessel_id'");
    $v_data = mysqli_fetch_assoc($v_query);
    $display_title = $v_data['vessel_name'];
} else {
    $display_title = "Global Fleet Overview";
}

// 1. Data Statistik (Card)
$stats_q = mysqli_query($conn, "SELECT 
    COUNT(*) as total_items, 
    SUM(current_qty) as total_qty,
    SUM(current_qty * price) as total_value 
    FROM inventory $filter");
$inv_stats = mysqli_fetch_assoc($stats_q);

// 2. Data Total Armada
$count_vessels = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM vessels"));

// 3. Data Stok Kritis (LIMIT DITINGKATKAN AGAR SCROLL BERFUNGSI)
$low_stock_query = "SELECT i.*, v.vessel_name 
                    FROM inventory i 
                    JOIN vessels v ON i.vessel_id = v.id 
                    $filter AND i.current_qty < 5 
                    ORDER BY i.current_qty ASC LIMIT 20";
$low_stock = mysqli_query($conn, $low_stock_query);

// 4. Data Aktivitas Stok Terbaru (LIMIT DITINGKATKAN AGAR SCROLL BERFUNGSI)
$recent_logs_query = "SELECT l.*, i.part_name, v.vessel_name 
                      FROM stock_logs l
                      JOIN inventory i ON l.inventory_id = i.id
                      JOIN vessels v ON i.vessel_id = v.id
                      WHERE 1=1 $filter_logs
                      ORDER BY l.created_at DESC LIMIT 20";
$recent_logs = mysqli_query($conn, $recent_logs_query);

// 5. Data Chart
$count_aman = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory $filter AND current_qty >= 5"));
$count_kritis = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory $filter AND current_qty < 5"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $display_title; ?> | SMS Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-main: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --accent: #2563eb;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .navbar { background: #ffffff; border-bottom: 1px solid var(--border-color); padding: 1rem 0; }
        .card { border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted); letter-spacing: 0.05em; }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin-top: 4px; }
        
        .icon-shape {
            width: 40px; height: 40px;
            background: #f1f5f9; color: var(--accent);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }

        /* CUSTOM SCROLLABLE AREA */
        .scrollable-table {
            max-height: 350px; /* Ketinggian box sebelum muncul scroll */
            overflow-y: auto;
        }

        /* Custom Scrollbar Styling (Webkit) */
        .scrollable-table::-webkit-scrollbar { width: 6px; }
        .scrollable-table::-webkit-scrollbar-track { background: #f1f5f9; }
        .scrollable-table::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .scrollable-table::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .table thead th {
            background: #f8fafc; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-muted); padding: 12px 15px;
            position: sticky; top: 0; z-index: 10; /* Biar header tabel ga ikut ke-scroll */
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody td { padding: 12px 15px; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; }
        .badge-custom { padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
        .bg-red-soft { background-color: #fef2f2; color: #dc2626; }
        .bg-green-soft { background-color: #f0fdf4; color: #16a34a; }
    </style>
</head>
<body>

<nav class="navbar sticky-top mb-4">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="index.php">
            <i data-lucide="anchor" class="me-2 text-primary"></i> SHIP MANAGEMENT
        </a>
        <div class="d-flex align-items-center gap-3">
            <form action="" method="GET">
                <select name="vessel_id" class="form-select form-select-sm border-light shadow-sm" style="min-width: 200px;" onchange="this.form.submit()">
                    <option value="">Semua Unit Kapal</option>
                    <?php
                    $res = mysqli_query($conn, "SELECT * FROM vessels");
                    while($row = mysqli_fetch_assoc($res)) {
                        $selected = ($vessel_id == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>{$row['vessel_name']}</option>";
                    }
                    ?>
                </select>
            </form>
            <?php if($vessel_id): ?>
                <a href="inventory.php?vessel_id=<?php echo $vessel_id; ?>" class="btn btn-primary btn-sm rounded-3 px-3">Inventory List</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-1"><?php echo $display_title; ?></h2>
        <p class="text-muted small">Update terakhir kondisi ketersediaan sparepart armada.</p>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card p-4">
                <div class="icon-shape"><i data-lucide="layers"></i></div>
                <div class="stat-label">Total Jenis Part</div>
                <div class="stat-value"><?php echo number_format($inv_stats['total_items'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4">
                <div class="icon-shape" style="color: #10b981; background: #ecfdf5;"><i data-lucide="database"></i></div>
                <div class="stat-label">Total Stok Fisik</div>
                <div class="stat-value"><?php echo number_format($inv_stats['total_qty'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4">
                <div class="icon-shape" style="color: #f59e0b; background: #fffbeb;"><i data-lucide="wallet"></i></div>
                <div class="stat-label">Estimasi Nilai Aset</div>
                <div class="stat-value" style="font-size: 1.2rem;">Rp <?php echo number_format($inv_stats['total_value'] ?? 0, 0, ',', '.'); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4">
                <div class="icon-shape" style="color: #6366f1; background: #eef2ff;"><i data-lucide="ship"></i></div>
                <div class="stat-label">Jumlah Armada</div>
                <div class="stat-value"><?php echo $count_vessels['total']; ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Stock Table (SCROLLABLE) -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white py-3 px-4 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Stok Kritis (< 5)</h6>
                    <i data-lucide="alert-triangle" class="text-danger" size="18"></i>
                </div>
                <div class="table-responsive scrollable-table">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Sparepart</th>
                                <th>Kapal</th>
                                <th class="text-center">Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($low_stock) > 0): ?>
                                <?php while($ls = mysqli_fetch_assoc($low_stock)): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo $ls['part_name']; ?></td>
                                    <td class="text-muted"><?php echo $ls['vessel_name']; ?></td>
                                    <td class="text-center"><span class="badge-custom bg-red-soft"><?php echo $ls['current_qty']; ?> UNIT</span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted small">Semua stok terpantau aman.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Adjustment Logs Table (SCROLLABLE) -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white py-3 px-4 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Aktivitas Stok Terbaru</h6>
                    <i data-lucide="history" class="text-primary" size="18"></i>
                </div>
                <div class="table-responsive scrollable-table">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Item / Kapal</th>
                                <th class="text-center">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($recent_logs) > 0): ?>
                                <?php while($log = mysqli_fetch_assoc($recent_logs)): 
                                    $is_in = $log['type'] == 'IN';
                                ?>
                                <tr>
                                    <td class="text-muted small"><?php echo date('d/m H:i', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <div class="fw-medium"><?php echo $log['part_name']; ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo $log['vessel_name']; ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-custom <?php echo $is_in ? 'bg-green-soft' : 'bg-red-soft'; ?>">
                                            <?php echo ($is_in ? '+' : '-') . $log['qty_change']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted small">Belum ada aktivitas tercatat.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card p-4">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h6 class="fw-bold mb-1">Rasio Kesehatan Stok</h6>
                        <p class="text-muted small mb-4">Perbandingan item aman vs kritis</p>
                        <div class="d-flex align-items-center mb-2">
                            <div style="width: 12px; height: 12px; border-radius: 3px; background: #2563eb;" class="me-2"></div>
                            <span class="small text-muted">Aman: <strong><?php echo $count_aman['total']; ?></strong></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width: 12px; height: 12px; border-radius: 3px; background: #ef4444;" class="me-2"></div>
                            <span class="small text-muted">Kritis: <strong><?php echo $count_kritis['total']; ?></strong></span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div style="height: 120px;">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="mt-5 py-4 text-center text-muted small border-top bg-white">
    &copy; <?php echo date('Y'); ?> Ship Management System - Stock Auditor Version
</footer>

<script>
    lucide.createIcons();

    const ctx = document.getElementById('inventoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Status Stok'],
            datasets: [
                {
                    label: 'Aman',
                    data: [<?php echo $count_aman['total']; ?>],
                    backgroundColor: '#2563eb',
                    borderRadius: 6,
                    barThickness: 30
                },
                {
                    label: 'Kritis',
                    data: [<?php echo $count_kritis['total']; ?>],
                    backgroundColor: '#ef4444',
                    borderRadius: 6,
                    barThickness: 30
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { display: false, stacked: true },
                y: { display: false, stacked: true }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>