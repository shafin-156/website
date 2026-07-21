<?php
// Start session
session_start();

// Load configuration if available, otherwise define defaults
if (file_exists('protected_data/config.php')) {
    require_once 'protected_data/config.php';
} else {
    define('SITE_NAME', 'Blood Savior');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Help Center</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.ico">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="../assets/fontawesome7/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@100..900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .font-poppins { font-family: 'Poppins', sans-serif; }
        
        /* Custom Color Class for dynamic usage */
        .text-custom-red { color: #cc0000; }
        .bg-custom-red { background-color: #cc0000; }
        .border-custom-red { border-color: #cc0000; }
        .hover-bg-custom-red:hover { background-color: #990000; } /* Darker shade for hover */
        
        .favicon {
            display: inline-block;
            width: 50px;
            height: 50px;
        }

        /* Card Animation */
        .contact-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -15px rgba(204, 0, 0, 0.15); /* Red shadow using #cc0000 rgba */
            border-color: #ffcccc; 
        }
        
        .icon-circle {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background-color: #fff5f5; /* Very light red bg */
            color: #cc0000; /* Custom Red Text */
        }

        .contact-card:hover .icon-circle {
            background-color: #cc0000;
            color: white;
            transform: scale(1.1);
        }
    </style>
</head>
<body class="text-gray-900 min-h-screen flex flex-col">

    
     <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-auto mx-auto px-1 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <a href="../" class="flex items-center space-x-0 group">
                    <img class="favicon" src="/assets/images/favicon.ico" alt="favicon">
                    <div>
                        <h1 class="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-800 bg-clip-text text-transparent font-poppins">
                            <?php echo SITE_NAME; ?>
                        </h1>
                        <p class="text-xs text-gray-500">Life is in your blood</p>
                    </div>
                </a>
                
                <nav class="flex items-center space-x-2">
    <a href="../user/registration/" class="flex items-center whitespace-nowrap bg-red-600 text-white px-3 py-2.5 rounded-lg font-semibold shadow-lg hover:bg-red-700 transition">
        <i class="fas fa-user-plus mr-2"></i> Register
    </a>
    
    <a href="../user/login/" class="flex items-center whitespace-nowrap bg-red-600 text-white px-3 py-2.5 rounded-lg font-semibold shadow-lg hover:bg-red-700 transition">
        <i class="fas fa-lock mr-2"></i> Login
    </a>
</nav>
                </nav>
            </div>
        </div>
    </header>
    
    <section class="bg-[#cc0000] text-white py-16 relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h1 class="text-4xl md:text-5xl font-bold font-poppins mb-4">We're Here to Help</h1>
            <p class="text-red-100 text-lg max-w-2xl mx-auto">
                Need assistance with finding blood or registering as a donor? <br>Reach out to our support team directly.
            </p>
        </div>
    </section>

    <section class="flex-grow -mt-10 px-4 sm:px-6 lg:px-8 pb-16 relative z-20">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
              <div class="contact-card bg-white p-8 rounded-2xl shadow-xl text-center flex flex-col items-center">
                    <div class="icon-circle">
                        <i class="fab fa-facebook-f"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Facebook Page</h3>
                    <p class="text-gray-500 text-sm mb-4">Blood Savior</p>
                    <a href="https://facebook.com/bloodsavior" target="_blank" class="px-6 py-2 bg-[#cc0000] text-white rounded-full font-medium hover:bg-[#aa0000] transition shadow-lg shadow-red-200">
                        Visit Facebook
                    </a>
                </div>

                <div class="contact-card bg-white p-8 rounded-2xl shadow-xl text-center flex flex-col items-center">
                    <div class="icon-circle">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Email Support</h3>
                    <p class="text-gray-500 text-sm mb-4">Drop us a line anytime</p>
                    <a href="mailto:help.bloodsavior@gmail.com" class="text-lg font-medium text-[#cc0000] hover:text-[#990000] transition">
                        help.bloodsavior@gmail.com
                    </a>
                </div>

                
                
                  <div class="contact-card bg-white p-8 rounded-2xl shadow-xl text-center flex flex-col items-center">
                    <div class="icon-circle">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Call Us</h3>
                    <p class="text-gray-500 text-sm mb-4">Available 10 AM - 5 PM for emergencies</p>
                    <a href="tel:0170000000" class="text-2xl font-bold text-[#cc0000] hover:text-[#990000] transition">
                        017XXXXXXXX
                    </a>
                </div>

            </div>
        </div>
    </section>

</body>
</html>