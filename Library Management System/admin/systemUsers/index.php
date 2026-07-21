<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

date_default_timezone_set('Asia/Dhaka');

$success_msg = "";
$error_msg = "";

if(isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    $check_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $res = $check_stmt->get_result()->fetch_assoc();

    if($delete_id == $_SESSION['user_id']) {
        $error_msg = "You cannot delete your own administrative account.";
    } elseif ($res && $res['username'] === 'admin') {
        $error_msg = "The system 'admin' account cannot be deleted.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $delete_id);
        if($stmt->execute()) {
            header("Location: ./?msg=deleted");
            exit();
        }
    }
}

if(isset($_POST['save_user'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $mobile_no = $_POST['mobile_no'];
    $role = $_POST['role'];
    $user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;

    $dup_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $dup_check->bind_param("si", $username, $user_id);
    $dup_check->execute();
    $dup_result = $dup_check->get_result();

    if($dup_result->num_rows > 0) {
        $error_msg = "Error: The username '$username' is already taken.";
    } else {
        if($user_id > 0) {
            if(!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, mobile_no=?, role=?, password=? WHERE user_id=?");
                $stmt->bind_param("sssssi", $full_name, $username, $mobile_no, $role, $password, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, mobile_no=?, role=? WHERE user_id=?");
                $stmt->bind_param("ssssi", $full_name, $username, $mobile_no, $role, $user_id);
            }
            if($stmt->execute()) { 
                header("Location: index.php?msg=updated");
                exit();
            } else { $error_msg = "Update failed."; }
        } else {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, mobile_no, role, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $username, $mobile_no, $role, $password);
            if($stmt->execute()) { 
                header("Location: index.php?msg=created");
                exit();
            } else { $error_msg = "Creation failed."; }
        }
    }
}

if(isset($_GET['msg'])) {
    if($_GET['msg'] == 'deleted') $success_msg = "User deleted successfully.";
    if($_GET['msg'] == 'updated') $success_msg = "User updated successfully!";
    if($_GET['msg'] == 'created') $success_msg = "User created successfully!";
}

$users_list = $conn->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | User Management</title>
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
            margin-top: auto; padding: 15px 0; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;
            font-family: "Brush Script", cursive; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
            color: rgba(255,255,255,0.3); font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px;
        }

        .main { margin-left: 280px; width: 100%; padding: 40px; min-height: 100vh; }
        .card-custom { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; padding: 25px; margin-bottom: 30px; }
        .badge-admin { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; font-size: 0.7rem; }
        .badge-student { background: #e0f2fe; color: #0ea5e9; border: 1px solid #bae6fd; font-size: 0.7rem; }
        .search-container { position: relative; max-width: 350px; }
        .search-container input { padding-left: 40px; border-radius: 10px; height: 40px; }
        .search-container i { position: absolute; left: 15px; top: 12px; color: var(--text-muted); }
        
        /* FIXED BUTTON SHAPE CSS */
        .btn-action { 
            border: none; 
            background: transparent; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            width: 32px; 
            height: 32px; 
            transition: 0.2s; 
            border-radius: 8px; 
            cursor: pointer;
            text-decoration: none;
            color: var(--btn-color);
        }
        .btn-edit { --btn-color: #3b82f6; }
        .btn-delete { --btn-color: #ef4444; }
        .btn-action:hover { 
            background-color: var(--btn-color); 
            color: white !important; 
        }
        
        .user-row:hover { background-color: #f1f5f9; }
        .user-row:hover td { background-color: inherit; }
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
            <a href="../returnBook/" class="nav-link"><i class="fa-solid fa-rotate-left me-2"></i> Return Book</a>
            <a href="../systemUsers/" class="nav-link active-nav"><i class="fa-solid fa-users-gear me-2"></i> System Users</a>
            <a href="../printReport/" class="nav-link"><i class="fa-solid fa-print me-2"></i> Print Report</a>
            <a href="../../logout.php" class="nav-link text-danger mt-4"><i class="fa-solid fa-power-off me-2"></i> Logout</a>
        </nav>

        <div class="sidebar-footer">
            <div><span><i class="fa-solid fa-code text-primary"></i> Developed by Shafin <br> CSE (2nd Batch), BIST </span></div>
        </div>
    </div>

    <div class="main">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-slate mb-1">User Management</h3>
                <p class="text-muted small">Manage administrative and student access levels.</p>
            </div>
            <div class="search-container shadow-sm">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="userSearch" class="form-control border-0" placeholder="Search system users...">
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="card-custom shadow-sm sticky-top" style="top: 40px;" id="user-form-card">
                    <h5 class="fw-bold mb-4" id="form-title"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Create New User</h5>
                    <form method="POST">
                        <input type="hidden" name="target_user_id" id="target_user_id" value="0">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">FULL NAME</label>
                            <input type="text" name="full_name" id="f_name" class="form-control" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">USERNAME (FOR LOGIN)</label>
                            <input type="text" name="username" id="u_name" class="form-control" placeholder="Enter username" minlength="5" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">MOBILE NUMBER</label>
                            <input type="text" name="mobile_no" id="u_mobile" class="form-control" placeholder="017XXXXXXXX" minlength="11" maxlength="11" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">ACCOUNT ROLE</label>
                            <select name="role" id="u_role" class="form-select" style="color: red; font-weight: bold;" required>
                                <option value="admin">Admin</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="small fw-bold text-muted mb-2" id="pass-label">PASSWORD (Length 8 - 16) </label>
                            <input type="password" name="password" id="u_pass" class="form-control" placeholder="New Password" minlength="8" maxlength="16" required>
                            <small class="text-info d-none" id="pass-hint">Leave blank to keep existing password.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="save_user" id="submit-btn" class="btn btn-primary w-100 py-2 fw-bold text-uppercase">Create Account</button>
                            <a href="index.php" id="cancel-btn" class="btn btn-light d-none"><i class="fa-solid fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-custom shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle" id="userTable">
                            <thead class="small text-muted text-uppercase bg-light">
                                <tr>
                                    <th class="ps-3">Name & Username</th>
                                    <th>Mobile No.</th>
                                    <th>Role</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $users_list->fetch_assoc()): ?>
                                <tr class="user-row">
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                                <?php echo substr($u['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold mb-0"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                                <div class="text-muted small"><i class="fa-regular fa-circle-user"></i> <?php echo htmlspecialchars($u['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><div class="small fw-medium"><?php echo htmlspecialchars($u['mobile_no']); ?></div></td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo $u['role'] == 'admin' ? 'badge-admin' : 'badge-student'; ?>">
                                            <?php echo strtoupper($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" class="btn-action btn-edit me-0" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                                                                
                                       <?php if($u['username'] == 'admin' || $u['user_id'] == $_SESSION['user_id']): ?>
                                        <a style="color: #808080 !important;" class="btn-action" title="Delete Restricted"><i class="fa-solid fa-trash"></i></a>
                                        
                                        <?php else: ?>
                                        <a href="?delete_id=<?php echo $u['user_id']; ?>" class="btn-action btn-delete me-0" onclick="return confirm('Delete this account ?')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function editUser(user) {
            const { user_id, full_name, username, mobile_no, role } = user;
            
            $('#target_user_id').val(user_id);
            $('#f_name').val(full_name);
            $('#u_name').val(username);
            $('#u_mobile').val(mobile_no);
            $('#u_role').val(role);
            
            $('#form-title').html('<i class="fa-solid fa-user-pen me-2 text-warning"></i>Edit User Account');
            $('#u_pass').removeAttr('required');
            $('#pass-hint').removeClass('d-none');
            $('#pass-label').text('New Password');
            
            $('#submit-btn').text('Update Account').removeClass('btn-primary').addClass('btn-warning');
            $('#cancel-btn').removeClass('d-none');
            
            if (username === 'admin') {
                $('#f_name, #u_name, #u_mobile, #u_pass').prop('readonly', true);
                $('#u_role').prop('disabled', true);
                $('#submit-btn').prop('disabled', true).text('PROTECTED').removeClass('btn-warning').addClass('btn-secondary');
            } else {
                $('#f_name, #u_name, #u_mobile, #u_pass').prop('readonly', false);
                $('#u_role').prop('disabled', false);
                $('#submit-btn').prop('disabled', false);
            }
            $('html, body').animate({ scrollTop: $("#user-form-card").offset().top - 40 }, 200);
        }

        $(document).ready(function() {
            setTimeout(function() { $(".alert").fadeOut('slow'); }, 4000);

            $("#userSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#userTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            const input = document.getElementById('f_name');
            input.addEventListener('input', (e) => {
                const start = e.target.selectionStart;
                e.target.value = e.target.value.toLowerCase().replace(/(^|\s)\S/g, (m) => m.toUpperCase());
                e.target.setSelectionRange(start, start);
            });
        });
    </script>
</body>
</html>