<?php
include 'koneksi.php';

$vessel_id = isset($_GET['vessel_id']) ? $_GET['vessel_id'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$main_filter = isset($_GET['main_id']) ? mysqli_real_escape_string($conn, $_GET['main_id']) : '';
$sub_filter = isset($_GET['sub_id']) ? mysqli_real_escape_string($conn, $_GET['sub_id']) : '';

// --- LOGIKA AJAX ADJUSTMENT ---
if (isset($_POST['ajax_adjustment'])) {
    $id = $_POST['inventory_id'];
    $type = $_POST['type'];
    $qty_input = $_POST['qty_change'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $item_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_qty FROM inventory WHERE id = $id"));
    $old_qty = $item_data['current_qty'];
    $new_qty = ($type == 'IN') ? ($old_qty + $qty_input) : ($old_qty - $qty_input);

    $update = mysqli_query($conn, "UPDATE inventory SET current_qty = $new_qty WHERE id = $id");
    mysqli_query($conn, "INSERT INTO stock_logs (inventory_id, type, qty_change, previous_qty, current_qty, remarks) VALUES ('$id', '$type', '$qty_input', '$old_qty', '$new_qty', '$remarks')");

    if ($update) {
        echo json_encode(['status' => 'success', 'new_qty' => $new_qty]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// --- LOGIKA BULK INSERT ---
if (isset($_POST['submit_bulk_inventory'])) {
    $v_id = $_POST['vessel_id'];
    $mains = $_POST['bulk_main'];
    $subs = $_POST['bulk_sub'];
    $names = $_POST['bulk_name'];
    $pns = $_POST['bulk_pn'];
    $qtys = $_POST['bulk_qty'];
    $prices = $_POST['bulk_price'];

    for ($i = 0; $i < count($names); $i++) {
        if (empty($names[$i]))
            continue;

        $m_name = mysqli_real_escape_string($conn, $mains[$i]);
        $s_name = mysqli_real_escape_string($conn, $subs[$i]);
        $p_name = mysqli_real_escape_string($conn, $names[$i]);
        $p_num = mysqli_real_escape_string($conn, $pns[$i]);
        $p_qty = $qtys[$i] ?: 0;
        $p_prc = $prices[$i] ?: 0;

        $q_m = mysqli_query($conn, "SELECT id FROM main_components WHERE vessel_id = '$v_id' AND component_name = '$m_name'");
        if (mysqli_num_rows($q_m) > 0) {
            $m_id = mysqli_fetch_assoc($q_m)['id'];
        } else {
            mysqli_query($conn, "INSERT INTO main_components (vessel_id, component_name) VALUES ('$v_id', '$m_name')");
            $m_id = mysqli_insert_id($conn);
        }

        $q_s = mysqli_query($conn, "SELECT id FROM sub_components WHERE main_component_id = '$m_id' AND sub_component_name = '$s_name'");
        if (mysqli_num_rows($q_s) > 0) {
            $s_id = mysqli_fetch_assoc($q_s)['id'];
        } else {
            mysqli_query($conn, "INSERT INTO sub_components (main_component_id, sub_component_name) VALUES ('$m_id', '$s_name')");
            $s_id = mysqli_insert_id($conn);
        }

        mysqli_query($conn, "INSERT INTO inventory (vessel_id, main_component_id, sub_component_id, part_name, part_number, initial_qty, current_qty, price) VALUES ('$v_id', '$m_id', '$s_id', '$p_name', '$p_num', '$p_qty', '$p_qty', '$p_prc')");
    }
    header("Location: inventory.php?vessel_id=$v_id&msg=bulk_success");
    exit();
}

// --- CONFIG PAGINATION ---
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- LOGIKA IMPORT CSV ---
if (isset($_POST['import_csv'])) {
    $v_id = $_POST['vessel_id'];
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    $file_orig_name = $_FILES['csv_file']['name'];
    $main_name = strtoupper(pathinfo($file_orig_name, PATHINFO_FILENAME));

    if ($_FILES['csv_file']['size'] > 0) {
        $file = fopen($file_tmp, "r");
        $q_main = mysqli_query($conn, "SELECT id FROM main_components WHERE vessel_id = '$v_id' AND component_name = '$main_name'");
        if (mysqli_num_rows($q_main) > 0) {
            $main_id_csv = mysqli_fetch_assoc($q_main)['id'];
        } else {
            mysqli_query($conn, "INSERT INTO main_components (vessel_id, component_name) VALUES ('$v_id', '$main_name')");
            $main_id_csv = mysqli_insert_id($conn);
        }

        $current_sub_id = 0;
        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            $col_a = trim($row[0]);
            $col_b = trim($row[1]);
            $col_c = trim($row[2]);
            $col_d = trim($row[3]);
            $qty_val = (isset($row[4]) && is_numeric(trim($row[4]))) ? intval(trim($row[4])) : 0;
            $prc_val = (isset($row[5]) && is_numeric(trim($row[5]))) ? floatval(trim($row[5])) : 0;
            if (empty($col_b))
                continue;

            if (empty($col_a)) {
                $sub_name = mysqli_real_escape_string($conn, $col_b);
                $sub_pn = mysqli_real_escape_string($conn, $col_d);
                mysqli_query($conn, "INSERT INTO sub_components (main_component_id, sub_component_name, part_number) VALUES ('$main_id_csv', '$sub_name', '$sub_pn')");
                $current_sub_id = mysqli_insert_id($conn);
            } else if (is_numeric($col_a)) {
                $part_name = mysqli_real_escape_string($conn, $col_b);
                $part_num = mysqli_real_escape_string($conn, $col_c);
                mysqli_query($conn, "INSERT INTO inventory (vessel_id, main_component_id, sub_component_id, part_name, part_number, initial_qty, current_qty, price) VALUES ('$v_id', '$main_id_csv', '$current_sub_id', '$part_name', '$part_num', '$qty_val', '$qty_val', '$prc_val')");
            }
        }
        fclose($file);
        header("Location: inventory.php?vessel_id=$v_id&msg=import_success");
        exit();
    }
}

// --- LOGIKA CRUD LAINNYA ---
if (isset($_POST['submit_main_component'])) {
    $name = mysqli_real_escape_string($conn, $_POST['component_name']);
    $pn = $_POST['part_number'];
    mysqli_query($conn, "INSERT INTO main_components (vessel_id, component_name, part_number) VALUES ('$vessel_id', '$name', '$pn')");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_POST['submit_sub_component'])) {
    $main_id_post = $_POST['main_component_id'];
    $name = mysqli_real_escape_string($conn, $_POST['sub_name']);
    $pn = $_POST['part_number'];
    mysqli_query($conn, "INSERT INTO sub_components (main_component_id, sub_component_name, part_number) VALUES ('$main_id_post', '$name', '$pn')");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_POST['submit_sparepart'])) {
    $m_id = $_POST['main_component_id'];
    $s_id = $_POST['sub_component_id'];
    $name = mysqli_real_escape_string($conn, $_POST['part_name']);
    $pn = $_POST['part_number'];
    $qty = $_POST['initial_qty'] ?: 0;
    $price = $_POST['price'] ?: 0;
    mysqli_query($conn, "INSERT INTO inventory (vessel_id, main_component_id, sub_component_id, part_name, part_number, initial_qty, current_qty, price) VALUES ('$vessel_id', '$m_id', '$s_id', '$name', '$pn', '$qty', '$qty', '$price')");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_POST['submit_edit_main_comp'])) {
    $id = $_POST['main_comp_id'];
    $name = mysqli_real_escape_string($conn, $_POST['component_name']);
    $pn = $_POST['part_number'];
    mysqli_query($conn, "UPDATE main_components SET component_name='$name', part_number='$pn' WHERE id='$id'");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_POST['submit_edit_sub_comp'])) {
    $id = $_POST['sub_comp_id'];
    $name = mysqli_real_escape_string($conn, $_POST['sub_name']);
    $pn = $_POST['part_number'];
    mysqli_query($conn, "UPDATE sub_components SET sub_component_name='$name', part_number='$pn' WHERE id='$id'");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_POST['submit_edit_sparepart'])) {
    $id = (int) $_POST['inventory_id'];
    $name = mysqli_real_escape_string($conn, $_POST['part_name']);
    $pn = mysqli_real_escape_string($conn, $_POST['part_number']);
    $price = (float) ($_POST['price'] ?? 0);
    $v_id = mysqli_real_escape_string($conn, $_POST['vessel_id_edit'] ?? $vessel_id);
    mysqli_query($conn, "UPDATE inventory SET part_name='$name', part_number='$pn', price='$price' WHERE id='$id'");
    header("Location: inventory.php?vessel_id=$v_id");
    exit();
}
if (isset($_GET['delete_id'])) {
    mysqli_query($conn, "DELETE FROM inventory WHERE id = '" . $_GET['delete_id'] . "'");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_GET['delete_main_id'])) {
    mysqli_query($conn, "DELETE FROM main_components WHERE id = '" . $_GET['delete_main_id'] . "'");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
if (isset($_GET['delete_sub_id'])) {
    mysqli_query($conn, "DELETE FROM sub_components WHERE id = '" . $_GET['delete_sub_id'] . "'");
    header("Location: inventory.php?vessel_id=$vessel_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | SMS Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg: #f8fafc;
            --primary: #2563eb;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 0;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .accordion-item {
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .accordion-header-flex {
            display: flex;
            align-items: center;
            width: 100%;
            padding-right: 2rem;
        }

        .main-title-area {
            flex: 1;
            display: flex;
            align-items: center;
            font-weight: 700;
            min-width: 0;
        }

        .main-title-area span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .subtotal-area {
            width: 160px;
            text-align: right;
            flex-shrink: 0;
        }

        .subtotal-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #94a3b8;
            display: block;
            line-height: 1;
            margin-bottom: 2px;
        }

        .subtotal-amount {
            font-size: 0.9rem;
            color: #2563eb;
            display: block;
            font-weight: 700;
            font-family: 'Courier New', Courier, monospace;
        }

        .accordion-button:not(.collapsed) {
            background-color: #f8fafc;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
            box-shadow: none;
        }

        .sub-card {
            border: 1px solid #edf2f7;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
            background: #fff;
        }

        .sub-header {
            background: #f1f5f9;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            border-left: 4px solid var(--primary);
        }

        .sub-title {
            font-weight: 700;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table thead th {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #64748b;
            background: #fafafa;
            padding: 12px;
        }

        .table tbody td {
            padding: 12px;
            font-size: 0.85rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .action-link {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border);
            color: #64748b;
            background: #fff;
            transition: 0.2s;
            text-decoration: none;
            cursor: pointer;
        }

        .action-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .action-link.delete:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .badge-stock {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .custom-search-container {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0 15px;
        }

        .custom-search-input {
            border: none !important;
            box-shadow: none !important;
            height: 46px;
            font-size: 0.95rem;
            width: 100%;
            background: transparent;
            outline: none;
        }

        .btn-filter-submit {
            background: #1e293b;
            color: #fff;
            border-radius: 12px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-filter-submit:hover {
            background: #0f172a;
            color: #fff;
        }
    </style>
</head>

<body>

    <nav class="navbar sticky-top mb-4 shadow-sm">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a class="navbar-brand fw-bold" href="index.php"><i data-lucide="anchor" class="me-2 text-primary"></i> SHIP
                MANAGEMENT</a>
            <a href="index.php?vessel_id=<?php echo $vessel_id; ?>"
                class="btn btn-outline-secondary btn-sm rounded-3">Dashboard</a>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="card p-3 mb-4 sticky-top" style="top: 95px;">
                    <label class="small fw-bold text-muted mb-2 text-uppercase d-block text-center">Armada</label>
                    <form action="" method="GET">
                        <select name="vessel_id" class="form-select mb-3 shadow-sm border-light bg-light"
                            onchange="this.form.submit()">
                            <option value="">-- Pilih Armada --</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM vessels");
                            while ($row = mysqli_fetch_assoc($res)) {
                                $sel = ($vessel_id == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['vessel_name']}</option>";
                            }
                            ?>
                        </select>
                    </form>
                    <a href="tambah_vessel.php"
                        class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-3">Registrasi Kapal</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if ($vessel_id):
                    $v_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT vessel_name FROM vessels WHERE id = '$vessel_id'"));
                    ?>
                    <!-- Header -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                        <div>
                            <h2 class="fw-bold mb-0 text-dark"><?php echo $v_data['vessel_name']; ?></h2>
                            <p class="text-muted small mb-0">Manajemen Inventaris</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-success btn-sm fw-bold rounded-3 px-3 shadow-sm"
                                data-bs-toggle="modal" data-bs-target="#modalImport"><i data-lucide="file-up" size="16"></i>
                                Import CSV</button>

                            <!-- UPDATED PDF BUTTON TO OPEN MODAL -->
                            <button class="btn btn-outline-danger btn-sm fw-bold rounded-3 px-3 shadow-sm"
                                data-bs-toggle="modal" data-bs-target="#modalReportRange"><i data-lucide="file-text"
                                    size="16"></i> PDF Report</button>

                            <button class="btn btn-dark btn-sm fw-bold shadow-sm rounded-3 px-3" data-bs-toggle="modal"
                                data-bs-target="#modalBulk"><i data-lucide="layers" size="16"></i> Bulk Table</button>
                            <button class="btn btn-primary btn-sm fw-bold shadow-sm rounded-3 px-3" data-bs-toggle="modal"
                                data-bs-target="#modalMain"><i data-lucide="plus" size="16"></i> Main Induk</button>
                        </div>
                    </div>

                    <!-- COMPLETE FILTER BAR (Same as before) -->
                    <form action="" method="GET" class="card p-3 shadow-sm border-0 mb-4 bg-white"
                        style="border-radius: 16px;">
                        <input type="hidden" name="vessel_id" value="<?php echo $vessel_id; ?>">
                        <div class="row g-2">
                            <div class="col-md-12 mb-2">
                                <div class="custom-search-container shadow-sm">
                                    <i data-lucide="search" size="20" class="text-muted me-2"></i>
                                    <input type="text" name="search" class="custom-search-input"
                                        placeholder="Cari nama barang atau part number..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <select name="main_id" class="form-select shadow-sm border-0 bg-light"
                                    style="height: 48px; border-radius: 12px;" onchange="this.form.submit()">
                                    <option value="">-- Semua Main Induk --</option>
                                    <?php
                                    $q_main_list = mysqli_query($conn, "SELECT id, component_name FROM main_components WHERE vessel_id = '$vessel_id' ORDER BY component_name ASC");
                                    while ($ml = mysqli_fetch_assoc($q_main_list)) {
                                        $selected = ($main_filter == $ml['id']) ? 'selected' : '';
                                        echo "<option value='{$ml['id']}' $selected>{$ml['component_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <select name="sub_id" class="form-select shadow-sm border-0 bg-light"
                                    style="height: 48px; border-radius: 12px;" onchange="this.form.submit()">
                                    <option value="">-- Semua Sub Induk --</option>
                                    <?php
                                    $sub_list_sql = "SELECT s.id, s.sub_component_name, m.component_name 
                                                 FROM sub_components s 
                                                 JOIN main_components m ON s.main_component_id = m.id 
                                                 WHERE m.vessel_id = '$vessel_id'";
                                    if (!empty($main_filter))
                                        $sub_list_sql .= " AND m.id = '$main_filter'";
                                    $sub_list_sql .= " ORDER BY m.component_name, s.sub_component_name ASC";

                                    $q_sub_list = mysqli_query($conn, $sub_list_sql);
                                    while ($sl = mysqli_fetch_assoc($q_sub_list)) {
                                        $selected = ($sub_filter == $sl['id']) ? 'selected' : '';
                                        echo "<option value='{$sl['id']}' $selected>{$sl['component_name']} -> {$sl['sub_component_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-filter-submit w-100 h-100"><i
                                        data-lucide="sliders-horizontal" size="18" class="me-1"></i> Filter</button>
                            </div>
                        </div>
                    </form>

                    <div class="accordion" id="accMain">
                        <?php
                        $where_clause = "WHERE vessel_id = '$vessel_id'";
                        if (!empty($search)) {
                            $where_clause .= " AND (component_name LIKE '%$search%' OR id IN (SELECT main_component_id FROM inventory WHERE part_name LIKE '%$search%' OR part_number LIKE '%$search%'))";
                        }
                        if (!empty($main_filter)) {
                            $where_clause .= " AND id = '$main_filter'";
                        }
                        if (!empty($sub_filter)) {
                            $where_clause .= " AND id IN (SELECT main_component_id FROM sub_components WHERE id = '$sub_filter')";
                        }

                        $total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM main_components $where_clause"))['total'];
                        $total_pages = ceil($total_rows / $limit);
                        $main_res = mysqli_query($conn, "SELECT * FROM main_components $where_clause ORDER BY component_name ASC LIMIT $offset, $limit");

                        while ($main = mysqli_fetch_assoc($main_res)):
                            $main_id = $main['id'];
                            $q_total = mysqli_query($conn, "SELECT SUM(current_qty * price) as total FROM inventory WHERE main_component_id = '$main_id'");
                            $row_total = mysqli_fetch_assoc($q_total);
                            $show_main = (!empty($search) || !empty($main_filter) || !empty($sub_filter)) ? 'show' : '';
                            ?>
                            <div class="accordion-item shadow-sm">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?php echo $show_main ? '' : 'collapsed'; ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#m-<?php echo $main_id; ?>">
                                        <div class="accordion-header-flex">
                                            <div class="main-title-area">
                                                <i data-lucide="package" size="18" class="me-3 text-primary"></i>
                                                <span><?php echo $main['component_name']; ?></span>
                                            </div>
                                            <div class="subtotal-area">
                                                <span class="subtotal-label">Subtotal</span>
                                                <span class="subtotal-amount">Rp
                                                    <?php echo number_format($row_total['total'] ?? 0, 0, ',', '.'); ?></span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="m-<?php echo $main_id; ?>"
                                    class="accordion-collapse collapse <?php echo $show_main; ?>">
                                    <div class="accordion-body p-0 bg-light-subtle">
                                        <div class="p-2 border-bottom d-flex justify-content-end gap-2 bg-white">
                                            <button class="action-link" data-bs-toggle="modal"
                                                data-bs-target="#modalEditMainComp" data-id="<?php echo $main_id; ?>"
                                                data-name="<?php echo $main['component_name']; ?>"
                                                data-number="<?php echo $main['part_number']; ?>"><i data-lucide="edit-2"
                                                    size="14"></i></button>
                                            <button class="btn btn-light btn-sm text-primary fw-bold border"
                                                data-bs-toggle="modal" data-bs-target="#modalSub"
                                                data-main-id="<?php echo $main_id; ?>">+ SUB-INDUK</button>
                                            <a href="inventory.php?vessel_id=<?php echo $vessel_id; ?>&delete_main_id=<?php echo $main_id; ?>"
                                                class="action-link delete" onclick="return confirm('Hapus Main Induk?')"><i
                                                    data-lucide="trash-2" size="14"></i></a>
                                        </div>
                                        <div class="sub-accordion-wrapper p-3">
                                            <?php
                                            $sub_query = "SELECT * FROM sub_components WHERE main_component_id = '$main_id'";
                                            if (!empty($sub_filter))
                                                $sub_query .= " AND id = '$sub_filter'";
                                            $sub_res = mysqli_query($conn, $sub_query . " ORDER BY sub_component_name ASC");
                                            while ($sub = mysqli_fetch_assoc($sub_res)):
                                                $sub_id = $sub['id'];
                                                ?>
                                                <div class="sub-card shadow-sm">
                                                    <div class="sub-header" data-bs-toggle="collapse"
                                                        data-bs-target="#sub-<?php echo $sub_id; ?>">
                                                        <div class="sub-title"><i data-lucide="chevron-down" size="14"
                                                                class="me-1"></i> <?php echo $sub['sub_component_name']; ?></div>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-white btn-xs border py-0 px-2"
                                                                data-bs-toggle="modal" data-bs-target="#modalEditSubComp"
                                                                data-id="<?php echo $sub_id; ?>"
                                                                data-name="<?php echo $sub['sub_component_name']; ?>"
                                                                data-number="<?php echo $sub['part_number']; ?>"
                                                                onclick="event.stopPropagation();"><i data-lucide="settings"
                                                                    size="10"></i></button>
                                                            <button class="btn btn-primary btn-xs py-0 px-2 fw-bold"
                                                                style="font-size:0.6rem;" data-bs-toggle="modal"
                                                                data-bs-target="#modalPart" data-main-id="<?php echo $main_id; ?>"
                                                                data-sub-id="<?php echo $sub_id; ?>"
                                                                data-sub-name="<?php echo $sub['sub_component_name']; ?>"
                                                                onclick="event.stopPropagation();">+ ITEM</button>
                                                            <a href="inventory.php?vessel_id=<?php echo $vessel_id; ?>&delete_sub_id=<?php echo $sub_id; ?>"
                                                                class="text-danger"
                                                                onclick="event.stopPropagation(); return confirm('Hapus Sub Induk?')"><i
                                                                    data-lucide="x-circle" size="14"></i></a>
                                                        </div>
                                                    </div>
                                                    <div id="sub-<?php echo $sub_id; ?>" class="collapse show">
                                                        <div class="table-responsive">
                                                            <table class="table table-hover mb-0 align-middle">
                                                                <thead>
                                                                    <tr>
                                                                        <th class="ps-4">Item Name</th>
                                                                        <th>P/N</th>
                                                                        <th class="text-center">Stok</th>
                                                                        <th>Harga</th>
                                                                        <th>Total</th>
                                                                        <th class="text-end pe-4">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    $p_query = "SELECT * FROM inventory WHERE sub_component_id = '$sub_id'";
                                                                    if (!empty($search))
                                                                        $p_query .= " AND (part_name LIKE '%$search%' OR part_number LIKE '%$search%')";
                                                                    $p_res = mysqli_query($conn, $p_query);
                                                                    while ($part = mysqli_fetch_assoc($p_res)):
                                                                        $stock_c = ($part['current_qty'] < 5) ? 'bg-danger text-white' : 'bg-light text-dark';
                                                                        ?>
                                                                        <tr>
                                                                            <td class="ps-4 fw-medium"><?php echo $part['part_name']; ?>
                                                                            </td>
                                                                            <td class="text-muted"><?php echo $part['part_number']; ?>
                                                                            </td>
                                                                            <td class="text-center"><span
                                                                                    id="stock-badge-<?php echo $part['id']; ?>"
                                                                                    class="badge-stock <?php echo $stock_c; ?>"><?php echo $part['current_qty']; ?></span>
                                                                            </td>
                                                                            <td>Rp
                                                                                <?php echo number_format($part['price'], 0, ',', '.'); ?>
                                                                            </td>
                                                                            <td class="fw-bold">Rp
                                                                                <?php echo number_format($part['current_qty'] * $part['price'], 0, ',', '.'); ?>
                                                                            </td>
                                                                            <td class="text-end pe-4">
                                                                                <div class="d-flex justify-content-end gap-1">
                                                                                    <button title="Adjust" class="action-link"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#modalAdjust"
                                                                                        data-id="<?php echo $part['id']; ?>"
                                                                                        data-name="<?php echo $part['part_name']; ?>"
                                                                                        data-qty="<?php echo $part['current_qty']; ?>"><i
                                                                                            data-lucide="refresh-cw"
                                                                                            size="14"></i></button>
                                                                                    <a title="History"
                                                                                        href="history.php?id=<?php echo $part['id']; ?>&v_id=<?php echo $vessel_id; ?>"
                                                                                        class="action-link"><i data-lucide="history"
                                                                                            size="14"></i></a>
                                                                                    <button title="Edit" class="action-link"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#modalEdit"
                                                                                        data-id="<?php echo $part['id']; ?>"
                                                                                        data-name="<?php echo $part['part_name']; ?>"
                                                                                        data-number="<?php echo $part['part_number']; ?>"
                                                                                        data-price="<?php echo $part['price']; ?>"><i
                                                                                            data-lucide="edit-3" size="14"></i></button>
                                                                                    <a href="inventory.php?vessel_id=<?php echo $vessel_id; ?>&delete_id=<?php echo $part['id']; ?>"
                                                                                        class="action-link delete"
                                                                                        onclick="return confirm('Hapus?')"><i
                                                                                            data-lucide="trash-2" size="14"></i></a>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endwhile; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>"><a class="page-link"
                                        href="?vessel_id=<?php echo $vessel_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&main_id=<?php echo $main_filter; ?>&sub_id=<?php echo $sub_filter; ?>">Prev</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>"><a class="page-link"
                                            href="?vessel_id=<?php echo $vessel_id; ?>&page=<?php echo $i; ?>&search=<?php echo $search; ?>&main_id=<?php echo $main_filter; ?>&sub_id=<?php echo $sub_filter; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"><a
                                        class="page-link"
                                        href="?vessel_id=<?php echo $vessel_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&main_id=<?php echo $main_filter; ?>&sub_id=<?php echo $sub_filter; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ALL MODALS (Standard Crud Modals) -->
    <div class="modal fade" id="modalMain" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-primary">Tambah Main Induk</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3">
                        <div class="mb-3"><label class="small fw-bold text-muted text-uppercase">Nama Komponen
                                Induk</label><input type="text" name="component_name" class="form-control" required>
                        </div>
                        <div class="mb-3"><label class="small fw-bold text-muted text-uppercase">Part
                                Number</label><input type="text" name="part_number" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="submit_main_component"
                            class="btn btn-primary w-100 py-2 fw-bold">Simpan Induk</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditMainComp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0">Edit Main Induk</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3"><input type="hidden" name="main_comp_id" id="edit_main_id">
                        <div class="mb-3"><label class="small fw-bold">Nama Induk</label><input type="text"
                                name="component_name" id="edit_main_name" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold">Part Number</label><input type="text"
                                name="part_number" id="edit_main_number" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="submit_edit_main_comp"
                            class="btn btn-primary w-100 py-2 fw-bold">Update Induk</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditSubComp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0">Edit Sub Induk</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3"><input type="hidden" name="sub_comp_id" id="edit_sub_id">
                        <div class="mb-3"><label class="small fw-bold">Nama Sub Induk</label><input type="text"
                                name="sub_name" id="edit_sub_name" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold">Part Number</label><input type="text"
                                name="part_number" id="edit_sub_number" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="submit_edit_sub_comp"
                            class="btn btn-primary w-100 py-2 fw-bold">Update Sub Induk</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalSub" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-primary">Tambah Sub Induk</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3"><input type="hidden" name="main_component_id" id="modal_main_id">
                        <div class="mb-3"><label class="small fw-bold text-muted text-uppercase">Nama Sub
                                Komponen</label><input type="text" name="sub_name" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold text-muted text-uppercase">Part
                                Number</label><input type="text" name="part_number" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="submit_sub_component"
                            class="btn btn-primary w-100 py-2 fw-bold">Simpan Sub Induk</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalPart" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-primary">Tambah Item Part</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3"><input type="hidden" name="main_component_id"
                            id="p_main_id"><input type="hidden" name="sub_component_id" id="p_sub_id">
                        <div class="mb-3 bg-light p-2 rounded small border text-muted">Induk: <b id="p_sub_name"
                                class="text-primary"></b></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Nama Barang</label><input type="text"
                                name="part_name" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Part Number</label><input type="text"
                                name="part_number" class="form-control"></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="small fw-bold text-muted">Stok Awal</label><input
                                    type="number" name="initial_qty" class="form-control" value="0"></div>
                            <div class="col-6"><label class="small fw-bold text-muted">Harga</label><input type="number"
                                    name="price" class="form-control" value="0"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="submit_sparepart"
                            class="btn btn-primary w-100 py-2 fw-bold">Simpan Item</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0">Edit Sparepart</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body px-4 pt-3">
                        <input type="hidden" name="inventory_id" id="edit_id">
                        <input type="hidden" name="vessel_id_edit" value="<?php echo $vessel_id; ?>">
                        <div class="mb-3"><label class="form-label small fw-bold">Nama Barang</label><input type="text"
                                name="part_name" id="edit_name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Part Number</label><input type="text"
                                name="part_number" id="edit_number" class="form-control"></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Harga Satuan</label><input
                                type="number" name="price" id="edit_price" class="form-control"></div>
                    </div>
                    <div class="modal-footer px-4 pb-4 border-0"><button type="submit" name="submit_edit_sparepart"
                            class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Update Data</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalAdjust" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-primary">Adjustment Stok</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form id="formAdjust">
                    <div class="modal-body px-4 pt-3"><input type="hidden" name="inventory_id" id="adj_id">
                        <div class="mb-4 text-center">
                            <h6 id="adj_name" class="fw-bold text-primary mb-1"></h6>
                            <p class="small text-muted mb-0">Stok Saat Ini: <b id="adj_qty_label"></b> Unit</p>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><label
                                    class="small fw-bold text-muted text-uppercase">Jenis</label><select name="type"
                                    class="form-select">
                                    <option value="IN">Masuk (+)</option>
                                    <option value="OUT">Keluar (-)</option>
                                </select></div>
                            <div class="col-6"><label
                                    class="small fw-bold text-muted text-uppercase">Jumlah</label><input type="number"
                                    name="qty_change" class="form-control" required></div>
                            <div class="col-12"><label
                                    class="small fw-bold text-muted text-uppercase">Keterangan</label><textarea
                                    name="remarks" class="form-control" rows="2"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit"
                            class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Update Stok</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalImport" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-success">Import CSV</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="modal-body px-4"><input type="hidden" name="vessel_id"
                            value="<?php echo $vessel_id; ?>">
                        <div class="mb-3"><label class="form-label small fw-bold">Pilih File CSV</label><input
                                type="file" name="csv_file" class="form-control shadow-sm" accept=".csv" required></div>
                        <div class="alert alert-info small">Nama File otomatis jadi <b>Main Induk</b>. Baris tanpa No
                            jadi <b>Sub-Induk</b>.</div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4"><button type="submit" name="import_csv"
                            class="btn btn-success w-100 py-2 fw-bold">Proses Import</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- NEW PDF DATE RANGE MODAL -->
    <div class="modal fade" id="modalReportRange" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0 text-danger"><i data-lucide="calendar" class="me-2"></i> Pilih Rentang
                        Laporan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="export_pdf.php" method="GET" target="_blank">
                    <input type="hidden" name="vessel_id" value="<?php echo $vessel_id; ?>">
                    <div class="modal-body px-4 pt-3">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted text-uppercase">Dari Tanggal</label>
                                <input type="date" name="start_date" class="form-control" required
                                    value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted text-uppercase">Sampai Tanggal</label>
                                <input type="date" name="end_date" class="form-control" required
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <p class="mt-3 small text-muted">Laporan akan mencakup mutasi stok dan data inventaris dalam
                            rentang waktu yang dipilih.</p>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold shadow-sm">Generate PDF
                            Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FULLSCREEN BULK MODAL (Same as before) -->
    <div class="modal fade" id="modalBulk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold"><i data-lucide="layout-grid" class="me-2"></i> Bulk Inventory (Excel
                        Paste Supported)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="vessel_id" value="<?php echo $vessel_id; ?>">
                    <div class="modal-body bg-light">
                        <div class="alert alert-warning py-2 small shadow-sm border-0 d-flex align-items-center"><i
                                data-lucide="info" size="16" class="me-2"></i><span><b>Pro-Tip:</b> Copy cells from
                                Excel and <b>Paste (Ctrl+V)</b> here. New rows inherit Main/Sub from the row
                                above!</span></div>
                        <div class="table-responsive rounded-3 shadow-sm bg-white p-3">
                            <table class="table table-bordered align-middle" id="bulkTable">
                                <thead class="table-light small text-uppercase fw-bold">
                                    <tr>
                                        <th style="width: 18%;">Main Induk</th>
                                        <th style="width: 18%;">Sub Induk</th>
                                        <th style="width: 25%;">Part Name</th>
                                        <th style="width: 15%;">Part Number</th>
                                        <th style="width: 10%;">Qty</th>
                                        <th style="width: 10%;">Price</th>
                                        <th style="width: 4%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="bulkBody">
                                    <tr class="bulk-row">
                                        <td><input type="text" name="bulk_main[]" class="form-control form-control-sm"
                                                list="listMain" placeholder="Main category"></td>
                                        <td><input type="text" name="bulk_sub[]" class="form-control form-control-sm"
                                                placeholder="Sub category"></td>
                                        <td><input type="text" name="bulk_name[]" class="form-control form-control-sm"
                                                required placeholder="Item Name"></td>
                                        <td><input type="text" name="bulk_pn[]" class="form-control form-control-sm"
                                                placeholder="P/N"></td>
                                        <td><input type="number" name="bulk_qty[]" class="form-control form-control-sm"
                                                value="0"></td>
                                        <td><input type="number" name="bulk_price[]"
                                                class="form-control form-control-sm" value="0"></td>
                                        <td class="text-center"><button type="button"
                                                class="btn btn-link text-danger p-0" onclick="removeRow(this)"><i
                                                    data-lucide="trash-2" size="16"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button"
                            class="btn btn-outline-primary btn-sm fw-bold mt-3 rounded-pill px-4 shadow-sm"
                            onclick="addRow()"><i data-lucide="plus" size="14"></i> Add Another Row</button>
                    </div>
                    <div class="modal-footer bg-white border-0 shadow-lg">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_bulk_inventory"
                            class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">Save All Items</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <datalist id="listMain">
        <?php
        $dl_q = mysqli_query($conn, "SELECT component_name FROM main_components WHERE vessel_id = '$vessel_id'");
        while ($dl = mysqli_fetch_assoc($dl_q))
            echo "<option value='{$dl['component_name']}'>";
        ?>
    </datalist>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        // BULK TABLE HANDLERS
        function addRow() {
            const tbody = document.getElementById('bulkBody');
            const lastRow = tbody.lastElementChild;
            const newRow = lastRow.cloneNode(true);
            const prevMain = lastRow.querySelector('input[name="bulk_main[]"]').value;
            const prevSub = lastRow.querySelector('input[name="bulk_sub[]"]').value;
            const inputs = newRow.getElementsByTagName('input');
            for (let i = 0; i < inputs.length; i++) {
                let name = inputs[i].name;
                if (name === 'bulk_main[]') inputs[i].value = prevMain;
                else if (name === 'bulk_sub[]') inputs[i].value = prevSub;
                else if (inputs[i].type === 'number') inputs[i].value = 0;
                else inputs[i].value = '';
            }
            tbody.appendChild(newRow);
            lucide.createIcons();
        }

        function removeRow(btn) {
            const tbody = document.getElementById('bulkBody');
            if (tbody.rows.length > 1) { btn.closest('tr').remove(); } else { alert("At least one row is required."); }
        }

        document.getElementById('bulkTable').addEventListener('paste', function (e) {
            e.preventDefault();
            const text = (e.originalEvent || e).clipboardData.getData('text/plain');
            const rows = text.split('\n');
            const tbody = document.getElementById('bulkBody');
            let startRow = e.target.closest('tr');
            let startColIndex = Array.from(e.target.closest('td').parentElement.children).indexOf(e.target.closest('td'));
            rows.forEach((rowText, rowIndex) => {
                if (!rowText.trim()) return;
                const cols = rowText.split('\t');
                let targetRow = startRow;
                if (!targetRow) { addRow(); targetRow = tbody.lastElementChild; }
                cols.forEach((colText, colIndex) => {
                    const targetTd = targetRow.children[startColIndex + colIndex];
                    if (targetTd) {
                        const input = targetTd.querySelector('input');
                        if (input) input.value = colText.trim();
                    }
                });
                startRow = targetRow.nextElementSibling;
            });
        });

        document.getElementById('formAdjust').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax_adjustment', '1');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const invId = formData.get('inventory_id');
                        const badge = document.getElementById('stock-badge-' + invId);
                        badge.textContent = data.new_qty;
                        badge.className = (data.new_qty < 5) ? 'badge-stock bg-danger text-white' : 'badge-stock bg-light text-dark';
                        bootstrap.Modal.getInstance(document.getElementById('modalAdjust')).hide();
                        this.reset();
                    } else { alert('Gagal update stok'); }
                });
        });

        // Modal Data Fillers
        document.getElementById('modalEditMainComp').addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_main_id').value = b.getAttribute('data-id');
            document.getElementById('edit_main_name').value = b.getAttribute('data-name');
            document.getElementById('edit_main_number').value = b.getAttribute('data-number');
        });
        document.getElementById('modalEditSubComp').addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_sub_id').value = b.getAttribute('data-id');
            document.getElementById('edit_sub_name').value = b.getAttribute('data-name');
            document.getElementById('edit_sub_number').value = b.getAttribute('data-number');
        });
        document.getElementById('modalSub').addEventListener('show.bs.modal', e => { document.getElementById('modal_main_id').value = e.relatedTarget.getAttribute('data-main-id'); });
        document.getElementById('modalPart').addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('p_main_id').value = b.getAttribute('data-main-id');
            document.getElementById('p_sub_id').value = b.getAttribute('data-sub-id');
            document.getElementById('p_sub_name').textContent = b.getAttribute('data-sub-name');
        });
        document.getElementById('modalEdit').addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_id').value = b.getAttribute('data-id');
            document.getElementById('edit_name').value = b.getAttribute('data-name');
            document.getElementById('edit_number').value = b.getAttribute('data-number');
            document.getElementById('edit_price').value = b.getAttribute('data-price');
        });
        document.getElementById('modalAdjust').addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('adj_id').value = b.getAttribute('data-id');
            document.getElementById('adj_name').textContent = b.getAttribute('data-name');
            document.getElementById('adj_qty_label').textContent = b.getAttribute('data-qty');
        });
    </script>
</body>

</html>