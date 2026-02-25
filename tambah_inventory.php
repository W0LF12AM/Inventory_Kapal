<?php
include 'koneksi.php';
$vessel_id = $_GET['vessel_id'];
$main_id = $_GET['main_id'];

if (isset($_POST['submit'])) {
    $name = $_POST['part_name'];
    $p_num = $_POST['part_number'];
    $iqty = $_POST['initial_qty'];
    $cqty = $_POST['current_qty'];
    $price = $_POST['price'];

    $sql = "INSERT INTO inventory (vessel_id, main_component_id, part_name, part_number, initial_qty, current_qty, price) 
            VALUES ('$vessel_id', '$main_id', '$name', '$p_num', '$iqty', '$cqty', '$price')";
    
    if(mysqli_query($conn, $sql)) {
        header("Location: index.php?vessel_id=$vessel_id");
    }
}
?>
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-body">
            <h3>Tambah Item ke: <?php 
                $m = mysqli_fetch_assoc(mysqli_query($conn, "SELECT component_name FROM main_components WHERE id=$main_id"));
                echo $m['component_name'];
            ?></h3>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nama Sparepart</label>
                        <input type="text" name="part_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Part Number</label>
                        <input type="text" name="part_number" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Initial Qty</label>
                        <input type="number" name="initial_qty" class="form-control" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Qty Terbaru</label>
                        <input type="number" name="current_qty" class="form-control" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Harga Satuan</label>
                        <input type="number" name="price" class="form-control" value="0">
                    </div>
                </div>
                <button type="submit" name="submit" class="btn btn-success">Simpan Item</button>
                <a href="index.php?vessel_id=<?= $vessel_id ?>" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>