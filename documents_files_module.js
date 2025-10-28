const filesByCategory = {
    documents: [],
    images: [],
    consentForms: [],
    others: [],
    mixedFiles: []
};

// ===============================
// DOM Elements
// ===============================
const filesContainer = document.getElementById('files-container');
const searchInput = document.getElementById('search-input');
const uploadBtn = document.getElementById('upload-btn');
const createGroupBtn = document.getElementById('create-group-btn');
const selectItemsBtn = document.getElementById('select-items-btn');
const cancelGroupBtn = document.getElementById('cancel-group-btn');
const moveGroupBtn = document.getElementById('move-group-btn');
const previewModal = document.getElementById('preview-modal');
const closePreview = document.getElementById('close-preview');
const previewImage = document.getElementById('preview-image');
const unsupportedFile = document.getElementById('unsupported-file');
const previewTitle = document.getElementById('preview-title');
const openBtn = document.getElementById('open-btn');
const modalDownloadBtn = document.getElementById('modal-download-btn');
const modalDeleteBtn = document.getElementById('modal-delete-btn');
const modalRenameBtn = document.getElementById('modal-rename-btn'); // Added Rename Button
const groupModal = document.getElementById('group-modal');
const closeGroupModal = document.getElementById('close-group-modal');
const groupNameInput = document.getElementById('group-name-input');
const confirmGroupBtn = document.getElementById('confirm-group-btn');
const columnTabs = document.querySelectorAll('.column-tab');
const moveModal = document.getElementById('move-modal');
const closeMoveModal = document.getElementById('close-move-modal');
const moveCategorySelect = document.getElementById('move-category-select');
const confirmMoveBtn = document.getElementById('confirm-move-btn');
const cancelMoveBtn = document.getElementById('cancel-move-btn');

// ===============================
// State
// ===============================
let currentFile = null;
let groupingMode = false;
let selectedFiles = [];
let currentTab = 'documents';
let groupColors = ['group-color-1', 'group-color-2', 'group-color-3', 'group-color-4', 'group-color-5'];
let originalFiles = JSON.parse(JSON.stringify(filesByCategory));

// =========================================
// Helper functions
// =========================================
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function getFileType(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    const docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
    const wordExts = ['doc', 'docx'];
    const excelExts = ['xls', 'xlsx'];
    const pptExts = ['ppt', 'pptx'];
    const txtExts = ['txt', 'csv'];

    if (imageExts.includes(ext)) return 'image';
    if (docExts.includes(ext)) return 'document';
    if (wordExts.includes(ext)) return 'word';
    if (excelExts.includes(ext)) return 'excel';
    if (pptExts.includes(ext)) return 'powerpoint';
    return 'other';
}

function getFileIcon(fileType) {
    const icons = {
        'image': '🖼️',
        'pdf': '📄',
        'document': '📄',
        'word': '📝',
        'excel': '📊',
        'powerpoint': '📑',
        'text': '📄',
        'other': '📁'
    };
    return icons[fileType.toLowerCase()] || '📁';
}

function getCategoryByExtension(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    const docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
    if (imageExts.includes(ext)) return 'images';
    if (docExts.includes(ext)) return 'documents';
    return 'others';
}

function getAllFiles() {
    return Object.values(filesByCategory).flat();
}

function categorizeFile(file) {
    const fileType = getFileType(file);
    const newFile = {
        id: file.id,
        name: file.name,
        type: fileType,
        size: file.size,
        date: file.date,
        preview: fileType === 'image' || fileType === 'document' || fileType === 'pdf',
        previewUrl: file.previewUrl || (fileType === 'image' ? file.url : null),
        url: file.url,
        group: file.group || null,
        category: file.category || getCategoryByExtension(file)
    };

    if (file.category === 'mixedFiles') {
        return { category: 'mixedFiles', file: newFile };
    }

    let category = file.category;
    if (!category || category === 'General') {
        category = getCategoryByExtension(file);
    }

    category = category.trim().toLowerCase();

    if (category.includes('image')) {
        return { category: 'images', file: newFile };
    } else if (category.includes('form')) {
        return { category: 'consentForms', file: newFile };
    } else if (category.includes('doc')) {
        return { category: 'documents', file: newFile };
    } else {
        return { category: 'others', file: newFile };
    }
}

// =========================================
// Load files from PHP into filesByCategory
// =========================================
if (typeof filesFromPHP !== 'undefined' && Array.isArray(filesFromPHP)) {
    filesFromPHP.forEach(file => {
        const { category, file: newFile } = categorizeFile(file);
        filesByCategory[category].push(newFile);
    });

    originalFiles = JSON.parse(JSON.stringify(filesByCategory));
}

// ===============================
// Enhanced Preview Functions
// ===============================
function openPreview(file) {
    // Close any active group modal first
    if (window.activeGroupModal) {
        window.activeGroupModal.remove();
        window.activeGroupModal = null;
    }

    currentFile = file;
    previewTitle.textContent = file.name;
    previewTitle.contentEditable = false;
    
    // Reset preview elements
    previewImage.style.display = 'none';
    unsupportedFile.style.display = 'none';
    
    // Clear any existing PDF canvas
    const existingCanvas = previewModal.querySelector('canvas');
    if (existingCanvas) existingCanvas.remove();
    
    // Handle image files
    if (file.type === 'image') {
        previewImage.src = file.previewUrl || file.url;
        previewImage.style.display = 'block';
        
        const img = new Image();
        img.onload = function() {
            const container = previewModal.querySelector('.file-preview');
            const widthRatio = container.clientWidth / img.width;
            const heightRatio = container.clientHeight / img.height;
            const scale = Math.min(widthRatio, heightRatio, 1);
            
            previewImage.style.width = (img.width * scale) + 'px';
            previewImage.style.height = (img.height * scale) + 'px';
        };
        img.onerror = function() {
            showUnsupportedPreview();
        };
        img.src = file.url;
    }
    // Handle PDF/document files
    else if ((file.type === 'document' || file.type === 'pdf') && typeof pdfjsLib !== 'undefined') {
        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-preview';
        previewModal.querySelector('.file-preview').appendChild(canvas);
        
        pdfjsLib.getDocument(file.url).promise
            .then(pdf => pdf.getPage(1))
            .then(page => {
                const viewport = page.getViewport({ scale: 1.0 });
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                const renderContext = {
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                };
                
                page.render(renderContext);
            })
            .catch(error => {
                console.error('PDF preview error:', error);
                showUnsupportedPreview();
            });
    }
    // Unsupported file types
    else {
        showUnsupportedPreview();
    }
    
    // Show the preview modal
    previewModal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function showUnsupportedPreview() {
    const icon = unsupportedFile.querySelector('.file-icon');
    const fileType = currentFile.type;
    
    // Set appropriate icon based on file type
    if (fileType === 'word') icon.textContent = '📝';
    else if (fileType === 'excel') icon.textContent = '📊';
    else if (fileType === 'powerpoint') icon.textContent = '📑';
    else if (fileType === 'text') icon.textContent = '📄';
    else icon.textContent = '📁';
    
    unsupportedFile.style.display = 'block';
}

function showUnsupportedPreview() {
    const icon = unsupportedFile.querySelector('.file-icon');
    const fileType = currentFile.type;
    
    if (fileType === 'word') icon.textContent = '📝';
    else if (fileType === 'excel') icon.textContent = '📊';
    else if (fileType === 'powerpoint') icon.textContent = '📑';
    else if (fileType === 'text') icon.textContent = '📄';
    else icon.textContent = '📁';
    
    unsupportedFile.style.display = 'block';
}

// ===============================
// Render Functions
// ===============================
function renderFiles() {
    filesContainer.innerHTML = '';
    const currentFiles = filesByCategory[currentTab] || [];
    
    const groupedFiles = {};
    const ungroupedFiles = [];
    
    // Organize files by group with accurate size calculation
    currentFiles.forEach(file => {
        if (file.group) {
            if (!groupedFiles[file.group]) {
                groupedFiles[file.group] = {
                    id: 'group-' + file.group,
                    name: file.group,
                    type: 'group',
                    files: [],
                    size: 0,
                    date: file.date,
                    colorClass: groupColors[Math.abs(hashCode(file.group)) % groupColors.length]
                };
            }
            groupedFiles[file.group].files.push(file);
            groupedFiles[file.group].size += Number(file.size) || 0; // Ensure numeric addition
            if (new Date(file.date) > new Date(groupedFiles[file.group].date)) {
                groupedFiles[file.group].date = file.date;
            }
        } else {
            ungroupedFiles.push(file);
        }
    });

    const gridDiv = document.createElement('div');
    gridDiv.className = 'files-grid';

    // Render groups with vertical layout
    Object.values(groupedFiles).forEach(group => {
        const fileCard = document.createElement('div');
        fileCard.className = `file-card group-card ${group.colorClass}`;
        fileCard.dataset.id = group.id;
        
        if (selectedFiles.includes(group.id)) fileCard.classList.add('selected');

        const firstFile = group.files[0];
        let thumbnail = '';
        if (firstFile.type === 'image') {
            thumbnail = `
                <div class="file-thumbnail-container">
                    <img src="${firstFile.previewUrl || firstFile.url}" 
                         class="file-thumbnail-img"
                         alt="${firstFile.name}"
                         onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'no-preview\'>No preview</div>'">
                    <div class="group-badge">${group.files.length}</div>
                </div>`;
        } else {
            thumbnail = `
                <div class="file-icon-container">
                    <div class="file-icon-large">${getFileIcon(firstFile.type)}</div>
                    <div class="group-badge">${group.files.length}</div>
                </div>`;
        }

        fileCard.innerHTML = `
            <div class="checkbox-container">
                <input type="checkbox" class="file-checkbox" ${selectedFiles.includes(group.id) ? 'checked' : ''}>
            </div>
            <div class="file-thumbnail-container">
                ${thumbnail}
            </div>
            <div class="file-info-container">
                <div class="file-info">
                    <div class="file-name" title="${group.name}">
                        <span class="group-icon">📁</span> ${group.name}
                    </div>
                    <div class="group-meta-container">
                        <div class="group-meta-row">
                            <span class="meta-label">Files:</span>
                            <span class="meta-value">${group.files.length}</span>
                        </div>
                        <div class="group-meta-row">
                            <span class="meta-label">Size:</span>
                            <span class="meta-value">${formatFileSize(group.size)}</span>
                        </div>
                        <div class="group-meta-row">
                            <span class="meta-label">Modified:</span>
                            <span class="meta-value">${group.date}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const checkbox = fileCard.querySelector('.file-checkbox');
        checkbox.addEventListener('change', (e) => {
            e.stopPropagation();
            const groupId = group.id;
            if (checkbox.checked) {
                if (!selectedFiles.includes(groupId)) {
                    selectedFiles.push(groupId);
                }
            } else {
                selectedFiles = selectedFiles.filter(id => id !== groupId);
            }
            fileCard.classList.toggle('selected', checkbox.checked);
            updateActionButtons();
        });

        fileCard.addEventListener('click', (e) => {
            if (e.target.tagName !== 'INPUT') {
                showGroupFilesModal(group.name, group.files);
            }
        });

        gridDiv.appendChild(fileCard);
    });

    // Render ungrouped files
    ungroupedFiles.forEach(file => {
        const fileCard = document.createElement('div');
        fileCard.className = 'file-card';
        fileCard.setAttribute('data-id', file.id);
        
        if (selectedFiles.includes(file.id)) fileCard.classList.add('selected');

        let thumbnail = '';
        if (file.type === 'image') {
            thumbnail = `
                <div class="file-thumbnail-container">
                    <img src="${file.previewUrl || file.url}" 
                         class="file-thumbnail-img"
                         alt="${file.name}"
                         onerror="this.parentElement.innerHTML='<div class=\'no-preview\'>No preview</div>'">
                </div>`;
        } else if (file.type === 'document' || file.type === 'pdf') {
            thumbnail = `
                <div class="document-preview">
                    <div class="document-icon">${getFileIcon(file.type)}</div>
                </div>`;
        } else {
            thumbnail = `
                <div class="file-icon-container">
                    <div class="file-icon-large">${getFileIcon(file.type)}</div>
                </div>`;
        }

        fileCard.innerHTML = `
            <div class="checkbox-container">
                <input type="checkbox" class="file-checkbox" ${selectedFiles.includes(file.id) ? 'checked' : ''}>
            </div>
            <div class="file-thumbnail-container">
                ${thumbnail}
            </div>
            <div class="file-info-container">
                <div class="file-info">
                    <div class="file-name" title="${file.name}">${file.name}</div>
                    <div class="file-meta">
                        <span class="file-size">${formatFileSize(file.size)}</span>
                        <span class="meta-separator">•</span>
                        <span class="file-date">${file.date}</span>
                    </div>
                </div>
            </div>
        `;

        const checkbox = fileCard.querySelector('.file-checkbox');
        checkbox.addEventListener('change', (e) => {
            e.stopPropagation();
            const fileId = file.id;
            if (checkbox.checked) {
                if (!selectedFiles.includes(fileId)) {
                    selectedFiles.push(fileId);
                }
            } else {
                selectedFiles = selectedFiles.filter(id => id !== fileId);
            }
            fileCard.classList.toggle('selected', checkbox.checked);
            updateActionButtons();
        });

        fileCard.addEventListener('click', (e) => {
            if (e.target.tagName !== 'INPUT') {
                openPreview(file);
            }
        });

        gridDiv.appendChild(fileCard);
    });

    filesContainer.appendChild(gridDiv);
    updateActionButtons();
}

// Helper function to generate consistent color based on group name
function hashCode(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    return hash;
}

function showGroupFilesModal(groupName, groupFiles) {
    const modal = document.createElement('div');
    modal.className = 'group-files-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Group: ${groupName}</h2>
                <button class="close-group-files">&times;</button>
            </div>
            <div class="group-files-list">
                ${groupFiles.map(file => `
                    <div class="group-file-item" data-id="${file.id}">
                        <div class="file-icon">${getFileIcon(file.type)}</div>
                        <div class="file-name">${file.name}</div>
                        <div class="file-actions">
                            <button class="btn-open-file">Open</button>
                            <button class="btn-remove-from-group">Remove</button>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);

    window.activeGroupModal = modal;
    
    modal.querySelector('.close-group-files').addEventListener('click', () => {
        modal.remove();
        window.activeGroupModal = null;
    });

    modal.querySelectorAll('.btn-open-file').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const fileId = e.target.closest('.group-file-item').dataset.id;
            const file = getAllFiles().find(f => f.id === fileId);
            if (file) {
                // Remove the modal before opening preview
                modal.remove();
                window.activeGroupModal = null;
                openPreview(file);
            }
        });
    });
    
    modal.querySelectorAll('.btn-remove-from-group').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const fileId = e.target.closest('.group-file-item').dataset.id;
            try {
                const response = await fetch('remove_from_group.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ fileId })
                });
                
                const result = await response.json();
                if (result.success) {
                    const allFiles = getAllFiles();
                    const file = allFiles.find(f => f.id === fileId);
                    if (file) {
                        file.group = null;
                        renderFiles();
                        modal.remove();
                    }
                } else {
                    alert('Error removing file from group: ' + result.message);
                }
            } catch (error) {
                alert('Error removing file from group: ' + error.message);
            }
        });
    });
}

function updateActionButtons() {
    if (groupingMode) {
        filesContainer.classList.add('grouping-mode');
        selectItemsBtn.style.display = 'inline-block';
        cancelGroupBtn.style.display = 'inline-block';
        createGroupBtn.style.display = 'none';
        moveGroupBtn.style.display = 'none';
    } else {
        filesContainer.classList.remove('grouping-mode');
        selectItemsBtn.style.display = 'none';
        cancelGroupBtn.style.display = selectedFiles.length > 0 ? 'inline-block' : 'none';
        moveGroupBtn.style.display = selectedFiles.length > 0 ? 'inline-block' : 'none';
        createGroupBtn.style.display = 'inline-block';
    }
}

// ===============================
// Event Listeners
// ===============================
columnTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        currentTab = tab.getAttribute('data-tab');
        columnTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        renderFiles();
    });
});

searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase();
    if (term === '') {
        Object.keys(filesByCategory).forEach(c => {
            filesByCategory[c] = JSON.parse(JSON.stringify(originalFiles[c]));
        });
    }
    else {
        Object.keys(filesByCategory).forEach(c => {
            filesByCategory[c] = originalFiles[c].filter(f => f.name.toLowerCase().includes(term));
        });
    }
    renderFiles();
});

createGroupBtn.addEventListener('click', () => {
    groupingMode = true;
    selectedFiles = [];
    renderFiles();
});

cancelGroupBtn.addEventListener('click', () => {
    groupingMode = false;
    selectedFiles = [];
    renderFiles();
});

selectItemsBtn.addEventListener('click', () => {
    if (selectedFiles.length === 0) {
        alert('Please select at least one file to group');
        return;
    }
    groupModal.style.display = 'flex';
});

confirmGroupBtn.addEventListener('click', async () => {
    const groupName = groupNameInput.value.trim();

    if (!groupName) {
        alert('Please enter a group name');
        return;
    }

    if (selectedFiles.length < 1) {
        alert('Please select at least one file to create a group');
        return;
    }

    try {
        const response = await fetch('save_group.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                selectedFiles, 
                groupName,
                currentCategory: currentTab
            })
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message);
        }

        // Update local state
        const allFiles = getAllFiles();
        selectedFiles.forEach(fileId => {
            const file = allFiles.find(f => f.id === fileId);
            if (file) {
                file.group = groupName;
            }
        });

        // Update originalFiles
        Object.keys(originalFiles).forEach(category => {
            originalFiles[category].forEach(file => {
                if (selectedFiles.includes(file.id)) {
                    file.group = groupName;
                }
            });
        });

        // Reset UI
        groupingMode = false;
        selectedFiles = [];
        groupNameInput.value = '';
        groupModal.style.display = 'none';
        
        renderFiles();
    } catch (error) {
        alert('Error saving group: ' + error.message);
    }
});

moveGroupBtn.addEventListener('click', () => {
    if (selectedFiles.length === 0) {
        alert('Please select at least one file to move');
        return;
    }
    moveModal.style.display = 'flex';
});

confirmMoveBtn.addEventListener('click', async () => {
    const destinationCategory = moveCategorySelect.value;
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `move_files=true&fileIds=${encodeURIComponent(JSON.stringify(selectedFiles))}&destinationCategory=${encodeURIComponent(destinationCategory)}`
        });

        const result = await response.json();
        
        if (result.success) {
            // Update local state
            selectedFiles.forEach(fileId => {
                let foundFile = null;
                let sourceCategory = null;
                
                // Find the file in original categories
                Object.keys(filesByCategory).forEach(category => {
                    if (category === destinationCategory) return;
                    
                    const index = filesByCategory[category].findIndex(f => f.id === fileId);
                    if (index !== -1) {
                        foundFile = filesByCategory[category][index];
                        sourceCategory = category;
                        filesByCategory[category].splice(index, 1);
                    }
                });
                
                // Add to destination category if found
                if (foundFile) {
                    foundFile.category = destinationCategory;
                    filesByCategory[destinationCategory].push(foundFile);
                }
            });
            
            // Update originalFiles to match
            Object.keys(originalFiles).forEach(category => {
                originalFiles[category] = JSON.parse(JSON.stringify(filesByCategory[category]));
            });
            
            // Reset selection and render
            selectedFiles = [];
            renderFiles();
            moveModal.style.display = 'none';
        } else {
            alert('Error moving files: ' + result.message);
        }
    } catch (error) {
        alert('Error moving files: ' + error.message);
    }
});

// Other event listeners
cancelMoveBtn.addEventListener('click', () => {
    moveModal.style.display = 'none';
});

closeMoveModal.addEventListener('click', () => {
    moveModal.style.display = 'none';
});

closePreview.addEventListener('click', () => {
    previewModal.style.display = 'none';
});

closeGroupModal.addEventListener('click', () => {
    groupModal.style.display = 'none';
});

openBtn.addEventListener('click', () => {
    if (currentFile) {
        window.open(currentFile.url, '_blank');
    }
});

modalDownloadBtn.addEventListener('click', () => {
    if (currentFile) {
        const a = document.createElement('a');
        a.href = currentFile.url;
        a.download = currentFile.name;
        a.click();
    }
});

modalDeleteBtn.addEventListener('click', () => {
    if (currentFile && confirm(`Are you sure you want to delete ${currentFile.name}?`)) {
        for (const category in filesByCategory) {
            const index = filesByCategory[category].findIndex(f => f.id === currentFile.id);
            if (index !== -1) {
                filesByCategory[category].splice(index, 1);
                break;
            }
        }

        for (const category in originalFiles) {
            const index = originalFiles[category].findIndex(f => f.id === currentFile.id);
            if (index !== -1) {
                originalFiles[category].splice(index, 1);
                break;
            }
        }

        renderFiles();
        previewModal.style.display = 'none';
    }
});

// Rename functionality
modalRenameBtn.addEventListener('click', () => {
    if (currentFile) {
        previewTitle.contentEditable = true;
        previewTitle.focus();
        // Select all text in the editable div
        const range = document.createRange();
        range.selectNodeContents(previewTitle);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
});

// Save new name on blur or Enter key
previewTitle.addEventListener('blur', async () => {
    if (previewTitle.contentEditable === 'true') {
        const newName = previewTitle.textContent.trim();
        if (newName && newName !== currentFile.name) {
            await renameFile(currentFile.id, newName);
        }
        previewTitle.contentEditable = false;
    }
});

previewTitle.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        e.preventDefault(); // Prevent new line
        previewTitle.blur(); // Trigger blur to save
    }
});

function renameFile(fileId, currentName) {
  const newName = prompt("Enter new file name:", currentName);
  
  if (newName === null || newName.trim() === currentName || newName.trim() === '') {
    return; // User cancelled or didn't change the name
  }

  fetch('rename_file.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      fileId: fileId,
      newName: newName.trim()
    })
  })
  .then(response => {
    // First check if the response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      return response.text().then(text => {
        throw new Error(`Expected JSON but got: ${text}`);
      });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      alert('File renamed successfully!');
      // Refresh your file list or update the UI here
      location.reload(); // Simple solution - or update DOM directly
    } else {
      alert('Error: ' + (data.message || 'Failed to rename file'));
    }
  })
  .catch(error => {
    console.error('Rename error:', error);
    alert('Error renaming file: ' + error.message);
  });
}


uploadBtn.addEventListener('click', () => {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.multiple = true;

    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);
        if (files.length === 0) return;

        const formData = new FormData();
        files.forEach(file => {
            formData.append('files[]', file);
        });

        try {
            const response = await fetch('upload_files.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.status === 'success') {
                alert(`${files.length} file(s) uploaded successfully!`);
                window.location.reload();
            } else {
                alert('Upload failed: ' + result.message);
            }
        } catch (error) {
            alert('Upload error: ' + error.message);
        }
    });

    fileInput.click();
});

window.addEventListener('click', (e) => {
    if (e.target === previewModal) {
        previewModal.style.display = 'none';
    }
    if (e.target === groupModal) {
        groupModal.style.display = 'none';
    }
    if (e.target === moveModal) {
        moveModal.style.display = 'none';
    }
});

function loadPDFJS() {
    if (typeof pdfjsLib === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js';
        script.onload = () => {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
            renderFiles();
        };
        document.head.appendChild(script);
    } else {
        renderFiles();
    }
}

// Initialize
loadPDFJS();