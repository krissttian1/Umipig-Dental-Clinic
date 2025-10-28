<?php
session_start();


// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    
    $uploadedFiles = [];
    $errors = [];
    $autoCategory = isset($_POST['auto_category']);
    
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['files']['name'][$key];
        $file_size = $_FILES['files']['size'][$key];
        $file_tmp = $_FILES['files']['tmp_name'][$key];
        $file_type = $_FILES['files']['type'][$key];
        
        // Validate file size (10MB max)
        if ($file_size > 10485760) {
            $errors[] = "$file_name is too large (max 10MB)";
            continue;
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = "uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $unique_name;
        
        // Auto-detect category if enabled
        $category = $_POST['category'] ?? 'documents';
        if ($autoCategory) {
            $file_type_lower = strtolower($file_type);
            if (strpos($file_type_lower, 'image') !== false) {
                $category = 'images';
            } elseif (strpos($file_name, 'consent') !== false || strpos($file_name, 'form') !== false) {
                $category = 'consentForms';
            }
        }
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO files (File_Name, File_Path, File_Type, File_Size, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $file_name, $file_path, $file_type, $file_size, $category);
            
            if ($stmt->execute()) {
                $new_file_id = $stmt->insert_id;
                
                // ===== NOTIFICATION TRIGGER FOR ADMIN FILE UPLOAD =====
                createAdminNotification(
                    'documents',
                    'New File Uploaded',
                    'File "' . $file_name . '" has been uploaded by ' . $_SESSION['username'] . '.',
                    'medium',
                    'documents_files_module.php',
                    $new_file_id
                );
                // ===== END NOTIFICATION TRIGGER =====
                
                $uploadedFiles[] = [
                    'name' => $file_name,
                    'path' => $file_path,
                    'type' => $file_type,
                    'size' => $file_size,
                    'category' => $category
                ];
            } else {
                $errors[] = "Database error for $file_name";
                unlink($file_path);
            }
        } else {
            $errors[] = "Failed to upload $file_name";
        }
    }
    
    echo json_encode([
        'success' => empty($errors),
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ]);
    exit;
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    header('Content-Type: application/json');
    
    $fileId = $_POST['file_id'];
    
    try {
        // First get file path
        $stmt = $conn->prepare("SELECT File_Path FROM files WHERE Files_ID = ?");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        $row = $result->fetch_assoc();
        $filePath = $row['File_Path'];
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM files WHERE Files_ID = ?");
        $stmt->bind_param("i", $fileId);
        
        if ($stmt->execute()) {
            // Delete the actual file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle file ungrouping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ungroup_file'])) {
    header('Content-Type: application/json');
    
    $fileId = $_POST['file_id'];
    $currentGroup = $_POST['current_group'] ?? '';
    
    try {
        $stmt = $conn->prepare("UPDATE files SET `group` = NULL, category = 'documents' WHERE Files_ID = ?");
        $stmt->bind_param("i", $fileId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'File removed from group successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle file rename
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_file'])) {
    header('Content-Type: application/json');
    
    $fileId = $_POST['file_id'];
    $newName = $_POST['new_name'];
    
    // Validate new name
    if (empty($newName)) {
        echo json_encode(['success' => false, 'message' => 'File name cannot be empty']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE files SET File_Name = ? WHERE Files_ID = ?");
        $stmt->bind_param("si", $newName, $fileId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    header('Content-Type: application/json');
    
    $groupName = $_POST['group_name'];
    $fileIds = json_decode($_POST['file_ids'], true);
    $destinationCategory = $_POST['destination_category'] ?? null;
    
    if (empty($groupName)) {
        echo json_encode(['success' => false, 'message' => 'Group name cannot be empty']);
        exit;
    }
    
    try {
        // First update the category if needed
        if ($destinationCategory) {
            $stmt = $conn->prepare("UPDATE files SET category = ? WHERE Files_ID = ?");
            foreach ($fileIds as $fileId) {
                $stmt->bind_param("si", $destinationCategory, $fileId);
                $stmt->execute();
            }
        }
        
        // Then update the group name
        $stmt = $conn->prepare("UPDATE files SET `group` = ? WHERE Files_ID = ?");
        $successCount = 0;
        
        foreach ($fileIds as $fileId) {
            $stmt->bind_param("si", $groupName, $fileId);
            if ($stmt->execute()) {
                $successCount++;
            }
        }
        
        if ($successCount === count($fileIds)) {
            echo json_encode(['success' => true, 'message' => 'Group created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Some files could not be added to group']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle move files request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_files'])) {
    header('Content-Type: application/json');
    
    $fileIds = json_decode($_POST['fileIds'], true);
    $destinationCategory = $_POST['destinationCategory'];
    
    // Validate destination category
    $validCategories = ['documents', 'images', 'consentForms', 'others', 'mixedFiles'];
    if (!in_array($destinationCategory, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid destination category']);
        exit;
    }
    
    try {
        // Prepare the update statement
        $stmt = $conn->prepare("UPDATE files SET category = ? WHERE Files_ID = ?");
        
        // Process each file
        $successCount = 0;
        foreach ($fileIds as $fileId) {
            $stmt->bind_param("si", $destinationCategory, $fileId);
            if ($stmt->execute()) {
                $successCount++;
            }
        }
        
        if ($successCount === count($fileIds)) {
            echo json_encode(['success' => true, 'message' => 'Files moved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Some files could not be moved']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch files for display - Updated to match your other modules' style
$sql = "SELECT 
            f.Files_ID,
            f.File_Name,
            f.File_Path,
            f.File_Type,
            f.File_Size,
            f.uploaded_at,
            f.Patient_ID,
            f.Appointment_ID,
            f.category,
            f.`group`,
            u.fullname AS patient_name
        FROM files f
        LEFT JOIN users u ON f.Patient_ID = u.id
        ORDER BY f.uploaded_at DESC";

$result = $conn->query($sql);

$files = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $fileType = strtolower($row["File_Type"]);
        $isImage = strpos($fileType, 'image') !== false;
        $isPDF = strpos($fileType, 'pdf') !== false;
        
        $files[] = [
            "id" => $row["Files_ID"],
            "name" => $row["File_Name"],
            "type" => $fileType,
            "size" => $row["File_Size"],
            "date" => date("M d, Y", strtotime($row["uploaded_at"])),
            "url" => $row["File_Path"],
            "category" => $row["category"],
            "group" => $row["group"] ?? null,
            "preview" => $isImage || $isPDF,
            "previewUrl" => $isImage ? $row["File_Path"] : null,
            "patientName" => $row["patient_name"] // Add patient name to the file data
        ];
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umipig Dental Clinic - Documents</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="documents_files_module.css">
    <link rel="stylesheet" href="notifications/notification_style.css">
</head>
<body>

<header>
    <div class="logo-container">
        <div class="logo-circle">
            <img src="images/UmipigDentalClinic_Logo.jpg" alt="Umipig Dental Clinic">
        </div>

        <div class="clinic-info">
            <h1>Umipig Dental Clinic</h1>
            <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
        </div>
    </div>

    <div class="header-right">
    <?php if (isset($_SESSION['username'])): ?>
		<!-- Notification Icon -->
            <div class="notification-container">
                <a href="javascript:void(0)" class="notification-icon" title="Notifications" style="color: royalblue;">
                    <i class="fas fa-bell"></i>
                    <?php
                    $unread_count = getUnreadCount($user_id);
                    if($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notification-dropdown-container"></div>
            </div>

            <!-- Profile Icon -->
            <a href="user_profile_module.php" class="profile-icon" title="Profile" style="color: royalblue;">
                <i class="fas fa-user-circle"></i>
            </a>

        <span class="welcome-text">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! &nbsp;
            &nbsp;|&nbsp;
            <a href="logout.php" class="auth-link">Logout</a>
        </span>
    <?php else: ?>
        <a href="register.php" class="auth-link">Register</a>
        <span>|</span>
        <a href="login.php" class="auth-link">Login</a>
    <?php endif; ?>
    </div>
</header>

    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="menu-icon">☰</div>
            <ul class="nav-menu">
               <li class="nav-item">
                     <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
               </li>
                <li class="nav-item">
                     <a href="appointment_module.php" class="nav-link">Appointment Management</a>
               </li>
                <li class="nav-item">
                     <a href="billing_module.php" class="nav-link">Billing</a>
               </li>
               <li class="nav-item">
                     <a href="patient_records.php" class="nav-link">Patient Records</a>
               </li>
               <li class="nav-item">
                     <a href="reports_module.php" class="nav-link active">Reports</a>
               <li class="nav-item">
                     <a href="documents_files_module.php" class="nav-link active">Documents / Files</a>
               </li>
               <li class="nav-item">
                     <a href="calendar_module.php" class="nav-link active">Calendar</a>
               </li>
               <li class="nav-item">
                     <a href="tasks_reminders_module.php" class="nav-link active">Tasks & Reminders</a>
               <li class="nav-item">
                     <a href="system_settings_module.php" class="nav-link active">System Settings</a>
               </li>
        </div>

        
        
        <div class="documents-module">
                <h1 style="color: royalblue; margin-top: 20px; margin-bottom: 100px;">Documents / Files</h1>
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="search-input" placeholder="Search files...">
            </div>

            <div class="module-header">
                <div class="action-buttons">
                    <button class="btn btn-primary" id="create-group-btn">Create a group</button>
                    <button class="btn btn-outline" id="cancel-group-btn" style="display: none;">Cancel</button>
                    <button class="btn btn-outline" id="move-group-btn" style="display: none;">Move</button>
                    <button class="btn btn-outline" id="upload-btn">Upload File</button>
                </div>
            </div>

            <div class="column-tabs" id="column-tabs">
                <div class="column-tab active" data-tab="documents">Documents/Files</div>
                <div class="column-tab" data-tab="images">Images</div>
                <div class="column-tab" data-tab="consentForms">Clinic Forms</div>
                <div class="column-tab" data-tab="others">Others</div>
                <div class="column-tab" data-tab="mixedFiles">Grouped Files</div>
            </div>

            <div class="files-container" id="files-container">
                <!-- Files will be rendered here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div class="modal" id="preview-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="preview-title">File Preview</h2>
                <button class="close-modal" id="close-preview">&times;</button>
            </div>
            <div class="file-preview">
                <img src="" alt="File preview" id="preview-image" style="display: none;">
                <div id="unsupported-file">
                    <div class="file-icon" style="font-size: 3rem; margin: 20px 0;">📄</div>
                    <p>Preview not available for this file type</p>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn btn-primary" id="open-btn">Open</button>
                <button class="btn btn-outline" id="modal-download-btn">Download</button>
                <button class="btn btn-secondary" id="modal-rename-btn">Rename</button>
                <button class="btn btn-danger" id="modal-delete-btn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="upload-modal">
        <div class="modal-content upload-modal-content">
            <div class="modal-header">
                <h2>Upload Files</h2>
                <button class="close-modal" id="close-upload-modal">&times;</button>
            </div>
            <div class="upload-area" id="upload-area">
                <div class="upload-icon">📤</div>
                <p>Drag & drop files here or click to browse</p>
                <input type="file" id="file-input" class="file-input" multiple>
            </div>
            <div class="file-list" id="file-list"></div>
            <div class="file-actions">
                <button class="btn btn-outline" id="cancel-upload-btn">Cancel</button>
                <button class="btn btn-primary" id="confirm-upload-btn">Upload</button>
            </div>
        </div>
    </div>

    <!-- Group Creation Modal -->
    <div class="modal" id="group-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Group</h2>
                <button class="close-modal" id="close-group-modal">&times;</button>
            </div>
            <div class="group-creation">
                <p>Enter a name for your group:</p>
                <input type="text" id="group-name-input" placeholder="Group name">
                <button class="btn btn-primary" id="confirm-group-btn">Create Group</button>
            </div>
        </div>
    </div>

    <!-- Move Files Modal -->
    <div class="modal" id="move-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Move Selected Files</h2>
                <button class="close-modal" id="close-move-modal">&times;</button>
            </div>
            <div class="move-options">
                <p>Select destination category:</p>
                <select id="move-category-select">
                    <option value="documents">Documents/Files</option>
                    <option value="images">Images</option>
                    <option value="consentForms">Clinic Forms</option>
                    <option value="others">Others</option>
                    <option value="mixedFiles">Grouped Files</option>
                </select>
            </div>
            <div class="file-actions">
                <button class="btn btn-outline" id="cancel-move-btn">Cancel</button>
                <button class="btn btn-primary" id="confirm-move-btn">Move Files</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-confirm-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="close-modal" id="close-delete-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this file?</p>
            </div>
            <div class="file-actions">
                <button class="btn btn-outline" id="cancel-delete-btn">Cancel</button>
                <button class="btn btn-danger" id="confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Folder Modal (dynamically created in JavaScript) -->
    <div class="folder-modal" id="folder-modal">
        <div class="folder-modal-content">
            <div class="folder-modal-header">
                <h2 id="folder-modal-title">Folder Contents</h2>
                <button class="close-folder-modal">&times;</button>
            </div>
            <div class="folder-modal-body">
                <div class="folder-files-container" id="folder-files-container"></div>
            </div>
        </div>
    </div>

    <script>
        const filesFromPHP = <?php echo json_encode($files); ?>;
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global variables
    let files = [...filesFromPHP];
    let selectedFiles = new Set();
    let currentTab = 'documents';
    let currentPreviewFile = null;
    let isSelectMode = false;
    let currentFolderGroupName = null;

    // DOM elements
    const filesContainer = document.getElementById('files-container');
    const searchInput = document.getElementById('search-input');
    const createGroupBtn = document.getElementById('create-group-btn');
    const cancelGroupBtn = document.getElementById('cancel-group-btn');
    const moveGroupBtn = document.getElementById('move-group-btn');
    const uploadBtn = document.getElementById('upload-btn');
    const columnTabs = document.getElementById('column-tabs');
    const previewModal = document.getElementById('preview-modal');
    const closePreviewBtn = document.getElementById('close-preview');
    const previewImage = document.getElementById('preview-image');
    const unsupportedFile = document.getElementById('unsupported-file');
    const previewTitle = document.getElementById('preview-title');
    const openBtn = document.getElementById('open-btn');
    const modalDownloadBtn = document.getElementById('modal-download-btn');
    const modalRenameBtn = document.getElementById('modal-rename-btn');
    const modalDeleteBtn = document.getElementById('modal-delete-btn');
    const uploadModal = document.getElementById('upload-modal');
    const closeUploadModalBtn = document.getElementById('close-upload-modal');
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const fileList = document.getElementById('file-list');
    const cancelUploadBtn = document.getElementById('cancel-upload-btn');
    const confirmUploadBtn = document.getElementById('confirm-upload-btn');
    const groupModal = document.getElementById('group-modal');
    const closeGroupModalBtn = document.getElementById('close-group-modal');
    const groupNameInput = document.getElementById('group-name-input');
    const confirmGroupBtn = document.getElementById('confirm-group-btn');
    const moveModal = document.getElementById('move-modal');
    const closeMoveModalBtn = document.getElementById('close-move-modal');
    const moveCategorySelect = document.getElementById('move-category-select');
    const cancelMoveBtn = document.getElementById('cancel-move-btn');
    const confirmMoveBtn = document.getElementById('confirm-move-btn');
    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const closeDeleteModalBtn = document.getElementById('close-delete-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const folderModal = document.getElementById('folder-modal');
    const folderModalTitle = document.getElementById('folder-modal-title');
    const folderFilesContainer = document.getElementById('folder-files-container');
    const closeFolderModalBtn = folderModal.querySelector('.close-folder-modal');

    // Initialize the application
    function init() {
        renderFiles();
        setupEventListeners();
    }

    // Set up all event listeners
    function setupEventListeners() {
        // Search functionality
        searchInput.addEventListener('input', debounce(handleSearch, 300));

        // Tab switching
        columnTabs.addEventListener('click', function(e) {
            if (e.target.classList.contains('column-tab')) {
                document.querySelectorAll('.column-tab').forEach(tab => tab.classList.remove('active'));
                e.target.classList.add('active');
                currentTab = e.target.dataset.tab;
                renderFiles();
            }
        });

        // Create group button
        createGroupBtn.addEventListener('click', function() {
            if (selectedFiles.size > 0) {
                openGroupModal();
            } else {
                toggleSelectMode();
            }
        });

        // Cancel group button
        cancelGroupBtn.addEventListener('click', cancelSelectMode);

        // Move group button
        moveGroupBtn.addEventListener('click', function() {
            if (selectedFiles.size > 0) {
                openMoveModal();
            } else {
                alert('Please select files first');
            }
        });

        // Upload button
        uploadBtn.addEventListener('click', openUploadModal);

        // Preview modal
        closePreviewBtn.addEventListener('click', closePreviewModal);
        openBtn.addEventListener('click', openFile);
        modalDownloadBtn.addEventListener('click', downloadFile);
        modalRenameBtn.addEventListener('click', renameFile);
        modalDeleteBtn.addEventListener('click', confirmDeleteFile);

        // Upload modal
        closeUploadModalBtn.addEventListener('click', closeUploadModal);
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        fileInput.addEventListener('change', handleFileSelect);
        cancelUploadBtn.addEventListener('click', closeUploadModal);
        confirmUploadBtn.addEventListener('click', handleFileUpload);

        // Drag and drop for upload
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.style.borderColor = '#3a7bd5';
            uploadArea.style.backgroundColor = 'rgba(58, 123, 213, 0.05)';
        });

        uploadArea.addEventListener('dragleave', function() {
            uploadArea.style.borderColor = '#dee2e6';
            uploadArea.style.backgroundColor = 'transparent';
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.style.borderColor = '#dee2e6';
            uploadArea.style.backgroundColor = 'transparent';
            fileInput.files = e.dataTransfer.files;
            handleFileSelect({ target: fileInput });
        });

        // Group modal
        closeGroupModalBtn.addEventListener('click', closeGroupModal);
        confirmGroupBtn.addEventListener('click', createGroup);

        // Move modal
        closeMoveModalBtn.addEventListener('click', closeMoveModal);
        cancelMoveBtn.addEventListener('click', closeMoveModal);
        confirmMoveBtn.addEventListener('click', moveFiles);

        // Delete confirmation modal
        closeDeleteModalBtn.addEventListener('click', closeDeleteModal);
        cancelDeleteBtn.addEventListener('click', closeDeleteModal);
        confirmDeleteBtn.addEventListener('click', deleteFile);

        // Folder modal
        closeFolderModalBtn.addEventListener('click', closeFolderModal);
        folderModal.addEventListener('click', function(e) {
            if (e.target === folderModal) {
                closeFolderModal();
            }
        });
    }

    // Render files based on current tab and search
    function renderFiles() {
        const searchTerm = searchInput.value.toLowerCase();
        
        const filteredFiles = files.filter(file => {
            const matchesTab = (file.category === currentTab && !file.group) || 
                             (currentTab === 'mixedFiles' && file.group) ||
                             (currentTab === 'documents' && !file.group && file.category !== 'images' && file.category !== 'consentForms' && file.category !== 'others');
            
            const matchesSearch = file.name.toLowerCase().includes(searchTerm) || 
                                (file.group && file.group.toLowerCase().includes(searchTerm));
            
            return matchesTab && matchesSearch;
        });

        filesContainer.innerHTML = '';

        if (filteredFiles.length === 0) {
            filesContainer.innerHTML = '<div class="no-files">No files found</div>';
            return;
        }

        // Group files by group name if in mixedFiles tab
        if (currentTab === 'mixedFiles') {
            const groups = {};
            filteredFiles.forEach(file => {
                const groupName = file.group || 'Ungrouped';
                if (!groups[groupName]) {
                    groups[groupName] = [];
                }
                groups[groupName].push(file);
            });

            Object.entries(groups).forEach(([groupName, groupFiles]) => {
                const groupHeader = document.createElement('div');
                groupHeader.className = 'group-header';
                groupHeader.innerHTML = `<h3>${groupName === 'Ungrouped' ? 'Ungrouped Files' : groupName}</h3>`;
                groupHeader.addEventListener('click', () => {
                    openFolderModal(groupName, groupFiles);
                });
                filesContainer.appendChild(groupHeader);

                const groupContainer = document.createElement('div');
                groupContainer.className = 'group-container';
                filesContainer.appendChild(groupContainer);
                
                renderFileList(groupFiles, groupContainer);
            });
        } else {
            renderFileList(filteredFiles, filesContainer);
        }
    }

    // Render a list of files
    function renderFileList(filesToRender, container = filesContainer, isFolderModal = false) {
        filesToRender.forEach(file => {
            const fileCard = document.createElement('div');
            fileCard.className = `file-card ${selectedFiles.has(file.id) ? 'selected' : ''}`;
            fileCard.dataset.id = file.id;

            const previewContent = file.preview && file.previewUrl ? 
                `<img src="${file.previewUrl}" alt="${file.name}">` :
                `<div class="file-icon">${getFileIcon(file.type)}</div>`;

            // Create the file meta with patient label
            const fileMeta = document.createElement('div');
            fileMeta.className = 'file-meta';
            fileMeta.innerHTML = `
                <span>${formatFileSize(file.size)}</span>
                ${file.patientName ? `<span class="patient-label" style="color: royalblue;">Submitted by ${file.patientName}</span>` : ''}
                <span>${file.date}</span>
            `;

            fileCard.innerHTML = `
                <div class="file-preview-container" data-id="${file.id}">
                    ${previewContent}
                    <input type="checkbox" class="file-checkbox" ${selectedFiles.has(file.id) ? 'checked' : ''}>
                </div>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    ${fileMeta.outerHTML}
                    ${file.group ? `<div class="file-group">${file.group}</div>` : ''}
                    ${isFolderModal ? `<button class="btn btn-danger remove-from-folder-btn" data-id="${file.id}">Remove</button>` : ''}
                </div>
            `;

            // Add event listeners to the file card
            const checkbox = fileCard.querySelector('.file-checkbox');
            const previewContainer = fileCard.querySelector('.file-preview-container');

            checkbox.addEventListener('change', function(e) {
                e.stopPropagation();
                if (this.checked) {
                    selectedFiles.add(file.id);
                } else {
                    selectedFiles.delete(file.id);
                }
                updateSelectedFilesUI();
            });

            previewContainer.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    if (isSelectMode) {
                        const checkbox = this.querySelector('.file-checkbox');
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    } else {
                        if (isFolderModal) {
                            closeFolderModal();
                        }
                        openPreviewModal(file);
                    }
                }
            });

            if (isFolderModal) {
                const removeBtn = fileCard.querySelector('.remove-from-folder-btn');
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    removeFromFolder(file.id);
                });
            }

            container.appendChild(fileCard);
        });
    }

    // Remove file from folder
    function removeFromFolder(fileId) {
        if (!confirm('Are you sure you want to remove this file from the folder?')) {
            return;
        }

        // Find the file to get its current group
        const file = files.find(f => f.id === fileId);
        if (!file || !file.group) return;

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ungroup_file=true&file_id=${fileId}&current_group=${encodeURIComponent(file.group)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update our local files array
                files.forEach(f => {
                    if (f.id === fileId) {
                        f.group = null;
                        f.category = 'documents';
                    }
                });
                
                // Reopen the folder modal with updated files
                const groupFiles = files.filter(f => f.group === currentFolderGroupName);
                if (groupFiles.length > 0) {
                    openFolderModal(currentFolderGroupName, groupFiles);
                } else {
                    closeFolderModal();
                }
                renderFiles();
                alert('File removed from folder successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while removing file from folder');
        });
    }

    // Open folder modal
    function openFolderModal(groupName, groupFiles) {
        currentFolderGroupName = groupName;
        folderModalTitle.textContent = groupName;
        folderFilesContainer.innerHTML = '';
        
        // Render the files in the modal
        renderFileList(groupFiles, folderFilesContainer, true);
        
        // Show the modal
        folderModal.style.display = 'flex';
    }

    // Close folder modal
    function closeFolderModal() {
        folderModal.style.display = 'none';
        currentFolderGroupName = null;
    }

    // Get appropriate icon for file type
    function getFileIcon(fileType) {
        if (!fileType) return '📄';
        
        if (fileType.includes('image')) return '🖼️';
        if (fileType.includes('pdf')) return '📕';
        if (fileType.includes('word')) return '📝';
        if (fileType.includes('excel')) return '📊';
        if (fileType.includes('powerpoint')) return '📑';
        if (fileType.includes('zip') || fileType.includes('compressed')) return '🗜️';
        if (fileType.includes('audio')) return '🎵';
        if (fileType.includes('video')) return '🎬';
        
        return '📄';
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Handle search
    function handleSearch() {
        renderFiles();
    }

    // Debounce function for search
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // Toggle select mode
    function toggleSelectMode() {
        isSelectMode = !isSelectMode;
        if (isSelectMode) {
            cancelGroupBtn.style.display = 'inline-block';
            moveGroupBtn.style.display = 'inline-block';
            createGroupBtn.textContent = `Create Group (${selectedFiles.size})`;
        } else {
            cancelSelectMode();
        }
    }

    // Cancel select mode
    function cancelSelectMode() {
        isSelectMode = false;
        selectedFiles.clear();
        cancelGroupBtn.style.display = 'none';
        moveGroupBtn.style.display = 'none';
        createGroupBtn.textContent = 'Create a group';
        document.querySelectorAll('.file-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
    }

    // Update UI based on selected files
    function updateSelectedFilesUI() {
        document.querySelectorAll('.file-card').forEach(card => {
            const fileId = parseInt(card.dataset.id);
            if (selectedFiles.has(fileId)) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });

        if (selectedFiles.size > 0) {
            createGroupBtn.textContent = `Create Group (${selectedFiles.size})`;
            if (!isSelectMode) {
                cancelGroupBtn.style.display = 'inline-block';
                moveGroupBtn.style.display = 'inline-block';
            }
        } else {
            createGroupBtn.textContent = 'Create a group';
            if (isSelectMode) {
                cancelGroupBtn.style.display = 'inline-block';
                moveGroupBtn.style.display = 'inline-block';
            } else {
                cancelGroupBtn.style.display = 'none';
                moveGroupBtn.style.display = 'none';
            }
        }
    }

    // Open preview modal
    function openPreviewModal(file) {
        currentPreviewFile = file;
        previewTitle.textContent = file.name;
        
        if (file.preview && file.previewUrl) {
            previewImage.src = file.previewUrl;
            previewImage.style.display = 'block';
            unsupportedFile.style.display = 'none';
        } else {
            previewImage.style.display = 'none';
            unsupportedFile.style.display = 'block';
            unsupportedFile.querySelector('.file-icon').textContent = getFileIcon(file.type);
        }
        
        previewModal.style.display = 'flex';
    }

    // Close preview modal
    function closePreviewModal() {
        previewModal.style.display = 'none';
        currentPreviewFile = null;
    }

    // Open file in new tab
    function openFile() {
        if (currentPreviewFile) {
            window.open(currentPreviewFile.url, '_blank');
        }
    }

    // Download file
    function downloadFile() {
        if (currentPreviewFile) {
            const a = document.createElement('a');
            a.href = currentPreviewFile.url;
            a.download = currentPreviewFile.name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }

    // Rename file
    function renameFile() {
        if (!currentPreviewFile) return;

        const newName = prompt('Enter new file name:', currentPreviewFile.name);
        if (newName && newName !== currentPreviewFile.name) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `rename_file=true&file_id=${currentPreviewFile.id}&new_name=${encodeURIComponent(newName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentPreviewFile.name = newName;
                    previewTitle.textContent = newName;
                    renderFiles();
                    alert('File renamed successfully');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while renaming the file');
            });
        }
    }

    // Confirm file deletion
    function confirmDeleteFile() {
        if (currentPreviewFile) {
            deleteConfirmModal.style.display = 'flex';
            previewModal.style.display = 'none';
        }
    }

    // Close delete confirmation modal
    function closeDeleteModal() {
        deleteConfirmModal.style.display = 'none';
        if (currentPreviewFile) {
            previewModal.style.display = 'flex';
        }
    }

    // Delete file
    function deleteFile() {
        if (!currentPreviewFile) return;

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `delete_file=true&file_id=${currentPreviewFile.id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                files = files.filter(f => f.id !== currentPreviewFile.id);
                renderFiles();
                closeDeleteModal();
                closePreviewModal();
                alert('File deleted successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the file');
        });
    }

    // Open upload modal
    function openUploadModal() {
        fileList.innerHTML = '';
        fileInput.value = '';
        uploadModal.style.display = 'flex';
    }

    // Close upload modal
    function closeUploadModal() {
        uploadModal.style.display = 'none';
    }

    // Handle file selection for upload
    function handleFileSelect(e) {
        fileList.innerHTML = '';
        
        if (e.target.files.length === 0) return;
        
        Array.from(e.target.files).forEach(file => {
            const listItem = document.createElement('div');
            listItem.className = 'file-list-item';
            listItem.innerHTML = `
                <div class="file-list-name">${file.name}</div>
                <div class="file-list-size">${formatFileSize(file.size)}</div>
            `;
            fileList.appendChild(listItem);
        });
    }

    // Handle file upload
    function handleFileUpload() {
        if (fileInput.files.length === 0) {
            alert('Please select files to upload');
            return;
        }

        const formData = new FormData();
        // Don't force the current tab as category - let server detect
        formData.append('auto_category', 'true');
        
        Array.from(fileInput.files).forEach(file => {
            formData.append('files[]', file);
        });

        confirmUploadBtn.disabled = true;
        confirmUploadBtn.textContent = 'Uploading...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add uploaded files to our local files array
                data.uploaded.forEach(uploadedFile => {
                    const fileType = uploadedFile.type || 'application/octet-stream';
                    const isImage = fileType.includes('image');
                    const isPDF = fileType.includes('pdf');
                    
                    files.push({
                        id: files.length > 0 ? Math.max(...files.map(f => f.id)) + 1 : 1,
                        name: uploadedFile.name,
                        type: fileType,
                        size: uploadedFile.size,
                        date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                        url: uploadedFile.path,
                        category: uploadedFile.category || 'documents', // Use server-detected category
                        group: null,
                        preview: isImage || isPDF,
                        previewUrl: isImage ? uploadedFile.path : null
                    });
                });

                renderFiles();
                closeUploadModal();
                alert('Files uploaded successfully');
            } else {
                let errorMessage = 'Some files failed to upload:\n';
                data.errors.forEach(error => {
                    errorMessage += `- ${error}\n`;
                });
                alert(errorMessage);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred during upload');
        })
        .finally(() => {
            confirmUploadBtn.disabled = false;
            confirmUploadBtn.textContent = 'Upload';
        });
    }

    // Open group modal
    function openGroupModal() {
        groupNameInput.value = '';
        groupModal.style.display = 'flex';
    }

    // Close group modal
    function closeGroupModal() {
        groupModal.style.display = 'none';
    }

    // Create a new group
    function createGroup() {
        const groupName = groupNameInput.value.trim();
        
        if (!groupName) {
            alert('Please enter a group name');
            return;
        }

        const fileIds = Array.from(selectedFiles);
        
        // Determine if all files have the same category
        const categories = new Set();
        const filesToGroup = [];
        fileIds.forEach(id => {
            const file = files.find(f => f.id === id);
            if (file) {
                categories.add(file.category);
                filesToGroup.push(file);
            }
        });

        // If multiple categories, set to mixedFiles
        const destinationCategory = categories.size === 1 ? Array.from(categories)[0] : 'mixedFiles';
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `create_group=true&group_name=${encodeURIComponent(groupName)}&file_ids=${JSON.stringify(fileIds)}&destination_category=${destinationCategory}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update our local files array
                files.forEach(file => {
                    if (selectedFiles.has(file.id)) {
                        file.group = groupName;
                        // Update category if needed
                        if (categories.size > 1) {
                            file.category = 'mixedFiles';
                        }
                    }
                });
                
                renderFiles();
                cancelSelectMode();
                closeGroupModal();
                alert('Group created successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the group');
        });
    }

    // Open move modal
    function openMoveModal() {
        moveModal.style.display = 'flex';
    }

    // Close move modal
    function closeMoveModal() {
        moveModal.style.display = 'none';
    }

    // Move files to another category
    function moveFiles() {
        const destinationCategory = moveCategorySelect.value;
        const fileIds = Array.from(selectedFiles);
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `move_files=true&fileIds=${JSON.stringify(fileIds)}&destinationCategory=${destinationCategory}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update our local files array
                files.forEach(file => {
                    if (selectedFiles.has(file.id)) {
                        file.category = destinationCategory;
                    }
                });
                
                renderFiles();
                cancelSelectMode();
                closeMoveModal();
                alert('Files moved successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while moving files');
        });
    }

    // Initialize the application
    init();
});
</script>

    <script src="notifications/notification_script.js"></script>

</body>
</html>