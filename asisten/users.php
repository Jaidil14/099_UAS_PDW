<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manajemen Akun Pengguna';
$activePage = 'users'; // Untuk menandai menu aktif di sidebar asisten
require_once '../config.php'; // Path relatif dari asisten/users.php ke config.php
require_once 'templates/header.php'; // Path relatif dari asisten/users.php ke header.php

$message = '';

// Handle Add/Edit User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // Hanya jika mengubah/menambah password
    $role = trim($_POST['role']);

    if (empty($nama) || empty($email) || empty($role)) {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Nama, Email, dan Peran wajib diisi.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Format email tidak valid.</div>";
    } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Peran tidak valid.</div>";
    } else {
        // Check for duplicate email (excluding current user if editing)
        $sql_check_email = "SELECT id FROM users WHERE email = ?";
        if ($_POST['action'] == 'edit' && $user_id) {
            $sql_check_email .= " AND id != ?";
        }
        $stmt_check_email = $conn->prepare($sql_check_email);
        if ($_POST['action'] == 'edit' && $user_id) {
            $stmt_check_email->bind_param("si", $email, $user_id);
        } else {
            $stmt_check_email->bind_param("s", $email);
        }
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();

        if ($result_check_email->num_rows > 0) {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Email sudah terdaftar.</div>";
        } else {
            if ($_POST['action'] == 'add') {
                if (empty($password)) {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Password wajib diisi untuk user baru.</div>";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nama, $email, $hashed_password, $role);
                    if ($stmt->execute()) {
                        $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Pengguna berhasil ditambahkan.</div>";
                    } else {
                        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menambahkan pengguna: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                }
            } elseif ($_POST['action'] == 'edit' && $user_id) {
                $sql = "UPDATE users SET nama = ?, email = ?, role = ?";
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $sql .= ", password = ?";
                }
                $sql .= " WHERE id = ?";

                $stmt = $conn->prepare($sql);
                if (!empty($password)) {
                    $stmt->bind_param("ssssi", $nama, $email, $role, $hashed_password, $user_id);
                } else {
                    $stmt->bind_param("sssi", $nama, $email, $role, $user_id);
                }

                if ($stmt->execute()) {
                    $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Pengguna berhasil diperbarui.</div>";
                } else {
                    $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal memperbarui pengguna: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
        $stmt_check_email->close();
    }
    // Redirect to clean POST and edit param from URL
    header("Location: users.php?status=" . urlencode(strip_tags($message)));
    exit();
}

// Handle Delete User
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_to_delete_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($user_to_delete_id) {
        if ($user_to_delete_id == $_SESSION['user_id']) {
            $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Anda tidak bisa menghapus akun Anda sendiri.</div>";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_to_delete_id);
            if ($stmt->execute()) {
                $message = "<div class='p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>Pengguna berhasil dihapus.</div>";
            } else {
                $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>Gagal menghapus pengguna: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    } else {
        $message = "<div class='p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>ID Pengguna tidak valid untuk penghapusan.</div>";
    }
}

// Get status message from redirect
if (isset($_GET['status'])) {
    $message = $_GET['status'];
}

// Fetch all users
$user_list = [];
$result = $conn->query("SELECT id, nama, email, role, created_at FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $user_list[] = $row;
    }
    $result->free();
}

// Logic for pre-filling edit form
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $user_edit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($user_edit_id) {
        $stmt_edit = $conn->prepare("SELECT id, nama, email, role FROM users WHERE id = ?");
        $stmt_edit->bind_param("i", $user_edit_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows > 0) {
            $editData = $result_edit->fetch_assoc();
        } else {
            $message = "<div class='p-3 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg' role='alert'>Pengguna tidak ditemukan untuk diedit.</div>";
        }
        $stmt_edit->close();
    }
}
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Akun Pengguna</h2>

    <?php echo $message; ?>

    <div class="mb-6 border border-gray-200 p-4 rounded-lg bg-gray-50">
        <h3 class="text-xl font-semibold mb-3"><?php echo $editData ? 'Edit' : 'Tambah'; ?> Pengguna</h3>
        <form action="users.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($editData['id']); ?>">
            <?php endif; ?>

            <div class="mb-4">
                <label for="nama" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
                <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($editData['nama'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editData['email'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password <?php echo $editData ? '(Kosongkan jika tidak ingin mengubah)' : ''; ?>:</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" <?php echo $editData ? '' : 'required'; ?>>
            </div>
            <div class="mb-4">
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Peran:</label>
                <select id="role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="mahasiswa" <?php echo (isset($editData['role']) && $editData['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                    <option value="asisten" <?php echo (isset($editData['role']) && $editData['role'] == 'asisten') ? 'selected' : ''; ?>>Asisten</option>
                </select>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $editData ? 'Perbarui Pengguna' : 'Tambah Pengguna'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="users.php" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800 ml-4">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 class="text-xl font-semibold text-gray-800 mb-3">Daftar Semua Pengguna</h3>
    <?php if (empty($user_list)): ?>
        <p class="text-gray-600">Belum ada pengguna terdaftar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Nama</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Email</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Peran</th>
                        <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-700">Terdaftar Sejak</th>
                        <th class="py-3 px-4 border-b text-center text-sm font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_list as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['nama']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td class="py-2 px-4 border-b text-sm text-gray-800"><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                            <td class="py-2 px-4 border-b text-center">
                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1.5 px-3 rounded text-xs mr-2 transition duration-200">Edit</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): // Tidak bisa menghapus akun sendiri ?>
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1.5 px-3 rounded text-xs transition duration-200" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini? Semua data terkait (praktikum diikuti, laporan) akan terhapus!');">Hapus</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs"> (Akun Anda)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php'; // Path relatif dari asisten/users.php ke footer.php
$conn->close();
?>