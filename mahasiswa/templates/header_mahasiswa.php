<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek jika pengguna belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panel Mahasiswa - <?php echo $pageTitle ?? 'Dashboard'; ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen bg-gray-100">
    <div class="bg-indigo-800 text-white w-64 space-y-6 py-7 px-2 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out" id="sidebar">
        <a href="dashboard.php" class="text-white flex items-center space-x-2 px-4">
            <span class="text-2xl font-extrabold">SIMPRAK</span>
        </a>

        <nav>
            <ul class="space-y-2">
                <?php
                // Definisikan kelas untuk menu aktif/tidak aktif
                $activeClass = 'bg-indigo-700 text-white';
                $inactiveClass = 'text-indigo-200 hover:bg-indigo-600 hover:text-white';
                ?>
                <li>
                    <a href="dashboard.php" class="<?php echo ($activePage == 'dashboard') ? $activeClass : $inactiveClass; ?> flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 001 1h3m-6-10v10a1 1 0 001 1h3"></path></svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="courses.php" class="<?php echo ($activePage == 'courses') ? $activeClass : $inactiveClass; ?> flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path></svg>
                        <span>Cari Praktikum</span>
                    </a>
                </li>
                <li>
                    <a href="my_courses.php" class="<?php echo ($activePage == 'my_courses') ? $activeClass : $inactiveClass; ?> flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        <span>Praktikum Saya</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="flex-1 flex flex-col">
        <header class="bg-white shadow-md p-4 flex justify-between items-center">
            <button id="sidebarToggle" class="text-gray-600 focus:outline-none md:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <span class="text-xl font-bold text-gray-800 md:hidden">SIMPRAK</span> <div class="hidden md:block ml-auto"> <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                    Logout
                </a>
            </div>

            <div class="md:hidden"> <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                    Logout
                </a>
            </div>
        </header>

        <main class="flex-1 p-6 lg:p-8">