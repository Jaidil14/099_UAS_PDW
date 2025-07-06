<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Detail Praktikum';
$activePage = 'my_courses'; // Tetap aktifkan menu 'Praktikum Saya'
require_once '../config.php';
require_once 'templates/header_mahasiswa.php';

$user_id = $_SESSION['user_id'];
$praktikum_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$message = '';

if (!$praktikum_id) {
    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>ID Praktikum tidak valid.</div>";
    // Redirect atau tampilkan error
    echo $message;
    exit();
}

// Cek apakah mahasiswa terdaftar di praktikum ini
$stmt_check_enrollment = $conn->prepare("SELECT id FROM praktikum_mahasiswa WHERE user_id = ? AND mata_praktikum_id = ?");
$stmt_check_enrollment->bind_param("ii", $user_id, $praktikum_id);
$stmt_check_enrollment->execute();
$result_check_enrollment = $stmt_check_enrollment->get_result();
if ($result_check_enrollment->num_rows === 0) {
    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Anda tidak terdaftar di praktikum ini.</div>";
    echo $message;
    exit();
}
$stmt_check_enrollment->close();

// Ambil detail mata praktikum
$praktikum_detail = null;
$stmt_praktikum = $conn->prepare("SELECT * FROM mata_praktikum WHERE id = ?");
$stmt_praktikum->bind_param("i", $praktikum_id);
$stmt_praktikum->execute();
$result_praktikum = $stmt_praktikum->get_result();
if ($result_praktikum->num_rows > 0) {
    $praktikum_detail = $result_praktikum->fetch_assoc();
} else {
    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Praktikum tidak ditemukan.</div>";
    echo $message;
    exit();
}
$stmt_praktikum->close();

// Handle pengumpulan laporan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'submit_report') {
    $modul_id = filter_var($_POST['modul_id'], FILTER_VALIDATE_INT);

    if ($modul_id && isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['file_laporan']['tmp_name'];
        $file_name = basename($_FILES['file_laporan']['name']);
        $file_size = $_FILES['file_laporan']['size'];
        $file_type = $_FILES['file_laporan']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = ['pdf', 'doc', 'docx', 'zip', 'rar']; // Sesuaikan ekstensi yang diizinkan
        $max_file_size = 10 * 1024 * 1024; // 10 MB

        if (!in_array($file_ext, $allowed_ext)) {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Ekstensi file tidak diizinkan. Hanya PDF, DOC, DOCX, ZIP, RAR.</div>";
        } elseif ($file_size > $max_file_size) {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Ukuran file terlalu besar (maks 10MB).</div>";
        } else {
            $upload_dir = '../uploads/laporan/';
            $new_file_name = uniqid('laporan_') . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_name, $target_file)) {
                // Cek apakah laporan sudah pernah disubmit untuk modul ini oleh user ini
                $stmt_check_report = $conn->prepare("SELECT id FROM laporan WHERE modul_id = ? AND user_id = ?");
                $stmt_check_report->bind_param("ii", $modul_id, $user_id);
                $stmt_check_report->execute();
                $result_check_report = $stmt_check_report->get_result();

                if ($result_check_report->num_rows > 0) {
                    // Update laporan yang sudah ada
                    $existing_report = $result_check_report->fetch_assoc();
                    $stmt_update_report = $conn->prepare("UPDATE laporan SET file_laporan = ?, tanggal_submit = CURRENT_TIMESTAMP, nilai = NULL, feedback = NULL, tanggal_dinilai = NULL WHERE id = ?");
                    $stmt_update_report->bind_param("si", $new_file_name, $existing_report['id']);
                    if ($stmt_update_report->execute()) {
                        $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Laporan berhasil diperbarui!</div>";
                    } else {
                        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal memperbarui laporan: " . $stmt_update_report->error . "</div>";
                    }
                    $stmt_update_report->close();
                } else {
                    // Insert laporan baru
                    $stmt_insert_report = $conn->prepare("INSERT INTO laporan (modul_id, user_id, file_laporan) VALUES (?, ?, ?)");
                    $stmt_insert_report->bind_param("iis", $modul_id, $user_id, $new_file_name);
                    if ($stmt_insert_report->execute()) {
                        $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Laporan berhasil dikumpulkan!</div>";
                    } else {
                        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal mengumpulkan laporan: " . $stmt_insert_report->error . "</div>";
                    }
                    $stmt_insert_report->close();
                }
                $stmt_check_report->close();
            } else {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal mengunggah file.</div>";
            }
        }
    } else {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Mohon pilih file laporan.</div>";
    }
}

// Ambil semua modul untuk praktikum ini, beserta status laporan mahasiswa
$modul_list = [];
$sql_modul = "SELECT m.*, l.id AS laporan_id, l.file_laporan, l.tanggal_submit, l.nilai, l.feedback, l.tanggal_dinilai
              FROM modul m
              LEFT JOIN laporan l ON m.id = l.modul_id AND l.user_id = ?
              WHERE m.mata_praktikum_id = ?
              ORDER BY m.urutan ASC, m.created_at ASC";
$stmt_modul = $conn->prepare($sql_modul);
$stmt_modul->bind_param("ii", $user_id, $praktikum_id);
$stmt_modul->execute();
$result_modul = $stmt_modul->get_result();
while ($row = $result_modul->fetch_assoc()) {
    $modul_list[] = $row;
}
$stmt_modul->close();
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum_detail['nama_praktikum']); ?></h2>
    <p class="text-gray-600 mb-4">Kode: <?php echo htmlspecialchars($praktikum_detail['kode_praktikum']); ?></p>
    <p class="text-gray-700 mb-6"><?php echo nl2br(htmlspecialchars($praktikum_detail['deskripsi'])); ?></p>

    <?php echo $message; ?>

    <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Modul</h3>

    <?php if (empty($modul_list)): ?>
        <p class="text-gray-600">Belum ada modul yang tersedia untuk praktikum ini.</p>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($modul_list as $modul): ?>
                <div class="border border-gray-200 rounded-lg p-5">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Modul <?php echo htmlspecialchars($modul['urutan']); ?>: <?php echo htmlspecialchars($modul['nama_modul']); ?></h4>
                    <p class="text-gray-700 text-sm mb-3"><?php echo nl2br(htmlspecialchars($modul['deskripsi'])); ?></p>

                    <?php if (!empty($modul['file_materi'])): ?>
                        <div class="mb-3">
                            <p class="text-sm font-medium text-gray-700">Materi Modul:</p>
                            <a href="../uploads/materi/<?php echo htmlspecialchars($modul['file_materi']); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l3-3m-3 3l-3-3m-3 8h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Unduh Materi (<?php echo htmlspecialchars($modul['file_materi']); ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 mb-3">Materi belum tersedia.</p>
                    <?php endif; ?>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <h5 class="text-md font-semibold text-gray-800 mb-2">Laporan Anda:</h5>
                        <?php if ($modul['laporan_id']): ?>
                            <p class="text-sm text-gray-700 mb-2">Status: <span class="font-bold <?php echo ($modul['nilai'] !== null) ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo ($modul['nilai'] !== null) ? 'Sudah Dinilai' : 'Menunggu Penilaian'; ?></span></p>
                            <p class="text-sm text-gray-700 mb-2">Dikumpulkan pada: <?php echo date('d M Y H:i', strtotime($modul['tanggal_submit'])); ?></p>
                            <a href="../uploads/laporan/<?php echo htmlspecialchars($modul['file_laporan']); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center text-sm mb-3">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l3-3m-3 3l-3-3m-3 8h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Unduh Laporan Anda (<?php echo htmlspecialchars($modul['file_laporan']); ?>)
                            </a>

                            <?php if ($modul['nilai'] !== null): ?>
                                <p class="text-sm text-gray-700 mb-2">Nilai: <span class="font-bold text-blue-700 text-lg"><?php echo htmlspecialchars($modul['nilai']); ?></span></p>
                                <p class="text-sm text-gray-700 mb-2">Feedback: <?php echo nl2br(htmlspecialchars($modul['feedback'])); ?></p>
                                <p class="text-xs text-gray-500">Dinilai pada: <?php echo date('d M Y H:i', strtotime($modul['tanggal_dinilai'])); ?></p>
                            <?php endif; ?>

                            <form action="detail_praktikum.php?id=<?php echo $praktikum_id; ?>" method="POST" enctype="multipart/form-data" class="mt-4 p-3 border border-dashed border-gray-300 rounded-md bg-gray-50">
                                <p class="text-sm font-semibold mb-2">Kumpulkan Ulang Laporan:</p>
                                <input type="hidden" name="action" value="submit_report">
                                <input type="hidden" name="modul_id" value="<?php echo $modul['id']; ?>">
                                <div class="mb-3">
                                    <label for="file_laporan_<?php echo $modul['id']; ?>" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Laporan Baru (PDF, DOCX, ZIP, RAR, maks 10MB):</label>
                                    <input type="file" id="file_laporan_<?php echo $modul['id']; ?>" name="file_laporan" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none" required>
                                </div>
                                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm">
                                    Update Laporan
                                </button>
                            </form>

                        <?php else: ?>
                            <p class="text-sm text-gray-600 mb-3">Anda belum mengumpulkan laporan untuk modul ini.</p>
                            <form action="detail_praktikum.php?id=<?php echo $praktikum_id; ?>" method="POST" enctype="multipart/form-data" class="mt-4 p-3 border border-dashed border-gray-300 rounded-md bg-gray-50">
                                <input type="hidden" name="action" value="submit_report">
                                <input type="hidden" name="modul_id" value="<?php echo $modul['id']; ?>">
                                <div class="mb-3">
                                    <label for="file_laporan_<?php echo $modul['id']; ?>" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Laporan (PDF, DOCX, ZIP, RAR, maks 10MB):</label>
                                    <input type="file" id="file_laporan_<?php echo $modul['id']; ?>" name="file_laporan" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none" required>
                                </div>
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm">
                                    Kumpulkan Laporan
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