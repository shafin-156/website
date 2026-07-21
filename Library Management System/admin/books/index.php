<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

if(isset($_GET['ajax_search'])) {
    $search = $conn->real_escape_string($_GET['ajax_search']);
    $query = "SELECT b.*, c.category_name 
              FROM books b 
              LEFT JOIN categories c ON b.category_id = c.category_id 
              WHERE (b.title LIKE '%$search%' 
              OR b.isbn LIKE '%$search%' 
              OR b.author LIKE '%$search%' 
              OR c.category_name LIKE '%$search%')
              ORDER BY b.title ASC"; 
    $result = $conn->query($query);
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $s = strtoupper($row['status']);
            $statusClass = ($s == 'AVAILABLE') ? 'status-available' : (($s == 'ISSUED') ? 'status-issued' : 'status-lost');
            echo "<tr>
                <td><span class='isbn-code'>".htmlspecialchars($row['isbn'])."</span></td>
                <td><div class='text-dark'>".htmlspecialchars($row['title'])."</div></td> <!-- removed fw-semibold -->
                <td><div class='text-muted small'>".htmlspecialchars($row['author'])."</div></td>
                <td><span class='badge bg-light text-dark border'>".htmlspecialchars($row['category_name'] ?? 'Uncategorized')."</span></td>
                <td><span>".$row['quantity']."</span></td> <!-- removed fw-bold -->
                <td>
                    <span class='status-pill $statusClass'>
                        <i class='fa-solid fa-circle me-1' style='font-size: 0.35rem;'></i> $s
                    </span>
                </td>
                <td>
                    <div class='actions-wrapper'>
                        <button class='btn btn-sm btn-outline-primary btn-action border-0' 
                            onclick=\"openEdit('{$row['book_id']}','".addslashes($row['isbn'])."','".addslashes($row['title'])."','".addslashes($row['author'])."','{$row['quantity']}','{$row['status']}','{$row['category_id']}')\">
                            <i class='fa-solid fa-pen-to-square'></i>
                        </button>
                        <a href='?delete_id={$row['book_id']}' class='btn btn-sm btn-outline-danger btn-action border-0' onclick=\"return confirm('Delete record ?')\">
                            <i class='fa-solid fa-trash'></i>
                        </a>
                    </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='text-center py-5 text-muted'>No books found.</td></tr>";
    }
    exit; 
}


if(isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM books WHERE book_id = $del_id");
    header("Location: ./?msg=deleted");
    exit();
}

if(isset($_POST['add_book'])) {
    $isbn = $conn->real_escape_string($_POST['isbn']);
    $title = $conn->real_escape_string($_POST['title']);
    $writer = $conn->real_escape_string($_POST['writer']);
    $qty = (int)$_POST['quantity'];
    $category_id = (int)$_POST['category_id'];


    $check = $conn->query("SELECT book_id FROM books WHERE isbn = '$isbn' OR title = '$title'");
    if($check->num_rows > 0) {
        header("Location: ./?msg=duplicate");
    } else {
        $conn->query("INSERT INTO books (isbn, title, author, quantity, category_id, status) VALUES ('$isbn', '$title', '$writer', $qty, $category_id, 'AVAILABLE')");
        header("Location: ./?msg=added");
    }
    exit();
}

if(isset($_POST['edit_book'])) {
    $id = (int)$_POST['book_id'];
    $isbn = $conn->real_escape_string($_POST['isbn']);
    $title = $conn->real_escape_string($_POST['title']);
    $writer = $conn->real_escape_string($_POST['writer']);
    $qty = (int)$_POST['quantity'];
    $status = $conn->real_escape_string($_POST['status']);
    $category_id = (int)$_POST['category_id'];


    $check = $conn->query("SELECT book_id FROM books WHERE (isbn = '$isbn' OR title = '$title') AND book_id != $id");
    if($check->num_rows > 0) {
        header("Location: ./?msg=duplicate");
    } else {
        $conn->query("UPDATE books SET isbn='$isbn', title='$title', author='$writer', quantity=$qty, category_id=$category_id, status='$status' WHERE book_id=$id");
        header("Location: ./?msg=updated");
    }
    exit();
}

$categories_list = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
$categories_array = [];
while($cat = $categories_list->fetch_assoc()) { $categories_array[] = $cat; }

$books = $conn->query("SELECT b.*, c.category_name FROM books b LEFT JOIN categories c ON b.category_id = c.category_id ORDER BY b.title ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Book Inventory</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root { --slate: #1e293b; --blue: #3b82f6; --bg: #f8fafc; --text-muted: #94a3b8; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
       
        .sidebar { 
            width: 280px; 
            height: 100vh; 
            background: var(--slate); 
            position: fixed; 
            color: white; 
            padding: 20px; 
            z-index: 100; 
            display: flex; 
            flex-direction: column; 
        }
        
        .sidebar-brand { 
            text-align: center; 
            padding-bottom: 25px; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            margin-bottom: 20px; 
        }
        
        .nav-link { 
            color: var(--text-muted); 
            padding: 12px 15px; 
            border-radius: 8px; 
            margin-bottom: 5px; 
            transition: 0.3s; 
            text-decoration: none; 
            display: block; 
            border-left: 4px solid transparent; 
        }

        .nav-link:hover, .active-nav { 
            background: rgba(255,255,255,0.05); 
            color: white; 
            border-left-color: var(--blue); 
        }
        
      
        .sidebar-footer { 
            margin-top: auto; 
            padding: 15px 0; 
            border-top: 1px solid rgba(255,255,255,0.05);
            text-align: center;
            font-family: "Brush Script", cursive;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.3);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .main { margin-left: 280px; width: 100%; padding: 30px; }
        .inventory-card { background: white; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; }
        
        /* Table header styling - bold and slightly larger */
        .table thead { 
            background: #f8fafc; 
            color: var(--slate); 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .table thead th { 
            font-weight: 700; 
        }
        
        /* Table body styling - normal weight, slightly larger for readability */
        .table td { 
            padding: 10px 15px; 
            border-bottom: 1px solid #f1f5f9; 
            vertical-align: middle; 
            font-size: 0.95rem; 
            font-weight: 400; 
        }
        /* Ensure any inner elements also use normal weight unless specified */
        .table td .text-dark,
        .table td span:not(.status-pill):not(.badge) {
            font-weight: 400;
        }
        /* Keep status pill bold */
        .status-pill { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-weight: 700; 
            font-size: 0.7rem; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            white-space: nowrap; 
        }
        .status-available { background: #dcfce7; color: #15803d; }
        .status-issued { background: #dbeafe; color: #1d4ed8; }
        .status-lost { background: #fee2e2; color: #b91c1c; }
        
        .btn-add { background: var(--blue); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; color: white; }
        .isbn-code { font-family: 'JetBrains Mono', monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-size: 0.85rem; }
        .actions-wrapper { display: flex; gap: 4px; justify-content: flex-end; white-space: nowrap; }
        .btn-action { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
        .search-container { position: relative; width: 300px; }
        .search-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-container input { padding-left: 35px; border-radius: 8px; border: 1px solid #e2e8f0; height: 42px; font-size: 0.9rem; }
    </style>
</head>
<body>

    <div class="sidebar shadow">
    <div class="sidebar-brand">
        <h4 class="fw-bold mb-0">BIST LIBRARY</h4>
    </div>
    
    <nav>
        <a href="../dashboard/" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
        <a href="../books/" class="nav-link active-nav"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
        <a href="../students/" class="nav-link"><i class="fa-solid fa-user-graduate me-2"></i> Students</a>
        <a href="../issueRenew" class="nav-link"><i class="fa-solid fa-hand-holding-hand me-2"></i> Issue / Renew</a>
        <a href="../returnBook/" class="nav-link"><i class="fa-solid fa-rotate-left me-2"></i> Return Book</a>
        <a href="../systemUsers/" class="nav-link"><i class="fa-solid fa-users-gear me-2"></i> System Users</a>
        <a href="../printReport/" class="nav-link"><i class="fa-solid fa-print me-2"></i> Print Report</a>
        <a href="../../logout.php" class="nav-link text-danger mt-4"><i class="fa-solid fa-power-off me-2"></i> Logout</a>
    </nav>

    <div class="sidebar-footer">
        <div>
            <span><i class="fa-solid fa-code text-primary"></i> Developed by Shafin <br> CSE (2nd Batch), BIST </span>
        </div>
    </div>
</div>

    <div class="main">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-slate mb-1">Book Inventory</h4>
                <p class="text-muted small mb-0">Manage library records</p>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="liveSearch" class="form-control" placeholder="Search title, ISBN, writer...">
                </div>
                <button class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Book
                </button>
            </div>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'duplicate'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> A book with this ISBN or Title already exists in the records.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="inventory-card shadow-sm">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th width="12%">ISBN</th>
                        <th width="30%">Title</th>
                        <th width="18%">Writer/Publication</th>
                        <th width="15%">Category</th>
                        <th width="8%">Qty</th>
                        <th width="8%">Status</th>
                        <th width="9%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="bookTableBody">
                    <?php while($row = $books->fetch_assoc()): ?>
                    <tr>
                        <td><span class="isbn-code"><?php echo htmlspecialchars($row['isbn']); ?></span></td>
                        <td><div class="text-dark"><?php echo htmlspecialchars($row['title']); ?></div></td> <!-- removed fw-semibold -->
                        <td><div class="text-muted small"><?php echo htmlspecialchars($row['author']); ?></div></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span></td>
                        <td><span><?php echo $row['quantity']; ?></span></td> <!-- removed fw-bold -->
                        <td>
                            <?php 
                            $s = strtoupper($row['status']);
                            $sc = ($s == 'AVAILABLE') ? 'status-available' : (($s == 'ISSUED') ? 'status-issued' : 'status-lost');
                            ?>
                            <span class="status-pill <?php echo $sc; ?>">
                                <i class="fa-solid fa-circle me-1" style="font-size: 0.35rem;"></i> <?php echo $s; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-wrapper">
                                <button class="btn btn-sm btn-outline-primary btn-action border-0" 
                                    onclick="openEdit('<?php echo $row['book_id']; ?>','<?php echo addslashes($row['isbn']); ?>','<?php echo addslashes($row['title']); ?>','<?php echo addslashes($row['author']); ?>','<?php echo $row['quantity']; ?>','<?php echo $row['status']; ?>','<?php echo $row['category_id']; ?>')">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <a href="?delete_id=<?php echo $row['book_id']; ?>" class="btn btn-sm btn-outline-danger btn-action border-0" onclick="return confirm('Delete Book Record?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addBookModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-plus"></i> Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" placeholder="e.g CSE102030..." minlength="4" required></div>
                    <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Writer / Publication</label><input type="text" name="writer" class="form-control"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <?php foreach($categories_array as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?php echo $c['category_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" value="1" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_book" class="btn btn-primary w-100">Save Book</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editBookModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="book_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Book Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">ISBN</label><input type="text" name="isbn" id="edit_isbn" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" id="edit_title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Writer/Publication</label><input type="text" name="writer" id="edit_writer" class="form-control"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="edit_category" class="form-select">
                                <?php foreach($categories_array as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?php echo $c['category_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" id="edit_qty" class="form-control" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="AVAILABLE">AVAILABLE</option>
                            <option value="ISSUED">ISSUED</option>
                            <option value="LOST">LOST</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_book" class="btn btn-primary w-100">Update Records</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let debounceTimer;
        document.getElementById('liveSearch').addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value;
            debounceTimer = setTimeout(() => {
                fetch(`./?ajax_search=${encodeURIComponent(query)}`)
                    .then(res => res.text())
                    .then(html => { document.getElementById('bookTableBody').innerHTML = html; });
            }, 300);
        });

        function openEdit(id, isbn, title, writer, qty, status, categoryId) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_isbn').value = isbn;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_writer').value = writer;
            document.getElementById('edit_qty').value = qty;
            document.getElementById('edit_status').value = status.toUpperCase();
            document.getElementById('edit_category').value = categoryId;
            new bootstrap.Modal(document.getElementById('editBookModal')).show();
        }
        
        // This snippet was originally for full_name, title, writer; but full_name not present. Keep as is.
        const fields = ['full_name', 'title', 'writer'];
        fields.forEach(name => {
            const input = document.querySelector(`input[name="${name}"]`);
            if (input) {
                input.addEventListener('input', (e) => {
                    const start = e.target.selectionStart;
                    e.target.value = e.target.value.toLowerCase().replace(/(^|\s)\S/g, (m) => m.toUpperCase());
                    e.target.setSelectionRange(start, start);
                });
            }
        });
    </script>
</body>
</html>