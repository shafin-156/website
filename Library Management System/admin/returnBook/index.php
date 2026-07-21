<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

date_default_timezone_set('Asia/Dhaka');

$success_msg = "";
$error_msg = "";

if(isset($_GET['return_id']) && isset($_GET['book_id'])) {
    $issue_id = (int)$_GET['return_id'];
    $book_id = (int)$_GET['book_id'];
    $return_date = date('Y-m-d');

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("UPDATE issued_books SET status = 'returned', return_date = ? WHERE issue_id = ?");
        $stmt1->bind_param("si", $return_date, $issue_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("UPDATE books SET quantity = quantity + 1, status = 'AVAILABLE' WHERE book_id = ?");
        $stmt2->bind_param("i", $book_id);
        $stmt2->execute();
        
        $conn->commit();
        $success_msg = "Book returned successfully! Stock updated.";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
    }
}

$query = "SELECT i.issue_id, i.book_id, i.issue_date, b.title, u.full_name, u.username 
          FROM issued_books i 
          JOIN books b ON i.book_id = b.book_id 
          JOIN users u ON i.user_id = u.user_id 
          WHERE i.status = 'issued' 
          ORDER BY i.issue_date ASC";
$issued_list = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Return Book</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --slate: #1e293b; --blue: #3b82f6; --bg: #f8fafc; --text-muted: #94a3b8; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; display: flex; margin: 0; }
        
        .sidebar { 
            width: 280px; height: 100vh; background: var(--slate); position: fixed; color: white; padding: 20px; z-index: 1000; 
            display: flex; flex-direction: column; 
        }
        
        .sidebar-brand { text-align: center; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .nav-link { color: var(--text-muted); padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; text-decoration: none; display: block; border-left: 4px solid transparent; }
        .nav-link:hover, .active-nav { background: rgba(255,255,255,0.05); color: white; border-left-color: var(--blue); }
        
        .sidebar-footer { 
            margin-top: auto; padding: 15px 0; border-top: 1px solid rgba(255,255,255,0.05); text-align: center; font-family: "Brush Script", cursive;
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3);
            font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px;
        }

        .main { margin-left: 280px; width: 100%; padding: 40px; min-height: 100vh; }
        .card-custom { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; padding: 25px; }
        
        /* Table Hover Fix */
        .table-hover tbody tr:hover { background-color: #f1f5f9 !important; transition: 0.2s; }
        
        .return-btn { font-size: 0.75rem; font-weight: 700; padding: 8px 15px; border-radius: 8px; text-decoration: none; background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; transition: 0.3s; }
        .return-btn:hover { background: var(--blue); color: white; }
        .search-container { position: relative; max-width: 400px; margin-bottom: 20px; }
        .search-container input { padding-left: 40px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .search-container i { position: absolute; left: 15px; top: 12px; color: var(--text-muted); }
    </style>
</head>
<body>

    <div class="sidebar shadow">
        <div class="sidebar-brand"><h4 class="fw-bold mb-0">BIST LIBRARY</h4></div>
        <nav>
            <a href="../dashboard/" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
            <a href="../books/" class="nav-link"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
            <a href="../students/" class="nav-link"><i class="fa-solid fa-user-graduate me-2"></i> Students</a>
            <a href="../issueRenew" class="nav-link"><i class="fa-solid fa-hand-holding-hand me-2"></i> Issue / Renew</a>
            <a href="../returnBook/" class="nav-link active-nav"><i class="fa-solid fa-rotate-left me-2"></i> Return Book</a>
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
            <h3 class="fw-bold text-slate mb-1">Return Management</h3>
            <p class="text-muted small">Select an issued book record to process the return and update stock.</p>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card-custom shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Active Issues List</h5>
                <div class="search-container shadow-sm mb-0">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="returnSearch" class="form-control" placeholder="Search by student ID or title...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="returnTable">
                    <thead class="small text-muted text-uppercase bg-light">
                        <tr>
                            <th class="ps-3">Book Details</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Issue Date</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($issued_list->num_rows > 0): ?>
                            <?php while($row = $issued_list->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['title']); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['username']); ?></span></td>
                                <td class="small fw-bold text-slate"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="small text-muted"><?php echo date('d M, Y', strtotime($row['issue_date'])); ?></td>
                                <td class="text-end">
                                    <a href="?return_id=<?php echo $row['issue_id']; ?>&book_id=<?php echo $row['book_id']; ?>" 
                                       class="return-btn" onclick="return confirm('Confirm returning this book?')">
                                        <i class="fa-solid fa-rotate-left me-1"></i> MARK RETURN
                                    </a>
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
    <script>
        $(document).ready(function() {
            setTimeout(function() { $(".alert").fadeOut('slow'); }, 5000);
            $("#returnSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#returnTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>
</body>
</html>