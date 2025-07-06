<?php
// === BAGIAN INI HARUS BERADA DI PALING ATAS FILE, TANPA SPASI ATAU BARIS KOSONG SEBELUMNYA ===
session_start();
require_once '../config.php'; // Path relatif dari asisten/modul.php ke config.php

// CEK LOGIN DAN ROLE SEBELUM LOGIKA UTAMA
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'asisten') {
    header("Location: ../login.php");
    exit(); // PENTING: selalu exit setelah header redirect
}

$message = '';
// Ambil praktikum_id dari GET atau POST untuk memastikan konsistensi redirect
$selected_praktikum_id = filter_var($_GET['praktikum_id'] ?? null, FILTER_VALIDATE_INT);


// === SEMUA LOGIKA PHP YANG MEMUNGKINKAN REDIRECT (header()) HARUS BERADA DI SINI ===
// Handle Add/Edit Modul
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $modul_id = filter_var($_POST['modul_id'] ?? null, FILTER_VALIDATE_INT);
    $praktikum_id_post = filter_var($_POST['mata_praktikum_id'], FILTER_VALIDATE_INT);
    $nama_modul = trim($_POST['nama_modul']);
    $deskripsi = trim($_POST['deskripsi']);
    $urutan = filter_var($_POST['urutan'], FILTER_VALIDATE_INT);

    // Perbaikan: Pastikan urutan adalah integer yang valid
    if (!$praktikum_id_post || empty($nama_modul) || ($urutan === false && $urutan !== 0)) {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Mohon lengkapi semua data modul yang wajib diisi (Nama Modul, Urutan).</div>";
    } else {
        $file_materi = null;
        $upload_dir = '../uploads/materi/'; // Path relatif dari asisten/ ke uploads/materi/

        // Jika ini mode edit, ambil nama file materi yang sudah ada
        if ($_POST['action'] == 'edit' && $modul_id) {
            $stmt_current_file = $conn->prepare("SELECT file_materi FROM modul WHERE id = ?");
            $stmt_current_file->bind_param("i", $modul_id);
            $stmt_current_file->execute();
            $result_current_file = $stmt_current_file->get_result();
            if ($result_current_file->num_rows > 0) {
                $file_materi = $result_current_file->fetch_assoc()['file_materi'];
            }
            $stmt_current_file->close();
        }

        // Handle file upload
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['file_materi']['tmp_name'];
            $file_name = basename($_FILES['file_materi']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_ext = ['pdf', 'doc', 'docx']; // Sesuaikan ekstensi yang diizinkan
            $max_file_size = 20 * 1024 * 1024; // 20 MB

            if (!in_array($file_ext, $allowed_ext)) {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Ekstensi file materi tidak diizinkan. Hanya PDF, DOC, DOCX.</div>";
            } elseif ($_FILES['file_materi']['size'] > $max_file_size) {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Ukuran file materi terlalu besar (maks 20MB).</div>";
            } else {
                // Hapus file lama jika ada dan berhasil diupload file baru
                if ($file_materi && file_exists($upload_dir . $file_materi)) {
                    unlink($upload_dir . $file_materi);
                }
                $new_file_name = uniqid('materi_') . '.' . $file_ext;
                $target_file = $upload_dir . $new_file_name;
                // Perbaikan: Pastikan direktori ada sebelum memindahkan file
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true); // Buat direktori jika tidak ada
                }
                if (move_uploaded_file($file_tmp_name, $target_file)) {
                    $file_materi = $new_file_name;
                } else {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal mengunggah file materi. Pastikan folder 'uploads/materi/' memiliki izin tulis.</div>";
                }
            }
        } elseif (isset($_POST['remove_file_materi']) && $_POST['remove_file_materi'] == 'yes') {
            // Perbaikan: Hapus file materi jika checkbox "Hapus File" dicentang
            if ($file_materi && file_exists($upload_dir . $file_materi)) {
                unlink($upload_dir . $file_materi);
            }
            $file_materi = null; // Set ke null di database
        }


        if (empty($message)) { // Lanjutkan jika tidak ada error upload
            if ($_POST['action'] == 'add') {
                $stmt = $conn->prepare("INSERT INTO modul (mata_praktikum_id, nama_modul, deskripsi, file_materi, urutan) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $praktikum_id_post, $nama_modul, $deskripsi, $file_materi, $urutan);
                if ($stmt->execute()) {
                    $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Modul berhasil ditambahkan.</div>";
                } else {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menambahkan modul: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } elseif ($_POST['action'] == 'edit' && $modul_id) {
                $sql = "UPDATE modul SET mata_praktikum_id = ?, nama_modul = ?, deskripsi = ?, urutan = ?, file_materi = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssi", $praktikum_id_post, $nama_modul, $deskripsi, $urutan, $file_materi, $modul_id);
                if ($stmt->execute()) {
                    $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Modul berhasil diperbarui.</div>";
                } else {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal memperbarui modul: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
    }
    // Perbaikan: Pastikan redirect selalu menyertakan praktikum_id yang benar
    $redirect_praktikum_id = $praktikum_id_post ?: $selected_praktikum_id;
    header("Location: modul.php?praktikum_id=" . ($redirect_praktikum_id ?: '') . "&status=" . urlencode(strip_tags($message)));
    exit(); // PENTING: selalu exit setelah header redirect
}

// Handle Delete Modul
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $modul_to_delete_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $redirect_praktikum_id = filter_var($_GET['praktikum_id'] ?? null, FILTER_VALIDATE_INT); // Ambil dari GET untuk redirect

    if ($modul_to_delete_id) {
        // Hapus file materi terkait jika ada
        $stmt_file = $conn->prepare("SELECT file_materi FROM modul WHERE id = ?");
        $stmt_file->bind_param("i", $modul_to_delete_id);
        $stmt_file->execute();
        $result_file = $stmt_file->get_result();
        if ($result_file->num_rows > 0) {
            $file_data = $result_file->fetch_assoc();
            if (!empty($file_data['file_materi']) && file_exists('../uploads/materi/' . $file_data['file_materi'])) {
                unlink('../uploads/materi/' . $file_data['file_materi']);
            }
        }
        $stmt_file->close();

        $stmt = $conn->prepare("DELETE FROM modul WHERE id = ?");
        $stmt->bind_param("i", $modul_to_delete_id);
        if ($stmt->execute()) {
            $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Modul berhasil dihapus.</div>";
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menghapus modul: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>ID Modul tidak valid untuk penghapusan.</div>";
    }
    header("Location: modul.php?praktikum_id=" . ($redirect_praktikum_id ?: '') . "&status=" . urlencode(strip_tags($message)));
    exit(); // PENTING: selalu exit setelah header redirect
}

// Dapatkan pesan status dari redirect (setelah semua pemrosesan POST/GET)
if (isset($_GET['status'])) {
    $message = urldecode($_GET['status']); // Gunakan urldecode karena pesan di-urlencoded
}

// Set judul halaman dan halaman aktif *setelah* semua pemrosesan di atas
$pageTitle = 'Manajemen Modul';
$activePage = 'modul';

// Fetch all mata_praktikum for dropdown selection (re-fetch as it might be needed for the form even if not selected)
$mata_praktikum_list = [];
$result_mp_all = $conn->query("SELECT id, nama_praktikum FROM mata_praktikum ORDER BY nama_praktikum ASC");
if ($result_mp_all) {
    while ($row = $result_mp_all->fetch_assoc()) {
        $mata_praktikum_list[] = $row;
    }
    $result_mp_all->free();
}

// Logic for pre-filling edit form and getting selected praktikum details
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $modul_edit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($modul_edit_id) {
        $stmt_edit = $conn->prepare("SELECT * FROM modul WHERE id = ?");
        $stmt_edit->bind_param("i", $modul_edit_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows > 0) {
            $editData = $result_edit->fetch_assoc();
            $selected_praktikum_id = $editData['mata_praktikum_id']; // Update selected_praktikum_id for form
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg' role='alert'>Modul tidak ditemukan untuk diedit.</div>";
        }
        $stmt_edit->close();
    }
}

// Fetch modules for the selected praktikum (after potential update from editData)
$modul_list = [];
$praktikum_name = "Pilih Praktikum";
if ($selected_praktikum_id) {
    $stmt_name = $conn->prepare("SELECT nama_praktikum FROM mata_praktikum WHERE id = ?");
    $stmt_name->bind_param("i", $selected_praktikum_id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if ($result_name->num_rows > 0) {
        $praktikum_name = $result_name->fetch_assoc()['nama_praktikum'];
    }
    $stmt_name->close();

    $stmt_modul = $conn->prepare("SELECT * FROM modul WHERE mata_praktikum_id = ? ORDER BY urutan ASC, created_at ASC");
    $stmt_modul->bind_param("i", $selected_praktikum_id);
    $stmt_modul->execute();
    $result_modul = $stmt_modul->get_result();
    while ($row = $result_modul->fetch_assoc()) {
        $modul_list[] = $row;
    }
    $stmt_modul->close();
}
?>

<?php
// === HTML OUTPUT STARTS HERE ===
require_once 'templates/header.php'; // Path relatif dari asisten/modul.php ke header.php
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Modul</h2>

    <?php echo $message; ?>

    <div class="mb-6 border border-gray-200 p-4 rounded-lg bg-gray-50">
        <label for="select_praktikum" class="block text-gray-700 text-sm font-bold mb-2">Pilih Mata Praktikum:</label>
        <select id="select_praktikum" onchange="window.location.href='modul.php?praktikum_id=' + this.value"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <option value="">-- Pilih Praktikum --</option>
            <?php foreach ($mata_praktikum_list as $mp): ?>
                <option value="<?php echo $mp['id']; ?>" <?php echo ($selected_praktikum_id == $mp['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($mp['nama_praktikum']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($mata_praktikum_list)): ?>
            <p class="text-red-500 text-xs mt-2">Tidak ada mata praktikum tersedia. Tambahkan <a href="mata_praktikum.php" class="text-blue-600 hover:underline">mata praktikum</a> terlebih dahulu.</p>
        <?php endif; ?>
    </div>

    <?php if ($selected_praktikum_id): // Perbaikan: Tambahkan endif; untuk blok ini di bagian paling bawah ?>
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Modul untuk Praktikum: <?php echo htmlspecialchars($praktikum_name); ?></h3>

    <div class="mb-6 border border-gray-200 p-4 rounded-lg bg-gray-50">
        <h4 class="text-lg font-semibold mb-3"><?php echo $editData ? 'Edit' : 'Tambah'; ?> Modul</h4>
        <form action="modul.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <input type="hidden" name="mata_praktikum_id" value="<?php echo htmlspecialchars($selected_praktikum_id); ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="modul_id" value="<?php echo htmlspecialchars($editData['id']); ?>">
            <?php endif; ?>

            <div class="mb-4">
                <label for="nama_modul" class="block text-gray-700 text-sm font-bold mb-2">Nama Modul:</label>
                <input type="text" id="nama_modul" name="nama_modul" value="<?php echo htmlspecialchars($editData['nama_modul'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="urutan" class="block text-gray-700 text-sm font-bold mb-2">Nomor Urut:</label>
                <input type="number" id="urutan" name="urutan" value="<?php echo htmlspecialchars($editData['urutan'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required min="1">
            </div>
            <div class="mb-4">
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
                <textarea id="deskripsi" name="deskripsi" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"><?php echo htmlspecialchars($editData['deskripsi'] ?? ''); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="file_materi" class="block text-gray-700 text-sm font-bold mb-2">File Materi (PDF/DOCX, maks 20MB):</label>
                <input type="file" id="file_materi" name="file_materi" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none">
                <?php if ($editData && !empty($editData['file_materi'])): ?>
                    <p class="text-xs text-gray-600 mt-1">File saat ini: <a href="../uploads/materi/<?php echo htmlspecialchars($editData['file_materi']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($editData['file_materi']); ?></a></p>
                    <div class="flex items-center mt-2">
                        <input type="checkbox" id="remove_file_materi" name="remove_file_materi" value="yes" class="h-4 w-4 text-red-600 border-gray-300 rounded">
                        <label for="remove_file_materi" class="ml-2 block text-sm text-red-700">Hapus File Materi Ini</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $editData ? 'Perbarui Modul' : 'Tambah Modul'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="modul.php?praktikum_id=<?php echo htmlspecialchars($selected_praktikum_id); ?>" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800 ml-4">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 class="text-xl font-semibold text-gray-800 mb-3">Daftar Modul</h3>
    <?php if (empty($modul_list)): ?>
        <p class="text-gray-600">Belum ada modul untuk praktikum ini. Tambahkan di atas.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Urutan</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Nama Modul</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Deskripsi</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Materi</th>
                        <th class="py-3 px-4 border-b text-center text-sm font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modul_list as $modul): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($modul['urutan']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($modul['nama_modul']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($modul['deskripsi'])); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800">
                                <?php if (!empty($modul['file_materi'])): ?>
                                    <a href="../uploads/materi/<?php echo htmlspecialchars($modul['file_materi']); ?>" target="_blank" class="text-blue-600 hover:underline">Unduh</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border-b text-center">
                                <a href="modul.php?action=edit&id=<?php echo $modul['id']; ?>&praktikum_id=<?php echo htmlspecialchars($selected_praktikum_id); ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1.5 px-3 rounded text-xs mr-2 transition duration-200">Edit</a>
                                <a href="modul.php?action=delete&id=<?php echo $modul['id']; ?>&praktikum_id=<?php echo htmlspecialchars($selected_praktikum_id); ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1.5 px-3 rounded text-xs transition duration-200" onclick="return confirm('Apakah Anda yakin ingin menghapus modul ini? Semua laporan terkait modul ini akan ikut terhapus!');">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php
require_once 'templates/footer.php'; // Path relatif dari asisten/modul.php ke footer.php
$conn->close();
?>