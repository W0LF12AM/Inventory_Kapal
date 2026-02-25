<?php
include 'koneksi.php';
require_once 'SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if (isset($_POST['import'])) {
    $inventory_id = $_POST['inventory_id'];
    $vessel_id = $_POST['vessel_id'];
    
    if ($xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name'])) {
        
        // Mulai Transaction agar data konsisten
        mysqli_begin_transaction($conn);

        try {
            $rows = $xlsx->rows();
            
            // Loop mulai dari baris ke-2 (asumsi baris 1 adalah header)
            for ($i = 1; $i < count($rows); $i++) {
                $type = strtoupper(trim($rows[$i][0])); // IN atau OUT
                $qty_change = (int)$rows[$i][1];
                $remarks = mysqli_real_escape_string($conn, $rows[$i][2]);

                if (empty($type) || $qty_change <= 0) continue;

                // 1. Ambil stok terakhir dari database
                $q_current = mysqli_query($conn, "SELECT current_qty FROM inventory WHERE id = '$inventory_id' FOR UPDATE");
                $data_inv = mysqli_fetch_assoc($q_current);
                $previous_qty = $data_inv['current_qty'];

                // 2. Hitung stok baru
                if ($type == 'IN') {
                    $new_qty = $previous_qty + $qty_change;
                } else {
                    $new_qty = $previous_qty - $qty_change;
                }

                // 3. Update tabel inventory
                mysqli_query($conn, "UPDATE inventory SET current_qty = '$new_qty' WHERE id = '$inventory_id'");

                // 4. Catat ke stock_logs
                $query_log = "INSERT INTO stock_logs (inventory_id, type, qty_change, previous_qty, current_qty, remarks, created_at) 
                              VALUES ('$inventory_id', '$type', '$qty_change', '$previous_qty', '$new_qty', '$remarks', NOW())";
                mysqli_query($conn, $query_log);
            }

            mysqli_commit($conn);
            header("Location: log_stok.php?id=$inventory_id&v_id=$vessel_id&status=success");
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            die("Error saat import: " . $e->getMessage());
        }

    } else {
        echo SimpleXLSX::parseError();
    }
}