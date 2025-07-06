<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Laporan Masuk';
$activePage = 'laporan';
require_once '../config.php'; // Path relatif dari asisten/laporan.php ke config.php
require_once 'templates/header.php'; // Path relatif dari asisten/laporan.php ke header.php

$message = '';

// Handle giving grade
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'grade_report') {
    $laporan_id = filter_var($_POST['laporan_id'], FILTER_VALIDATE_INT);
    $nilai = filter_var($_POST['nilai'], FILTER_VALIDATE_INT);
    $feedback = trim($_POST['feedback']);

    // Perbaikan: Pastikan nilai valid sebelum update
    if (!$laporan_id || $nilai === false || $nilai < 0 || $nilai > 100) {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Data penilaian tidak valid. Nilai harus antara 0-100.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE laporan SET nilai = ?, feedback = ?, tanggal_dinilai = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("isi", $nilai, $feedback, $laporan_id);
        if ($stmt->execute()) {
            $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Laporan berhasil dinilai.</div>";
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menilai laporan: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Get filter parameters
$filter_modul_id = filter_var($_GET['modul_id'] ?? null, FILTER_VALIDATE_INT);
$filter_user_id = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
$filter_status = trim($_GET['status'] ?? ''); // 'dinilai', 'belum_dinilai', ''

// Build SQL query with filters
$sql_laporan = "SELECT l.*, u.nama AS nama_mahasiswa, u.email AS email_mahasiswa, m.nama_modul, mp.nama_praktikum
                FROM laporan l
                JOIN users u ON l.user_id = u.id
                JOIN modul m ON l.modul_id = m.id
                JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id
                WHERE 1=1"; // Dummy condition to allow easy WHERE clause appending

$params = [];
$types = '';

if ($filter_modul_id) {
    $sql_laporan .= " AND l.modul_id = ?";
    $params[] = $filter_modul_id;
    $types .= 'i';
}
if ($filter_user_id) {
    $sql_laporan .= " AND l.user_id = ?";
    $params[] = $filter_user_id;
    $types .= 'i';
}
if (!empty($filter_status)) {
    if ($filter_status == 'dinilai') {
        $sql_laporan .= " AND l.nilai IS NOT NULL";
    } elseif ($filter_status == 'belum_dinilai') {
        $sql_laporan .= " AND l.nilai IS NULL";
    }
}

$sql_laporan .= " ORDER BY l.tanggal_submit DESC";

$stmt_laporan = $conn->prepare($sql_laporan);
if (!empty($params)) {
    $stmt_laporan->bind_param($types, ...$params);
}
$stmt_laporan->execute();
$result_laporan = $stmt_laporan->get_result();

$laporan_list = [];
while ($row = $result_laporan->fetch_assoc()) {
    $laporan_list[] = $row;
}
$stmt_laporan->close();

// Fetch data for filters dropdowns (Perbaikan: Ambil nama praktikum untuk modul filter)
$modul_filter_list = [];
$result_modul_filter = $conn->query("SELECT m.id, m.nama_modul, mp.nama_praktikum FROM modul m JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id ORDER BY mp.nama_praktikum, m.nama_modul ASC");
if ($result_modul_filter) {
    while ($row = $result_modul_filter->fetch_assoc()) {
        $modul_filter_list[] = $row;
    }
    $result_modul_filter->free();
}


$mahasiswa_filter_list = [];
$result_mahasiswa_filter = $conn->query("SELECT id, nama FROM users WHERE role = 'mahasiswa' ORDER BY nama ASC");
if ($result_mahasiswa_filter) {
    while ($row = $result_mahasiswa_filter->fetch_assoc()) {
        $mahasiswa_filter_list[] = $row;
    }
    $result_mahasiswa_filter->free();
}
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Laporan Masuk</h2>

    <?php echo $message; ?>

    <div class="mb-6 border border-gray-200 p-4 rounded-lg bg-gray-50">
        <h3 class="text-lg font-semibold mb-3">Filter Laporan</h3>
        <form action="laporan.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="filter_modul" class="block text-gray-700 text-sm font-bold mb-2">Modul:</label>
                <select id="filter_modul" name="modul_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modul_filter_list as $modul): ?>
                        <option value="<?php echo $modul['id']; ?>" <?php echo ($filter_modul_id == $modul['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($modul['nama_praktikum']); ?> - <?php echo htmlspecialchars($modul['nama_modul']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_mahasiswa" class="block text-gray-700 text-sm font-bold mb-2">Mahasiswa:</label>
                <select id="filter_mahasiswa" name="user_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Mahasiswa</option>
                    <?php foreach ($mahasiswa_filter_list as $mahasiswa): ?>
                        <option value="<?php echo $mahasiswa['id']; ?>" <?php echo ($filter_user_id == $mahasiswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mahasiswa['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status" class="block text-gray-700 text-sm font-bold mb-2">Status Penilaian:</label>
                <select id="filter_status" name="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="" <?php echo (empty($filter_status)) ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="dinilai" <?php echo ($filter_status == 'dinilai') ? 'selected' : ''; ?>>Sudah Dinilai</option>
                    <option value="belum_dinilai" <?php echo ($filter_status == 'belum_dinilai') ? 'selected' : ''; ?>>Belum Dinilai</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Terapkan Filter</button>
                <a href="laporan.php" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">Reset Filter</a>
            </div>
        </form>
    </div>

    <?php if (empty($laporan_list)): ?>
        <p class="text-gray-600">Tidak ada laporan yang sesuai dengan filter.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Mahasiswa</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Praktikum / Modul</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">File Laporan</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Tanggal Submit</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Nilai</th>
                        <th class="py-3 px-4 border-b text-center text-sm font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($laporan_list as $laporan): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($laporan['nama_mahasiswa']); ?><br><span class="text-xs text-gray-500"><?php echo htmlspecialchars($laporan['email_mahasiswa']); ?></span></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800">
                                <?php echo htmlspecialchars($laporan['nama_praktikum']); ?><br>
                                <span class="text-xs text-gray-500">Modul: <?php echo htmlspecialchars($laporan['nama_modul']); ?></span>
                            </td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800">
                                <a href="../uploads/laporan/<?php echo htmlspecialchars($laporan['file_laporan']); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l3-3m-3 3l-3-3m-3 8h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Unduh File
                                </a>
                            </td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo date('d M Y H:i', strtotime($laporan['tanggal_submit'])); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800">
                                <?php if ($laporan['nilai'] !== null): ?>
                                    <span class="font-bold text-lg text-blue-700"><?php echo htmlspecialchars($laporan['nilai']); ?></span>
                                    <p class="text-xs text-gray-600">Feedback: <?php echo nl2br(htmlspecialchars($laporan['feedback'])); ?></p>
                                    <p class="text-xs text-gray-500">Dinilai: <?php echo date('d M Y H:i', strtotime($laporan['tanggal_dinilai'])); ?></p>
                                <?php else: ?>
                                    <span class="text-yellow-600 font-semibold">Belum Dinilai</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border-b text-center">
                                <button onclick="openGradeModal(<?php echo $laporan['id']; ?>, '<?php echo htmlspecialchars($laporan['nama_mahasiswa']); ?>', '<?php echo htmlspecialchars($laporan['nama_modul']); ?>', <?php echo json_encode($laporan['nilai']); ?>, <?php echo json_encode($laporan['feedback']); ?>)" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-3 rounded text-xs transition duration-200">
                                    <?php echo ($laporan['nilai'] === null) ? 'Beri Nilai' : 'Edit Nilai'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="gradeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
        <h3 class="text-xl font-bold mb-4">Beri/Edit Nilai Laporan</h3>
        <p class="mb-2 text-gray-700">Mahasiswa: <span id="modalStudentName" class="font-semibold"></span></p>
        <p class="mb-4 text-gray-700">Modul: <span id="modalModuleName" class="font-semibold"></span></p>

        <form action="laporan.php" method="POST">
            <input type="hidden" name="action" value="grade_report">
            <input type="hidden" id="modalLaporanId" name="laporan_id">
            <div class="mb-4">
                <label for="nilai" class="block text-gray-700 text-sm font-bold mb-2">Nilai (0-100):</label>
                <input type="number" id="nilai" name="nilai" min="0" max="100" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-6">
                <label for="feedback" class="block text-gray-700 text-sm font-bold mb-2">Feedback (Opsional):</label>
                <textarea id="feedback" name="feedback" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeGradeModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">Batal</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Simpan Nilai</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGradeModal(laporanId, studentName, moduleName, currentNilai = '', currentFeedback = '') {
    document.getElementById('modalLaporanId').value = laporanId;
    document.getElementById('modalStudentName').innerText = studentName;
    document.getElementById('modalModuleName').innerText = moduleName;
    document.getElementById('nilai').value = currentNilai !== null ? currentNilai : ''; // Handle null for initial empty state
    document.getElementById('feedback').value = currentFeedback !== null ? currentFeedback : ''; // Handle null for initial empty state
    document.getElementById('gradeModal').classList.remove('hidden');
    document.getElementById('gradeModal').classList.add('flex');
}

function closeGradeModal() {
    document.getElementById('gradeModal').classList.add('hidden');
    document.getElementById('gradeModal').classList.remove('flex');
}
</script>

<?php
require_once 'templates/footer.php'; // Path relatif dari asisten/laporan.php ke footer.php
$conn->close();
?>