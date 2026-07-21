<?php
// Start session for CSRF protection
session_start();

// Load configuration
require_once 'protected_data/config.php';

// Initialize variables
$search_results = [];
$has_searched = false;
$data_file = DONORS_FILE;

// Check if search parameters exist
$search_location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$search_group = isset($_GET['group']) ? sanitizeInput($_GET['group']) : '';

// DATA LOADING LOGIC: Only load if search parameters exist (and are not empty) or the user clicked search
if (isset($_GET['location']) || isset($_GET['group'])) {
    $has_searched = true;

    // Load Donor Data only on search
    $donors = [];
    if (file_exists($data_file)) {
        try {
            $json_data = file_get_contents($data_file);
            if ($json_data !== false) {
                $donors = json_decode($json_data, true) ?: [];
            }
        } catch (Exception $e) {
            error_log("Failed to load donors data: " . $e->getMessage());
        }
    }

    // Filter Logic
    $search_results = array_filter($donors, function($donor) use ($search_location, $search_group) {
        $location_match = empty($search_location) || 
                         stripos($donor['location'], $search_location) !== false;
        $group_match = empty($search_group) || $donor['group'] === $search_group;
        return $location_match && $group_match;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blood Savior - Find blood donors instantly. Save lives by connecting donors with those in need.">
    <title><?php echo SITE_NAME; ?> - Find Donors</title>
    
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.ico">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="assets/fontawesome7/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@100..900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .font-poppins {
            font-family: 'Poppins', sans-serif;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -15px rgba(220, 38, 38, 0.25);
        }

        /* favicon */
        .favicon {
            display: inline-block;
            width: 50px;
            height: 50px;
            position: relative;
        }
        
        
        /* Donor Card Styles */
        .donor-card {
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            aspect-ratio: 1/1; /* Force Square Shape */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .blood-group-badge {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        .donor-contact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-top: auto; /* Push to bottom */
        }
        
        .donor-contact:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        /* Universal Donor Style */
        .universal-donor {
            background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%) !important;
        }
        
        /* Search Results Grid */
        .donor-grid {
            display: grid;
            gap: 1.5rem;
            /* Adjusted for squares */
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }

        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #dc2626;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="text-gray-900 min-h-screen flex flex-col">

    <div id="loading-overlay">
        <div class="spinner"></div>
        <p class="text-red-700 font-bold text-lg animate-pulse">Searching for Heroes...</p>
    </div>

    <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-auto mx-auto px-1 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <a href="./" class="flex items-center space-x-0 group">
                    <img class="favicon" src="/assets/images/favicon.ico" alt="favicon">
                    <div>
                        <h1 class="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-800 bg-clip-text text-transparent font-poppins">
                            <?php echo SITE_NAME; ?>
                        </h1>
                        <p class="text-xs text-gray-500">Life is in your blood</p>
                    </div>
                </a>
                
                <nav class="flex items-center space-x-2">
    <a href="user/registration/" class="flex items-center whitespace-nowrap bg-red-600 text-white px-3 py-2.5 rounded-lg font-semibold shadow-lg hover:bg-red-700 transition">
        <i class="fas fa-user-plus mr-2"></i> Register
    </a>
    
    <a href="user/login/" class="flex items-center whitespace-nowrap bg-red-600 text-white px-3 py-2.5 rounded-lg font-semibold shadow-lg hover:bg-red-700 transition">
        <i class="fas fa-lock mr-2"></i> Login
    </a>
</nav>
                </nav>
            </div>
        </div>
    </header>

    <section class="hero-gradient text-white py-10 sm:py-20 relative overflow-hidden z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight mb-4">
                Find Blood Donors. Save Lives.
            </h1>
            <p class="text-xl sm:text-2xl font-light max-w-3xl mx-auto mb-10 opacity-90">
                Connect instantly with verified blood donors in your area
            </p>
        </div>
    </section>

    <section id="searchSection" class="w-full px-4 sm:px-6 lg:px-8 -mt-24 mb-12 relative z-30">
        <div class="bg-white p-6 sm:p-10 rounded-2xl shadow-2xl border border-gray-100 transition duration-500 w-full">
            <h2 class="text-3xl font-bold text-red-700 mb-6 border-b pb-2">Find Blood Donors</h2>
            
            <form id="searchForm" action="index.php" method="GET" class="flex flex-col md:flex-row gap-4 mb-6">
                <select name="location" 
                        class="flex-grow px-4 py-3 border-2 border-gray-200 rounded-lg bg-white focus:ring-red-500 focus:border-red-500 transition duration-150">
                    <option value="">Select Location</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location); ?>" 
                            <?php echo $search_location === $location ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="group" 
                        class="w-full md:w-1/3 px-4 py-3 border-2 border-gray-200 rounded-lg bg-white focus:ring-red-500 focus:border-red-500">
                    <option value="">Select Blood Group</option>
                    <?php foreach ($blood_groups as $group): ?>
                        <option value="<?php echo $group; ?>" 
                            <?php echo $search_group === $group ? 'selected' : ''; ?>>
                            <?php echo $group; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" 
                        class="w-full md:w-auto px-6 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition duration-150 flex items-center justify-center">
                    <i class="fas fa-search mr-2"></i> Search Donors
                </button>
            </form>

            <div id="searchResults" class="min-h-[100px] border-t pt-6">
                
                <?php if (!$has_searched): ?>
                    <div class="text-center py-8">
                        <div class="text-red-200 text-6xl mb-4">
                            <i class="fas fa-search-location"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-600">Select a location and blood group to start searching.</h4>
                    </div>

                <?php elseif ($has_searched && empty($search_results)): ?>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-700">Results</h3>
                        <a href="index.php" class="text-red-600 hover:text-red-800 font-medium text-sm">
                            <i class="fas fa-times mr-1"></i> Clear Search
                        </a>
                    </div>
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-5xl mb-6">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-700 mb-4">No Donors Found</h4>
                        <p class="text-gray-600 max-w-md mx-auto mb-8">
                            No donors match your criteria. Try a different location or blood group.
                        </p>
                    </div>

                <?php else: ?>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-700">
                            Available Donors <span class="text-red-600">(<?php echo count($search_results); ?>)</span>
                        </h3>
                        <a href="index.php" class="text-red-600 hover:text-red-800 font-medium text-sm">
                            <i class="fas fa-times mr-1"></i> Clear Search
                        </a>
                    </div>

                    <div class="donor-grid">
                        <?php foreach ($search_results as $donor): ?>
                            <?php 
                                $is_universal = str_contains($donor['group'], '-');
                                $badge_class = $is_universal ? 'universal-donor' : 'bg-red-600';
                            ?>
                            
                            <div class="donor-card bg-white border-2 border-gray-100 rounded-2xl p-6 card-hover hover:border-red-200 shadow-sm">
                                
                                <div>
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="blood-group-badge <?php echo $badge_class; ?> text-white">
                                            <?php echo htmlspecialchars($donor['group']); ?>
                                        </div>
                                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded flex items-center">
                                            <i class="fas fa-check-circle mr-1"></i> Verified
                                        </span>
                                    </div>
                                    
                                    <h4 class="text-lg font-bold text-gray-900 leading-tight mb-1 truncate">
                                        <i class="fa-solid fa-user-tie"></i>
                                        <?php echo htmlspecialchars($donor['name']); ?>
                                    </h4>
                                    
                                    <div class="text-gray-600 text-sm flex items-center mb-2">
                                        <i class="fas fa-map-marker-alt text-red-500 mr-2"></i> 
                                        <?php echo htmlspecialchars($donor['location']); ?>
                                    </div>

                                    <div class="text-gray-400 text-xs flex items-center">
                                        <i class="far fa-calendar-alt mr-2"></i> 
                                        Last Donation: <?php echo date('d/m/Y', strtotime($donor['last_donation'])); ?>
                                    </div>
                                </div>
                                
                                <div class="donor-contact p-3 mt-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex flex-col">
                                            <div class="text-[10px] text-white/90 uppercase font-semibold">Contact</div>
                                            <div class="text-[20px] font-roboto font-bold text-white truncate">
                                                <?php echo htmlspecialchars($donor['contact']); ?>
                                            </div>
                                        </div>
                                        <a href="tel:<?php echo htmlspecialchars($donor['contact']); ?>" 
                                           class="bg-white text-green-700 w-8 h-8 rounded-full flex items-center justify-center font-bold hover:bg-green-50 transition duration-200 shadow-sm">
                                            <i class="fas fa-phone-alt"></i>
                                        </a>
                                    </div>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-12 pt-6 border-t text-center">
                 <p class="text-lg text-gray-700 mb-4">Want to help save a life?</p>
                 <a href="user/registration/" class="bg-red-700 text-white hover:bg-red-800 border-2 border-white px-8 py-3 rounded-xl font-bold text-lg shadow-md transition duration-300 inline-flex items-center">
                    <i class="fas fa-heart mr-2"></i> Register as a Donor
                 </a>
            </div>
        </div>
    </section>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <a href="/help" style="text-decoration: none;"><div style="display: inline-block; padding: 10px 20px; color: white; border-radius: 5px; cursor: pointer; text-align: center; font-family: Arial, sans-serif; font-weight: bold; transition: background-color 0.3s;" onmouseover="this.style.backgroundColor='#cc0000'" onmouseout="this.style.backgroundColor='transparent'">Contract For Help</div>
                <p class="text-sm text-gray-400">&copy; 2025 Blood Savior. Free Blood Donation Finder.</p>
                    <p class="text-xs text-gray-500 mt-2">Saving lives one donation at a time</p>  </a>
            </div>
            <p class="text-xs text-gray-500 text-right"><br><i class="fas fa-code text-white mr-1"></i> Designed & Developed by <span class="font-medium text-white">SHAFIN</span></p>
			</div>
            
        </div>
    </footer>
    
    <script>
        // Trigger Loading Animation on Search
        document.getElementById('searchForm').addEventListener('submit', function() {
            document.getElementById('loading-overlay').style.display = 'flex';
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>