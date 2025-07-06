<?php
session_start();

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: ./login.php"); // Path relatif dari logout.php ke login.php
exit; // Sangat penting untuk menghentikan eksekusi script setelah redirect
?>