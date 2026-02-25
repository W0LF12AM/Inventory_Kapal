<?php
include 'koneksi.php';
$id = $_GET['id'];
$v_id = $_GET['v_id'];

mysqli_query($conn, "DELETE FROM inventory WHERE id = $id");
header("Location: index.php?vessel_id=$v_id");
?>