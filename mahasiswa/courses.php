<?php
session_start();
// Pastikan hanya mahasiswa yang bisa mengakses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Cari Praktikum';
$activePage = 'courses'; // Untuk menandai menu aktif di sidebar
require_once '../config.php'; // Sesuaikan path jika berbeda
require_once 'templates/header_mahasiswa.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Handle pendaftaran praktikum
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'enroll') {
    $mata_praktikum_id = filter_var($_POST['mata_praktikum_id'], FILTER_VALIDATE_INT);

    if ($mata_praktikum_id) {
        // Cek apakah mahasiswa sudah terdaftar
        $stmt_check = $conn->prepare("SELECT id FROM praktikum_mahasiswa WHERE user_id = ? AND mata_praktikum_id = ?");
        $stmt_check->bind_param("ii", $user_id, $mata_praktikum_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $message = "<div class='p-3 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg' role='alert'>Anda sudah terdaftar di praktikum ini.</div>";
        } else {
            // Lakukan pendaftaran
            $stmt_enroll = $conn->prepare("INSERT INTO praktikum_mahasiswa (user_id, mata_praktikum_id) VALUES (?, ?)");
            $stmt_enroll->bind_param("ii", $user_id, $mata_praktikum_id);
            if ($stmt_enroll->execute()) {
                $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Berhasil mendaftar ke praktikum!</div>";
            } else {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal mendaftar: " . $stmt_enroll->error . "</div>";
            }
            $stmt_enroll->close();
        }
        $stmt_check->close();
    } else {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>ID Praktikum tidak valid.</div>";
    }
}

// Ambil semua mata praktikum yang tersedia
$all_praktikum = [];
$sql_all_praktikum = "SELECT mp.*, pm.id AS enrolled_id
                      FROM mata_praktikum mp
                      LEFT JOIN praktikum_mahasiswa pm ON mp.id = pm.mata_praktikum_id AND pm.user_id = ?
                      ORDER BY mp.nama_praktikum ASC";
$stmt_all = $conn->prepare($sql_all_praktikum);
$stmt_all->bind_param("i", $user_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();
while ($row = $result_all->fetch_assoc()) {
    $all_praktikum[] = $row;
}
$stmt_all->close();
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Cari Praktikum</h2>

    <?php echo $message; ?>

    <?php if (empty($all_praktikum)): ?>
        <p class="text-gray-600">Belum ada mata praktikum yang tersedia saat ini.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($all_praktikum as $praktikum): ?>
                <div class="border border-gray-200 rounded-lg p-4 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h3>
                        <p class="text-sm text-gray-600 mb-2">Kode: <?php echo htmlspecialchars($praktikum['kode_praktikum']); ?></p>
                        <p class="text-gray-700 text-sm mb-4"><?php echo nl2br(htmlspecialchars($praktikum['deskripsi'])); ?></p>
                    </div>
                    <div class="mt-auto">
                        <?php if ($praktikum['enrolled_id']): ?>
                            <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Sudah Terdaftar</span>
                            <a href="detail_praktikum.php?id=<?php echo $praktikum['id']; ?>" class="ml-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-1.5 px-3 rounded text-sm transition duration-200">Lihat Detail</a>
                        <?php else: ?>
                            <form action="courses.php" method="POST">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="mata_praktikum_id" value="<?php echo $praktikum['id']; ?>">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                                    Daftar Sekarang
                                </button>
                            </form>
                        <?php endif; ?>
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