<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

if(isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM users WHERE user_id = $del_id AND role = 'student'");
    header("Location: ./?msg=deleted");
    exit();
}

if(isset($_POST['add_student'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $mobile_no = $conn->real_escape_string($_POST['mobile_no']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $check = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if($check->num_rows > 0) {
        header("Location: ./?msg=duplicate");
    } else {
        $conn->query("INSERT INTO users (username, password, full_name, mobile_no, role) VALUES ('$username', '$password', '$full_name', '$mobile_no', 'student')");
        header("Location: ./?msg=added");
    }
    exit();
}

if(isset($_POST['edit_student'])) {
    $id = (int)$_POST['user_id'];
    $username = $conn->real_escape_string($_POST['username']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $mobile_no = $conn->real_escape_string($_POST['mobile_no']);
    
    $check = $conn->query("SELECT user_id FROM users WHERE username = '$username' AND user_id != $id");
    if($check->num_rows > 0) {
        header("Location: ./?msg=duplicate");
    } else {
        $sql = "UPDATE users SET username='$username', full_name='$full_name', mobile_no='$mobile_no' WHERE user_id=$id";
        if(!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username='$username', full_name='$full_name', mobile_no='$mobile_no', password='$pass' WHERE user_id=$id";
        }
        $conn->query($sql);
        header("Location: ./?msg=updated");
    }
    exit();
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql = "SELECT * FROM users WHERE role = 'student'";
if(!empty($search)) {
    $sql .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%' OR mobile_no LIKE '%$search%')";
}
$sql .= " ORDER BY user_id DESC";
$students = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Student Management</title>
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
        .main { margin-left: 280px; width: 100%; padding: 40px; }
        .student-card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); overflow: hidden; }
        
        .student-card .table thead {font-size: 1rem; font-family: sans-serif;}
        .student-card .table td {font-size: 1rem; padding: 14px 20px; vertical-align: middle; }
        .student-card .table th {
            text-align: center;
            font-size: 1rem;
            padding: 14px 20px;
            vertical-align: middle;
        }
        
       
        .student-card .table tbody tr:hover {
            background-color: #e3e4e6 !important; 
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
       
        .student-card .table tbody tr:hover td {
            background-color: #e3e4e6 !important;
        }
        
        .student-card .user-avatar { 
            font-size: 1rem;
            width: 40px; 
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--blue);
            font-weight: bold;
        }
        .student-card .search-input { 
            font-size: 1rem;
        }
        .student-card .search-icon { 
            font-size: 1rem;
        }
        .student-card .fw-bold { 
            font-size: 1rem;
        }
        .student-card .badge { 
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        .student-card .text-muted { 
            font-size: 0.95rem;
        }
        
        
        .student-card .card-header {
            display: flex;
            justify-content: flex-end;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        
        .student-card .table td:first-child {
            text-align: left;
        }
        .student-card .table td:nth-child(2),
        .student-card .table td:nth-child(3),
        .student-card .table td:nth-child(4) {
            text-align: center;
        }
        
        .btn-add { background: var(--blue); border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .search-container { position: relative; width: 300px; }
        .search-input { padding-left: 35px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        
        /* Make table headers bold */
        .student-card .table th {
            font-weight: 700 !important;
        }
        
        /* Make all table body text normal (including .fw-bold inside) */
        .student-card .table tbody,
        .student-card .table tbody td,
        .student-card .table tbody .fw-bold {
            font-weight: 400 !important;
        }
    </style>
</head>
<body>

    <div class="sidebar shadow">
        <div class="sidebar-brand"><h4 class="fw-bold mb-0">BIST LIBRARY</h4></div>
        <nav>
            <a href="../dashboard/" class="nav-link"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
            <a href="../books/" class="nav-link"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
            <a href="../students/" class="nav-link active-nav"><i class="fa-solid fa-user-graduate me-2"></i> Students</a>
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
                <h3 class="fw-bold text-slate mb-1">Student Management</h3>
                <p class="text-muted small mb-0">Total Students Registered: <span class="fw-bold text-dark"><?php echo $students->num_rows; ?></span></p>
            </div>
            <button class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fa-solid fa-plus me-2"></i> ADD NEW STUDENT
            </button>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-info border-0 shadow-sm py-2 mb-4">
                <?php 
                    if($_GET['msg'] == 'added') echo "Success: New student added.";
                    if($_GET['msg'] == 'updated') echo "Success: Student record updated.";
                    if($_GET['msg'] == 'deleted') echo "Success: Student deleted.";
                    if($_GET['msg'] == 'duplicate') echo "Error: Student ID already exists.";
                ?>
            </div>
        <?php endif; ?>

        <div class="student-card shadow-sm">
            <!-- Header with search only, aligned right -->
            <div class="card-header">
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="realTimeSearch" class="form-control search-input" placeholder="Search by name, ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0" id="studentTable">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID (Username)</th>
                            <th>Mobile No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php if($students->num_rows > 0): ?>
                            <?php while($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3"><?php echo strtoupper(substr($row['full_name'], 0, 1)); ?></div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['username']); ?></span></td>
                                <td class="text-muted"><?php echo htmlspecialchars($row['mobile_no']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary border-0 me-2" onclick="openEdit('<?php echo $row['user_id']; ?>', '<?php echo $row['username']; ?>', '<?php echo htmlspecialchars($row['full_name']); ?>', '<?php echo $row['mobile_no']; ?>')">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <a href="?delete_id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Delete this student?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST" class="p-4">
                    <h5 class="fw-bold mb-4"><i class="fa-solid fa-plus"></i> Add Student</h5>
                    <div class="mb-3"><label class="small fw-bold">FULL NAME</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Full Name"></div>
                    <div class="mb-3"><label class="small fw-bold">MOBILE NO</label>
                        <input type="text" name="mobile_no" class="form-control" required placeholder="017XXXXXXXX" minlength="11" maxlength="11"></div>
                    <div class="mb-3"><label class="small fw-bold">STUDENT ID (Username)</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. CSE22705020...." minlength="13"></div>
                    <div class="mb-4"><label class="small fw-bold">PASSWORD (Length 8 - 16) </label>
                        <input type="text" name="password" class="form-control" value="12345678" placeholder="Password" minlength="8" maxlength="16" required></div>
                    <button type="submit" name="add_student" class="btn btn-primary w-100 py-2">REGISTER STUDENT</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST" class="p-4">
                    <h5 class="fw-bold mb-4">Edit Student Record</h5>
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3"><label class="small fw-bold">FULL NAME</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required></div>
                    <div class="mb-3"><label class="small fw-bold">MOBILE NO</label>
                        <input type="text" name="mobile_no" id="edit_mobile_no" class="form-control" minlength="11" maxlength="11" required></div>
                    <div class="mb-3"><label class="small fw-bold">STUDENT ID (Username)</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required></div>
                    <div class="mb-4"><label class="small fw-bold text-danger">NEW PASSWORD (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" minlength="8" maxlength="16"></div>
                    <button type="submit" name="edit_student" class="btn btn-primary w-100 py-2">UPDATE RECORD</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEdit(id, user, name, mobile) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = user;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_mobile_no').value = mobile;
            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }
        
        document.querySelector('input[name="full_name"]').addEventListener('input', (e) => {
            const start = e.target.selectionStart;
            e.target.value = e.target.value.toLowerCase().replace(/(^|\s)\S/g, (match) => match.toUpperCase());
            e.target.setSelectionRange(start, start);
        });

        // Real-time search logic
        document.getElementById('realTimeSearch').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#studentTableBody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.cells.length === 1 && row.cells[0].colSpan === 4) return;

                const name = row.cells[0]?.innerText.toLowerCase() || '';
                const studentId = row.cells[1]?.innerText.toLowerCase() || '';
                const mobile = row.cells[2]?.innerText.toLowerCase() || '';

                if (name.includes(filter) || studentId.includes(filter) || mobile.includes(filter)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const tbody = document.getElementById('studentTableBody');
            const noResultsRow = document.getElementById('noResultsRow');
            
            if (visibleCount === 0 && rows.length > 0) {
                if (!noResultsRow) {
                    const newRow = document.createElement('tr');
                    newRow.id = 'noResultsRow';
                    newRow.innerHTML = '<td colspan="4" class="text-center py-5 text-muted">No matching students found.</td>';
                    tbody.appendChild(newRow);
                }
            } else {
                if (noResultsRow) noResultsRow.remove();
            }
        });

        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                document.getElementById('realTimeSearch').value = searchParam;
                document.getElementById('realTimeSearch').dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>