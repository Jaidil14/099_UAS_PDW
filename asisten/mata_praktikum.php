<?php
session_start();
// Pastikan hanya asisten yang bisa mengakses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manajemen Mata Praktikum';
$activePage = 'mata_praktikum'; // Untuk menandai menu aktif di sidebar asisten
require_once '../config.php'; // Path relatif dari asisten/mata_praktikum.php ke config.php
require_once 'templates/header.php'; // Path relatif dari asisten/mata_praktikum.php ke header.php

$message = '';

// Handle POST requests for Create, Update, Delete
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Logika untuk Tambah Mata Praktikum
        if ($_POST['action'] == 'add') {
            $nama_praktikum = trim($_POST['nama_praktikum']);
            $deskripsi = trim($_POST['deskripsi']);
            $kode_praktikum = trim($_POST['kode_praktikum']);

            if (empty($nama_praktikum) || empty($kode_praktikum)) {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Nama praktikum dan kode praktikum wajib diisi.</div>";
            } else {
                // Periksa duplikasi kode_praktikum
                $stmt_check_code = $conn->prepare("SELECT id FROM mata_praktikum WHERE kode_praktikum = ?");
                $stmt_check_code->bind_param("s", $kode_praktikum);
                $stmt_check_code->execute();
                $result_check_code = $stmt_check_code->get_result();

                if ($result_check_code->num_rows > 0) {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Kode Praktikum sudah ada, silakan gunakan yang lain.</div>";
                } else {
                    $stmt = $conn->prepare("INSERT INTO mata_praktikum (nama_praktikum, deskripsi, kode_praktikum) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $nama_praktikum, $deskripsi, $kode_praktikum);
                    if ($stmt->execute()) {
                        $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Mata praktikum berhasil ditambahkan.</div>";
                    } else {
                        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menambahkan mata praktikum: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                }
                $stmt_check_code->close();
            }
        // Logika untuk Edit Mata Praktikum
        } elseif ($_POST['action'] == 'edit') {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $nama_praktikum = trim($_POST['nama_praktikum']);
            $deskripsi = trim($_POST['deskripsi']);
            $kode_praktikum = trim($_POST['kode_praktikum']);

            if (empty($nama_praktikum) || empty($kode_praktikum) || !$id) {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Semua field wajib diisi dan ID tidak valid.</div>";
            } else {
                // Periksa duplikasi kode_praktikum (kecuali untuk praktikum yang sedang diedit)
                $stmt_check_code = $conn->prepare("SELECT id FROM mata_praktikum WHERE kode_praktikum = ? AND id != ?");
                $stmt_check_code->bind_param("si", $kode_praktikum, $id);
                $stmt_check_code->execute();
                $result_check_code = $stmt_check_code->get_result();

                if ($result_check_code->num_rows > 0) {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Kode Praktikum sudah ada, silakan gunakan yang lain.</div>";
                } else {
                    $stmt = $conn->prepare("UPDATE mata_praktikum SET nama_praktikum = ?, deskripsi = ?, kode_praktikum = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $nama_praktikum, $deskripsi, $kode_praktikum, $id);
                    if ($stmt->execute()) {
                        $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Mata praktikum berhasil diperbarui.</div>";
                    } else {
                        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal memperbarui: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                }
                $stmt_check_code->close();
            }
        }
    }
}

// Handle GET request for Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM mata_praktikum WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Mata praktikum berhasil dihapus.</div>";
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menghapus: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>ID tidak valid untuk penghapusan.</div>";
    }
}

// Get status message from redirect (jika ada)
if (isset($_GET['status'])) {
    $message = urldecode($_GET['status']);
}

// Fetch all mata_praktikum for display
$mata_praktikum_list = [];
$result = $conn->query("SELECT * FROM mata_praktikum ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mata_praktikum_list[] = $row;
    }
    $result->free();
}

// Logic for pre-filling edit form
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM mata_praktikum WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $editData = $result->fetch_assoc();
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg' role='alert'>Mata praktikum tidak ditemukan untuk diedit.</div>";
        }
        $stmt->close();
    }
}
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Mata Praktikum</h2>

    <?php echo $message; ?>

    <div class="mb-6 border border-gray-200 p-4 rounded-lg bg-gray-50">
        <h3 class="text-xl font-semibold mb-3"><?php echo $editData ? 'Edit' : 'Tambah'; ?> Mata Praktikum</h3>
        <form action="mata_praktikum.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id']); ?>">
            <?php endif; ?>

            <div class="mb-4">
                <label for="nama_praktikum" class="block text-gray-700 text-sm font-bold mb-2">Nama Praktikum:</label>
                <input type="text" id="nama_praktikum" name="nama_praktikum" value="<?php echo htmlspecialchars($editData['nama_praktikum'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="kode_praktikum" class="block text-gray-700 text-sm font-bold mb-2">Kode Praktikum:</label>
                <input type="text" id="kode_praktikum" name="kode_praktikum" value="<?php echo htmlspecialchars($editData['kode_praktikum'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi:</label>
                <textarea id="deskripsi" name="deskripsi" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="4"><?php echo htmlspecialchars($editData['deskripsi'] ?? ''); ?></textarea>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $editData ? 'Perbarui Praktikum' : 'Tambah Praktikum'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="mata_praktikum.php" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800 ml-4">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 class="text-xl font-semibold text-gray-800 mb-3">Daftar Mata Praktikum Tersedia</h3>
    <?php if (empty($mata_praktikum_list)): ?>
        <p class="text-gray-600">Belum ada mata praktikum yang ditambahkan. Silakan tambahkan di atas.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Nama Praktikum</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Kode</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Deskripsi</th>
                        <th class="py-3 px-4 border-b text-center text-sm font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mata_praktikum_list as $praktikum): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($praktikum['kode_praktikum']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($praktikum['deskripsi'])); ?></td>
                            <td class="py-2 px-4 border-b text-center">
                                <a href="mata_praktikum.php?action=edit&id=<?php echo $praktikum['id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1.5 px-3 rounded text-xs mr-2 transition duration-200">Edit</a>
                                <a href="mata_praktikum.php?action=delete&id=<?php echo $praktikum['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1.5 px-3 rounded text-xs transition duration-200" onclick="return confirm('Apakah Anda yakin ingin menghapus mata praktikum ini? Semua modul dan laporan terkait juga akan terhapus!');">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php'; // Path relatif dari asisten/mata_praktikum.php ke footer.php
$conn->close();
?>