<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

date_default_timezone_set('Asia/Dhaka');


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$date_filter_fine = "";
$date_filter_history = "";

if (!empty($start_date) && !empty($end_date)) {
    
    $s = $conn->real_escape_string($start_date);
    $e = $conn->real_escape_string($end_date);
    
    $date_filter_fine = " AND i.issue_date BETWEEN '$s' AND '$e'";
    $date_filter_history = " WHERE i.issue_date BETWEEN '$s' AND '$e'";
}


$current_user_id = $_SESSION['user_id']; 
$logged_user = $conn->query("SELECT full_name, role FROM users WHERE user_id = '$current_user_id'")->fetch_assoc();

$books_stats = $conn->query("SELECT COUNT(*) as total_titles, SUM(quantity) as total_qty FROM books")->fetch_assoc();
$active_issues = $conn->query("SELECT COUNT(*) as active FROM issued_books WHERE status = 'issued'")->fetch_assoc();


$fine_query = "SELECT i.*, b.title, u.full_name, u.username, 
               DATEDIFF(CURRENT_DATE, i.issue_date) as days_kept 
               FROM issued_books i 
               JOIN books b ON i.book_id = b.book_id 
               JOIN users u ON i.user_id = u.user_id 
               WHERE i.status = 'issued' $date_filter_fine
               HAVING days_kept > 7 
               ORDER BY days_kept DESC";
$fine_list = $conn->query($fine_query);


$issues_history_query = "SELECT i.issue_date, i.return_date, b.title, u.full_name, u.username 
                         FROM issued_books i 
                         JOIN books b ON i.book_id = b.book_id 
                         JOIN users u ON i.user_id = u.user_id 
                         $date_filter_history
                         ORDER BY i.issue_date DESC";
$issues_history = $conn->query($issues_history_query);

$all_books = $conn->query("SELECT b.*, c.category_name FROM books b LEFT JOIN categories c ON b.category_id = c.category_id ORDER BY b.title ASC");

$cat_summary = $conn->query("SELECT c.category_name, COUNT(b.book_id) as total FROM categories c LEFT JOIN books b ON c.category_id = b.category_id GROUP BY c.category_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Library Report</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --slate: #1e293b; --blue: #3b82f6; --bg: #f8fafc; --text-muted: #94a3b8; --danger-red: #ef4444; }
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
        .card-custom { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 25px; }
        .fine-row { background-color: #fef2f2 !important; color: var(--danger-red) !important; font-weight: bold; }
        .print-btn-container { position: sticky; top: 20px; z-index: 999; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        @media print {
            .sidebar, .print-btn-container, .no-print, .filter-section { display: none !important; }
            .main { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            .card-custom { border: 1px solid #ddd !important; box-shadow: none !important; margin-bottom: 15px; }
            body { background: white !important; }
        }
    </style>
</head>
<body>

    <div class="sidebar shadow no-print">
        <div class="sidebar-brand"><h4 class="fw-bold mb-0">BIST LIBRARY</h4></div>
        <nav>
            <a href="../dashboard/" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
            <a href="../books/" class="nav-link"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
            <a href="../students/" class="nav-link"><i class="fa-solid fa-user-graduate me-2"></i> Students</a>
            <a href="../issueRenew" class="nav-link"><i class="fa-solid fa-hand-holding-hand me-2"></i> Issue / Renew</a>
            <a href="../returnBook/" class="nav-link"><i class="fa-solid fa-rotate-left me-2"></i> Return Book</a>
            <a href="../systemUsers/" class="nav-link"><i class="fa-solid fa-users-gear me-2"></i> System Users</a>
            <a href="../printReport/" class="nav-link active-nav"><i class="fa-solid fa-print me-2"></i> Print Report</a>
            <a href="../../logout.php" class="nav-link text-danger mt-4"><i class="fa-solid fa-power-off me-2"></i> Logout</a>
        </nav>

        <div class="sidebar-footer">
            <div>
                <span><i class="fa-solid fa-code text-primary"></i> Developed by Shafin <br> CSE (2nd Batch), BIST </span>
            </div>
        </div>
    </div>

    <div class="main">
        <div class="print-btn-container">
            <form method="GET" class="d-flex gap-2 no-print align-items-center bg-white p-2 rounded border border-dark shadow-sm">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>" required>
                <span class="small text-muted">to</span>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>" required>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if($start_date): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>

            <button onclick="window.print()" class="btn btn-dark shadow-sm fw-bold">
                <i class="fa-solid fa-print me-2"></i> Print (Ctrl P)
            </button>
        </div>

        <div class="text-center mb-4">
            <h1 class="fw-bold">BIST LIBRARY CENTRAL REPORT</h1>
            <?php if($start_date && $end_date): ?>
                <p class="badge bg-primary">Filtered: <?php echo date('d M, Y', strtotime($start_date)); ?> To <?php echo date('d M, Y', strtotime($end_date)); ?></p>
            <?php endif; ?>
            <p class="text-muted small mb-0">Generated Date: <?php echo date('d M, Y | h:i A'); ?></p>
            <p class="text-muted small">Generated By: <strong><?php echo htmlspecialchars($logged_user['full_name']); ?> (<?php echo ucfirst($logged_user['role']); ?>)</strong></p>
        </div>

        <div class="card-custom border-danger">
            <h5 class="fw-bold text-danger mb-3"><i class="fa-solid fa-triangle-exclamation me-2"></i>Overdue / Fine List (Past 7 Days)</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="bg-danger text-white small">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Book Title</th>
                            <th>Issued On</th>
                            <th class="text-center">Days Held</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if($fine_list->num_rows > 0): ?>
                            <?php while($f = $fine_list->fetch_assoc()): ?>
                            <tr class="fine-row">
                                <td><?php echo htmlspecialchars($f['username']); ?></td>
                                <td><?php echo htmlspecialchars($f['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($f['title']); ?></td>
                                <td><?php echo date('d M, Y', strtotime($f['issue_date'])); ?></td>
                                <td class="text-center"><?php echo $f['days_kept']; ?> Days</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No overdue books found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check me-2 text-info"></i>Book Issues Details</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="bg-light small">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Book Name</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if($issues_history->num_rows > 0): ?>
                            <?php while($ih = $issues_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ih['username']); ?></td>
                                <td><?php echo htmlspecialchars($ih['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($ih['title']); ?></td>
                                <td><?php echo date('d M, Y', strtotime($ih['issue_date'])); ?></td>
                                <td>
                                    <?php 
                                        echo ($ih['return_date'] && $ih['return_date'] != '0000-00-00') 
                                        ? date('d M, Y', strtotime($ih['return_date'])) 
                                        : '<span class="text-muted">NULL</span>'; 
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No issue records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-book me-2 text-primary"></i>Current Inventory Detail</h5>
            <table class="table table-sm table-bordered">
                <thead class="bg-light small">
                    <tr>
                        <th>ISBN</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th class="text-center">Stock</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php while($b = $all_books->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['isbn']); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($b['title']); ?></td>
                        <td><?php echo htmlspecialchars($b['author']); ?></td>
                        <td><?php echo htmlspecialchars($b['category_name']); ?></td>
                        <td class="text-center"><?php echo $b['quantity']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card-custom bg-light border-0 shadow-sm">
            <h5 class="fw-bold text-slate mb-4"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Summary Details</h5>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h6 class="fw-bold small text-muted text-uppercase mb-3">System Stock Health</h6>
                    <div class="p-3 bg-white rounded border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Total Unique Book Titles: </span>
                            <span class="fw-bold"><?php echo $books_stats['total_titles']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Physical Book Copies in Library: </span>
                            <span class="fw-bold text-success"><?php echo $books_stats['total_qty']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="small">Books Currently Borrowed: </span>
                            <span class="fw-bold text-warning"><?php echo $active_issues['active']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <h6 class="fw-bold small text-muted text-uppercase mb-3">Resource Distribution</h6>
                    <div class="p-3 bg-white rounded border">
                        <?php while($cat = $cat_summary->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                            <span class="small"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                            <span class="badge rounded-pill" style="color: black; font-size: 0.875rem;"><?php echo $cat['total']; ?> Books</span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="p-3 bg-white rounded border border-start border-4 border-primary">
                        <h6 class="fw-bold small mb-2 text-primary"><i class="fa-solid fa-scroll"></i> Library Policy Reminder</h6>
                        <ul class="small text-muted mb-0">
                            <li>Standard lending period for all students is <strong>7 Days</strong>.</li>
                            <li>Damage or loss of books must be reported immediately to the Librarian.</li>
                            <li>This document serves as an official summary of the library management records.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 text-center d-none d-print-block">
            <div class="row pt-5">
                <div class="col-6"><p class="border-top pt-2 w-50 mx-auto small">Librarian Signature</p></div>
                <div class="col-6"><p class="border-top pt-2 w-50 mx-auto small">Authorized Authority</p></div>
            </div>
        </div>
    </div>

</body>
</html>