<?php
include 'koneksi.php';
$id = $_GET['id'];
$v_id = $_GET['v_id'];

// Ambil data item sekarang
$item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM inventory WHERE id = $id"));

if (isset($_POST['submit'])) {
    $type = $_POST['type']; // IN atau OUT
    $qty_input = $_POST['qty_change'];
    $remarks = $_POST['remarks'];
    $old_qty = $item['current_qty'];

    // Hitung stok baru
    if ($type == 'IN') {
        $new_qty = $old_qty + $qty_input;
    } else {
        $new_qty = $old_qty - $qty_input;
    }

    // 1. Update tabel inventory
    mysqli_query($conn, "UPDATE inventory SET current_qty = $new_qty WHERE id = $id");

    // 2. Catat ke tabel stock_logs
    $sql_log = "INSERT INTO stock_logs (inventory_id, type, qty_change, previous_qty, current_qty, remarks) 
                VALUES ('$id', '$type', '$qty_input', '$old_qty', '$new_qty', '$remarks')";
    mysqli_query($conn, $sql_log);

    header("Location: index.php?vessel_id=$v_id");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Adjustment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card mx-auto shadow" style="max-width: 500px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Stock Adjustment</h5>
        </div>
        <div class="card-body">
            <p><strong>Item:</strong> <?= $item['part_name'] ?> (<?= $item['part_number'] ?>)</p>
            <p><strong>Stok Saat Ini:</strong> <span class="badge bg-secondary"><?= $item['current_qty'] ?></span></p>
            <hr>
            <form method="POST">
                <div class="mb-3">
                    <label>Jenis Perubahan</label>
                    <select name="type" class="form-select" required>
                        <option value="IN text-success">Barang Masuk (+)</option>
                        <option value="OUT text-danger">Barang Keluar / Terpakai (-)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Jumlah (Quantity)</label>
                    <input type="number" name="qty_change" class="form-control" min="1" required>
                </div>
                <div class="mb-3">
                    <label>Keterangan</label>
                    <textarea name="remarks" class="form-control" placeholder="Contoh: Penggantian rutin, Barang baru datang, dsb"></textarea>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="submit" class="btn btn-primary">Update Stok</button>
                    <a href="index.php?vessel_id=<?= $v_id ?>" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>