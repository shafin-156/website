<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

date_default_timezone_set('Asia/Dhaka');

$error_msg = "";
$success_msg = "";

if(isset($_GET['renew_id'])) {
    $old_issue_id = (int)$_GET['renew_id'];
    $current_time = date('Y-m-d H:i:s'); 

    $fetch_stmt = $conn->prepare("SELECT book_id, user_id FROM issued_books WHERE issue_id = ? AND status = 'issued'");
    $fetch_stmt->bind_param("i", $old_issue_id);
    $fetch_stmt->execute();
    $old_data = $fetch_stmt->get_result()->fetch_assoc();

    if ($old_data) {
        $book_id = $old_data['book_id'];
        $user_id = $old_data['user_id'];

        $update_stmt = $conn->prepare("UPDATE issued_books SET return_date = ?, status = 'returned' WHERE issue_id = ?");
        $update_stmt->bind_param("si", $current_time, $old_issue_id);
        $update_stmt->execute();

        $insert_stmt = $conn->prepare("INSERT INTO issued_books (book_id, user_id, issue_date, status) VALUES (?, ?, ?, 'issued')");
        $insert_stmt->bind_param("iis", $book_id, $user_id, $current_time);
        
        if($insert_stmt->execute()) {
            header("Location: ./?msg=renewed");
            exit();
        }
    }
}


if(isset($_POST['issue_book'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = (int)$_POST['user_id'];
    $issue_date = date('Y-m-d H:i:s'); 

    $limit_stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM issued_books WHERE user_id = ? AND status = 'issued'");
    $limit_stmt->bind_param("i", $user_id);
    $limit_stmt->execute();
    $limit_res = $limit_stmt->get_result()->fetch_assoc();

    if ($limit_res['active_count'] >= 2) {
        $error_msg = "Transaction Denied: This student already has 2 books issued.";
    } else {

        $book_stmt = $conn->prepare("SELECT title, quantity FROM books WHERE book_id = ?");
        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();
        $book_data = $book_stmt->get_result()->fetch_assoc();

        if ($book_data['quantity'] <= 0) {
            $error_msg = "Stock Exhausted for '" . $book_data['title'] . "'.";
        } else {

            $stmt = $conn->prepare("INSERT INTO issued_books (book_id, user_id, issue_date, status) VALUES (?, ?, ?, 'issued')");
            $stmt->bind_param("iis", $book_id, $user_id, $issue_date);
            
            if($stmt->execute()) {

                $conn->query("UPDATE books SET quantity = quantity - 1 WHERE book_id = $book_id");
                $conn->query("UPDATE books SET status = 'ISSUED' WHERE book_id = $book_id AND quantity = 0");
                $success_msg = "Book issued successfully.";
            }
        }
    }
}


$books_list = $conn->query("SELECT * FROM books ORDER BY title ASC");
$students_list = $conn->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name ASC");

$active_issues = $conn->query("SELECT i.*, b.title, u.full_name, u.username FROM issued_books i 
                              JOIN books b ON i.book_id = b.book_id 
                              JOIN users u ON i.user_id = u.user_id 
                              WHERE i.status = 'issued'
                              ORDER BY i.issue_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Issue & Renew</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
        }
        
        .sidebar-brand { text-align: center; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .nav-link { color: var(--text-muted); padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; text-decoration: none; display: block; border-left: 4px solid transparent; }
        .nav-link:hover, .active-nav { background: rgba(255,255,255,0.05); color: white; border-left-color: var(--blue); }
        
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

        .main { margin-left: 280px; width: 100%; padding: 40px; min-height: 100vh; }
        .card-custom { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; margin-bottom: 30px; }
        
        .renew-btn { font-size: 0.8rem; font-weight: 700; padding: 8px 12px; border-radius: 6px; text-decoration: none; background: #ecfdf5; color: #10b981; border: 1px solid #10b981; display: inline-block; transition: 0.2s; }
        .renew-btn:hover { background: #10b981; color: white; }
        
        .select2-container--default .select2-selection--single { height: 45px; border-radius: 8px; padding-top: 8px; border: 1px solid #e2e8f0; }
        .search-container { position: relative; max-width: 300px; }
        .search-container input { padding-left: 35px; border-radius: 10px; }
        .search-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        #historyTable tbody tr {transition: background-color 0.2s ease; cursor: default;}
        #historyTable tbody tr:hover {background-color: rgba(59, 130, 246, 0.04) !important;}
        
    </style>
</head>
<body>

    <div class="sidebar shadow">
        <div class="sidebar-brand"><h4 class="fw-bold mb-0">BIST LIBRARY</h4></div>
        <nav>
            <a href="../dashboard/" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
            <a href="../books/" class="nav-link"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
            <a href="../students/" class="nav-link"><i class="fa-solid fa-user-graduate me-2"></i> Students</a>
            <a href="../issueRenew" class="nav-link active-nav"><i class="fa-solid fa-hand-holding-hand me-2"></i> Issue / Renew</a>
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
        <div class="mb-4">
            <h3 class="fw-bold text-slate mb-1">Issue / Renew System</h3>
            <p class="text-muted small">GMT+6:00 Dhaka : <strong><?php echo date('h:i A, d M Y'); ?></strong></p>
        </div>

        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if($success_msg || isset($_GET['msg'])): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                <?php echo $success_msg ?: (isset($_GET['msg']) && $_GET['msg'] == 'renewed' ? "Book renewed (closed old & opened new session)!" : "Action completed!"); ?>
            </div>
        <?php endif; ?>

        <div class="card-custom p-4 shadow-sm">
            <h5 class="fw-bold mb-4 text-slate"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Assign Book</h5>
            <form method="POST" class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label class="small fw-bold text-muted mb-2 text-uppercase">Select Book Title</label>
                    <select name="book_id" class="form-select select2" required>
                        <option value="">Search Title...</option>
                        <?php $books_list->data_seek(0); while($b = $books_list->fetch_assoc()): ?>
                            <option value="<?php echo $b['book_id']; ?>"><?php echo htmlspecialchars($b['title']); ?> (Stock: <?php echo $b['quantity']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="small fw-bold text-muted mb-2 text-uppercase">Select Student</label>
                    <select name="user_id" class="form-select select2" required>
                        <option value="">Search Student...</option>
                        <?php $students_list->data_seek(0); while($s = $students_list->fetch_assoc()): ?>
                            <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?> (<?php echo $s['username']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <button type="submit" name="issue_book" class="btn btn-primary w-100 py-2 fw-bold" style="height: 45px;">ISSUE BOOK</button>
                </div>
            </form>
        </div>

        <div class="card-custom p-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-slate mb-0"><i class="fa-solid fa-book me-2 text-primary"></i>Active Issues</h5>
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search active issues...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="historyTable">
                    <thead class="small text-muted text-uppercase bg-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Issue Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($active_issues->num_rows > 0): ?>
                            <?php while($r = $active_issues->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold small text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($r['title']); ?></div>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($r['username']); ?></td>
                                <td class="small fw-bold"><?php echo htmlspecialchars($r['full_name']); ?></td>
                                <td class="small">
                                    <span class="text-primary fw-bold"><?php echo date('h:i A', strtotime($r['issue_date'])); ?></span><br>
                                    <span class="text-muted" style="font-size: 0.7rem;"><?php echo date('d M, Y', strtotime($r['issue_date'])); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="?renew_id=<?php echo $r['issue_id']; ?>" 
                                       class="renew-btn" title="Renew" onclick="return confirm('Will You Renew This Book Session ?')">
                                        <i class="fa-solid fa-sync me-1"></i> Renew</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">No books are currently issued.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({ width: '100%' });
            setTimeout(function() { $(".alert").fadeOut('slow'); }, 5000);
            
            $("#tableSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#historyTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>
</body>
</html>