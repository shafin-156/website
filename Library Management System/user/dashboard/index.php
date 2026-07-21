<?php
require_once '../../data/subConfig.php';

protect_page('student'); 

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'];

$update_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    
    if (!empty($current_pass) && !empty($new_pass)) {
        $stmt_check = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($row_user = $result_check->fetch_assoc()) {
            if (password_verify($current_pass, $row_user['password'])) {
                $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt_update->bind_param("si", $new_hashed, $user_id);
                
                if ($stmt_update->execute()) {
                    $update_msg = "<div id='status-msg' class='alert alert-success mt-3 shadow-sm'><i class='fa-solid fa-circle-check me-2'></i>Password updated successfully!</div>";
                } else {
                    $update_msg = "<div id='status-msg' class='alert alert-danger mt-3 shadow-sm'><i class='fa-solid fa-triangle-exclamation me-2'></i>Database update failed.</div>";
                }
            } else {
                // Verified: Incorrect password message now has the icon and the ID for auto-hide
                $update_msg = "<div id='status-msg' class='alert alert-danger mt-3 shadow-sm'><i class='fa-solid fa-circle-xmark me-2'></i>Current password is incorrect.</div>";
            }
        }
    } else {
        $update_msg = "<div id='status-msg' class='alert alert-warning mt-3 shadow-sm'><i class='fa-solid fa-circle-exclamation me-2'></i>Please fill in both fields.</div>";
    }
}

$filter = get_user_data_filter('i.user_id');
$query = "SELECT b.title, i.issue_date, i.return_date, i.status, u.full_name as borrower 
          FROM issued_books i 
          JOIN books b ON i.book_id = b.book_id 
          JOIN users u ON i.user_id = u.user_id
          WHERE {$filter['sql']} 
          ORDER BY i.issue_date DESC";

$stmt_books = $conn->prepare($query);
if ($filter['bind']) { $stmt_books->bind_param("i", $filter['id']); }
$stmt_books->execute();
$books_result = $stmt_books->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>User Dashboard | BIST Library</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* ----- Modern Professional Reset / Overrides ----- */
        body {
            background: #f0f4fa;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #1e293b;
        }

        /* ----- Navbar (with subtle gradient) ----- */
        .navbar {
            background: linear-gradient(95deg, #ffffff 0%, #f8fcff 100%);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 6px 22px rgba(0,20,40,0.06), 0 2px 6px rgba(0,0,0,0.02);
            border-bottom: 1px solid rgba(59,130,246,0.15);
            padding: 0.8rem 0;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0b2b4a !important;
            font-size: 1.5rem;
        }
        .navbar-brand i {
            color: #3b82f6;
            background: linear-gradient(145deg, #2563eb, #1d4ed8);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-link-custom {
            background: transparent;
            border: none;
            color: #2c3e5c !important;
            font-weight: 500;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            transition: 0.2s;
            font-size: 0.95rem;
        }
        .nav-link-custom:hover {
            background: #e0edff;
            color: #1d4ed8 !important;
        }
        .btn-logout {
            background: #b22222;
            border: 1px solid #8b1a1a;
            color: white !important;
            font-weight: 600;
            padding: 0.4rem 1.3rem;
            border-radius: 30px;
            transition: all 0.2s;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(178,34,34,0.3);
            text-decoration: none;
        }
        .btn-logout:hover {
            background: #8b1a1a;
            border-color: #6b1313;
            box-shadow: 0 4px 12px rgba(178,34,34,0.4);
            transform: translateY(-1px);
            text-decoration: none;
        }

        
        #message-container .alert {
            border-radius: 16px;
            border: none;
            font-weight: 500;
            padding: 1rem 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            background: white;
            border-left: 4px solid;
        }
        .alert-success { border-left-color: #10b981; background: #f0fdf9; }
        .alert-danger { border-left-color: #b22222; background: #fff5f5; }
        .alert-warning { border-left-color: #f59e0b; background: #fffbeb; }

        /* ----- Welcome card (more color) ----- */
        .welcome-card {
            background: white;
            border-radius: 28px;
            padding: 1.8rem 2rem;
            box-shadow: 0 16px 30px rgba(0,30,60,0.06);
            border: 1px solid #eef5ff;
            backdrop-filter: blur(4px);
            transition: 0.2s;
            background: linear-gradient(105deg, #ffffff 0%, #f9fcff 100%);
            border-bottom: 3px solid #2563eb;
        }
        .avatar-circle {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 8px 18px rgba(37,99,235,0.3);
        }
        .greeting-text {
            font-weight: 600;
            color: #0b1e33;
            letter-spacing: -0.01em;
        }
        .role-badge-modern {
            background: linear-gradient(145deg, #4f46e5, #6366f1);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 1.2rem;
            border-radius: 30px;
            border: 1px solid #a5b4fc;
            box-shadow: 0 2px 6px rgba(79,70,229,0.25);
        }

        /* ----- Table container (clean card with color pop) ----- */
        .table-container {
            background: white;
            border-radius: 28px;
            box-shadow: 0 18px 35px rgba(0,20,50,0.04);
            border: 1px solid #eaf0f6;
            overflow: hidden;
            transition: 0.2s;
        }
        .table-header {
            padding: 1.5rem 1.8rem;
            background: linear-gradient(98deg, #f8fcff, #f1f7fe);
            border-bottom: 2px solid #2563eb20;
        }
        .table-header h5 {
            font-weight: 700;
            color: #1a3a5e;
            margin: 0;
            font-size: 1.2rem;
        }
        .table-header i {
            color: #2563eb;
        }
        .table thead tr {
            background: #edf4fd !important;
            border-bottom: 2px solid #bdd3f0;
        }
        .table thead th {
            color: #1e3a5f;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 1rem 1.2rem;
            border: none;
            background: transparent;
        }
        .table thead th i {
            color: #2563eb;
            margin-right: 6px;
        }
        .table tbody tr {
            border-bottom: 1px solid #ecf3fa;
            transition: 0.15s;
        }
        .table tbody tr:hover {
            background: #f5fbff;
        }
        .table td {
            padding: 1.2rem 1.2rem;
            color: #1f3a5c;
            font-size: 0.95rem;
            vertical-align: middle;
            border: none;
        }
        .badge-issued, .badge-returned {
            font-weight: 600;
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.8rem;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .badge-issued {
            background: #fef7e6;
            color: #b6560c;
            border-color: #fddead;
        }
        .badge-returned {
            background: #e2f3e8;
            color: #0d7c5a;
            border-color: #b0dfce;
        }
        .return-date-modern {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #2d4b71;
        }
        .return-date-modern i {
            font-size: 0.8rem;
            opacity: 0.8;
            color: #2563eb;
        }

        /* ----- Footer with more color ----- */
        footer {
            font-size: 0.9rem;
            background: linear-gradient(90deg, #ffffff10, #ffffff40);
            padding: 2rem 1rem 1.5rem;
            border-top: 1px solid #cddef7;
        }
        .footer-signature {
            color: #2b4b75;
            font-weight: 500;
            letter-spacing: 0.3px;
            opacity: 0.9;
        }
        .footer-signature i {
            color: #b22222; /* blood red accent */
        }

        /* ----- Profile modal refinements with color ----- */
        .modal-content {
            border-radius: 32px;
            border: none;
            box-shadow: 0 30px 60px rgba(0,20,60,0.2);
        }
        .modal-header {
            background: linear-gradient(105deg, #f6fbff, #ecf3fc);
            border-bottom: 2px solid #2563eb30;
            border-radius: 32px 32px 0 0;
            padding: 1.5rem 1.8rem;
        }
        .modal-header h5 {
            font-weight: 700;
            color: #143056;
        }
        .modal-header .btn-close {
            filter: brightness(0.4);
            transition: 0.2s;
        }
        .modal-header .btn-close:hover {
            filter: brightness(0.2) drop-shadow(0 0 4px #b22222);
        }
        .modal-body {
            padding: 2rem 1.8rem 1.5rem;
        }
        .modal-footer {
            background: #f3f9ff;
            border-top: 1px solid #d5e5fa;
            border-radius: 0 0 32px 32px;
            padding: 1.2rem 1.8rem;
        }
        .form-label {
            font-weight: 600;
            color: #1b3a62;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        .form-label i {
            color: #2563eb;
        }
        .form-control {
            border: 1px solid #cbdaf0;
            border-radius: 20px;
            padding: 0.7rem 1.2rem;
            background: #ffffff;
            transition: 0.2s;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .btn-primary {
            background: linear-gradient(145deg, #2563eb, #1d4ed8);
            border: none;
            border-radius: 40px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(145deg, #1d4ed8, #1e3fae);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(37,99,235,0.4);
        }
        .btn-secondary {
            background: white;
            border: 1px solid #b9ceec;
            color: #23497c;
            border-radius: 40px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: 0.15s;
        }
        .btn-secondary:hover {
            background: #f0f6ff;
            border-color: #2563eb;
            color: #1d4ed8;
        }

        /* fade-out utility (for status message) */
        .fade-out {
            transition: opacity 0.8s ease-out;
            opacity: 0;
        }

        /* responsiveness touches */
        @media (max-width: 576px) {
            .welcome-card { padding: 1.2rem; }
            .avatar-circle { width: 48px; height: 48px; font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<!-- modern navbar with blood red logout -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="./">
            <i class="fa-solid fa-book-open me-2"></i> BIST LIBRARY
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="nav-link-custom" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="fa-regular fa-id-card me-2"></i> Profile
            </button>
            <a href="../../logout.php" class="btn-logout">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <!-- auto-hide message container -->
    <div id="message-container"><?php echo $update_msg; ?></div>

    <!-- welcome card (more colorful) -->
    <div class="welcome-card d-flex align-items-center gap-4 mb-5">
        <div class="avatar-circle">
            <i class="fa-regular fa-user"></i>
        </div>
        <div>
            <h2 class="greeting-text mb-1">
                <span style="font-weight: 400;">Welcome back,</span> 
                <span style="font-family: cursive; color: #1e4a8b;"><?php echo htmlspecialchars($user_name); ?></span>
            </h2>
            <div class="d-flex align-items-center gap-3">
                <span class="role-badge-modern">
                   <i class="fa-solid fa-user-shield me-1"></i> <?php echo ucfirst($user_role); ?>
                </span>
                <span class="text-secondary" style="font-size:0.85rem;">
                    <i class="fa-regular fa-circle-check text-success me-1"></i> active session
                </span>
            </div>
        </div>
    </div>

    <!-- logs table (card style) -->
    <div class="table-container">
        <div class="table-header d-flex align-items-center">
            <h5><i class="fa-regular fa-rectangle-list me-2"></i> Borrowing history</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4"><i class="fa-regular fa-bookmark"></i> Title</th>
                        <?php if($user_role === 'admin'): ?>
                            <th><i class="fa-regular fa-user"></i> Borrower</th>
                        <?php endif; ?>
                        <th><i class="fa-regular fa-calendar"></i> Issue date</th>
                        <th><i class="fa-regular fa-calendar-check"></i> Return date</th>
                        <th><i class="fa-regular fa-flag"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($books_result->num_rows > 0): ?>
                        <?php while($row = $books_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($row['title']); ?></td>
                                <?php if($user_role === 'admin'): ?>
                                    <td>
                                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                                            <i class="fa-regular fa-user me-1 text-secondary"></i> <?php echo htmlspecialchars($row['borrower']); ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="text-secondary"><?php echo date('d M Y', strtotime($row['issue_date'])); ?></td>
                                <td>
                                    <?php if (!empty($row['return_date']) && $row['return_date'] != '0000-00-00'): ?>
                                        <span class="return-date-modern">
                                            <i class="fa-regular <?php echo ($row['status'] == 'issued') ? 'fa-hourglass-half text-warning' : 'fa-circle-check text-success'; ?>"></i>
                                            <?php echo date('d M Y', strtotime($row['return_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic"><i class="fa-regular fa-calendar-xmark me-1"></i> Null</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($row['status'] == 'issued') ? 'badge-issued' : 'badge-returned'; ?>">
                                        <i class="fa-regular <?php echo ($row['status'] == 'issued') ? 'fa-clock' : 'fa-check-circle'; ?>"></i>
                                        <?php echo ($row['status'] == 'issued') ? 'Issued' : 'Returned'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5" style="color: #7a9bcb;">
                                <i class="fa-regular fa-folder-open fa-2x d-block mb-3 opacity-50"></i>
                                No borrowing logs yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- minimalist footer with blood red accent -->
<footer class="container-fluid">
    <div class="container text-end footer-signature">
        <i class="fa-solid fa-laptop-code me-2"></i></i> Developed by Shafin [CSE 2nd Batch, BIST]
    </div>
</footer>

<!-- profile modal (elegant) -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-gear me-2"></i></i> Security settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label"><i class="fa-regular fa-user me-2"></i> Full name</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="fa-solid fa-key me-2 text-danger"></i> Current password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter old password" maxlength="16" required>
                    </div>
                    <hr class="my-4" style="opacity:0.3;">
                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-key me-2 text-success"></i> New password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password" minlength="4" maxlength="16" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-2"></i> Cancel</button>
                    <button type="submit" name="update_profile" class="btn btn-primary px-5"><i class="fa-regular fa-floppy-disk me-2"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusMsg = document.getElementById('status-msg');
        if (statusMsg) {
            setTimeout(function() {
                statusMsg.classList.add('fade-out');
                setTimeout(() => statusMsg.remove(), 1000);
            }, 5000);
        }
    });
</script>
</body>
</html>