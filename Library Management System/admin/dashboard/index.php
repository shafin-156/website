<?php 
require_once '../../data/subConfig.php'; 
protect_page('admin');

$book_res = $conn->query("SELECT SUM(quantity) as total_qty FROM books");
$total_books = $book_res->fetch_assoc()['total_qty'] ?? 0;

$labels = []; $data = [];
$date_map = [];
$start_date = date('Y-m-d', strtotime("-29 days"));

$graph_res = $conn->query("SELECT DATE(issue_date) as d, COUNT(*) as count 
                           FROM issued_books 
                           WHERE issue_date >= '$start_date' 
                           GROUP BY DATE(issue_date)");

while($row = $graph_res->fetch_assoc()) {
    $date_map[$row['d']] = $row['count'];
}

for($i = 29; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($date_key));
    $data[] = $date_map[$date_key] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIST | Analytical Dashboard</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="../../assets/fontawesome7/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stat-card { 
            background: white; 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            transition: 0.3s; 
        }

     

        .log-container::-webkit-scrollbar { width: 6px; }
        .log-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>

<div class="sidebar shadow">
    <div class="sidebar-brand">
        <h4 class="fw-bold mb-0">BIST LIBRARY</h4>
    </div>
    
    <nav>
        <a href="../dashboard/" class="nav-link active-nav"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
        <a href="../books/" class="nav-link"><i class="fa-solid fa-book me-2"></i> Book Inventory</a>
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
    <h3 class="fw-bold mb-4"><i class="fa-solid fa-chart-simple"></i> Library Analytics</h3>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <p class="text-muted small fw-bold mb-1"><i class="fa-solid fa-book"></i> TOTAL PHYSICAL BOOKS</p>
                <h2 class="fw-bold text-slate"><?php echo number_format($total_books); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <p class="text-muted small fw-bold mb-1"><i class="fa-solid fa-user-graduate"></i> REGISTERED STUDENTS</p>
                <h2 class="fw-bold text-success"><?php echo get_count($conn, 'users', "WHERE role='student'"); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <p class="text-muted small fw-bold mb-1"><i class="fa-solid fa-hand-holding-hand"></i> BOOKS CURRENTLY ISSUED</p>
                <h2 class="fw-bold text-primary"><?php echo get_count($conn, 'issued_books', "WHERE status='issued'"); ?></h2>
            </div>
        </div>
    </div>

    <div class="card stat-card p-4 mb-5">
        <h5 class="fw-bold mb-4">Book Activity (Last 30 Days)</h5>
        <canvas id="activityChart" height="100"></canvas>
    </div>

    <div class="card stat-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left"></i> System Logs</h5>
            <span class="badge bg-dark text-white px-3 py-2"><i class="fa-regular fa-clock"></i> Time Zone : GMT+6:00 Dhaka </span>
        </div>
        <div class="log-container" style="max-height: 400px; overflow-y: auto; background: #fdfdfd; border-radius: 10px; border: 1px solid #edf2f7;">
            <table class="table table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr class="small text-uppercase">
                        <th class="ps-3" style="width: 200px;">Time</th>
                        <th style="width: 180px;">User Full Name</th>
                        <th style="width: 150px;">IP Address</th>
                        <th>Event</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php
                    $logPath = __DIR__ . '/../../data/systemLog.txt';
                    if (file_exists($logPath)) {
                        $logs = array_reverse(file($logPath));
                        foreach (array_slice($logs, 0, 100) as $line) {
                            if (preg_match('/\[(.*?)\] \[User: (.*?)\] \[IP: (.*?)\] (.*)/', $line, $matches)) {
                                $formattedTime = date('d M Y, h:i A', strtotime($matches[1]));
                                echo "<tr>
                                        <td class='ps-3 text-muted'>{$formattedTime}</td>
                                        <td class='fw-bold text-dark'>{$matches[2]}</td>
                                        <td class='text-muted'>{$matches[3]}</td>
                                        <td class='fw-medium'>{$matches[4]}</td>
                                      </tr>";
                            }
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'>No system logs available yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Issues',
            data: <?php echo json_encode($data); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3
        }]
    },
    options: { 
        plugins: { legend: { display: false } }, 
        scales: { 
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        } 
    }
});
</script>
</body>
</html>