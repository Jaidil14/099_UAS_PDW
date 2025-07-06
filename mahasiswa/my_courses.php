<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Praktikum Saya';
$activePage = 'my_courses'; // Untuk menandai menu aktif di sidebar
require_once '../config.php';
require_once 'templates/header_mahasiswa.php';

$user_id = $_SESSION['user_id'];

// Ambil praktikum yang diikuti oleh mahasiswa ini
$my_praktikum = [];
$sql_my_praktikum = "SELECT mp.id, mp.nama_praktikum, mp.deskripsi, mp.kode_praktikum, pm.tanggal_daftar
                     FROM praktikum_mahasiswa pm
                     JOIN mata_praktikum mp ON pm.mata_praktikum_id = mp.id
                     WHERE pm.user_id = ?
                     ORDER BY pm.tanggal_daftar DESC";
$stmt_my = $conn->prepare($sql_my_praktikum);
$stmt_my->bind_param("i", $user_id);
$stmt_my->execute();
$result_my = $stmt_my->get_result();
while ($row = $result_my->fetch_assoc()) {
    $my_praktikum[] = $row;
}
$stmt_my->close();
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Praktikum yang Diikuti</h2>

    <?php if (empty($my_praktikum)): ?>
        <p class="text-gray-600">Anda belum terdaftar di praktikum manapun. Silakan <a href="courses.php" class="text-blue-600 hover:underline">cari praktikum</a> untuk mendaftar.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($my_praktikum as $praktikum): ?>
                <div class="border border-gray-200 rounded-lg p-4 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h3>
                        <p class="text-sm text-gray-600 mb-2">Kode: <?php echo htmlspecialchars($praktikum['kode_praktikum']); ?></p>
                        <p class="text-gray-700 text-sm mb-4"><?php echo nl2br(htmlspecialchars($praktikum['deskripsi'])); ?></p>
                        <p class="text-xs text-gray-500">Terdaftar sejak: <?php echo date('d M Y', strtotime($praktikum['tanggal_daftar'])); ?></p>
                    </div>
                    <div class="mt-auto pt-4 border-t border-gray-100">
                        <a href="detail_praktikum.php?id=<?php echo $praktikum['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                            Lihat Detail Praktikum
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer_mahasiswa.php';
$conn->close();
?>