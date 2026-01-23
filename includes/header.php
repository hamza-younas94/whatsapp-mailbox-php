<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'MessageHub'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern.css">
    <link rel="stylesheet" href="assets/css/validation.css">
    
    <style>
        /* Modern Navigation Styles */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
        
        .top-nav {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .top-nav .nav-links {
            display: flex;
            gap: 0;
            align-items: center;
            flex: 1;
        }
        .top-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            border-bottom: 3px solid transparent;
        }
        .top-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-bottom-color: #25D366;
        }
        .top-nav .nav-link i {
            font-size: 1.1rem;
        }
        
        /* Dropdown Styles */
        .top-nav .dropdown {
            position: relative;
        }
        .top-nav .dropdown > .nav-link {
            cursor: pointer;
        }
        .top-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
            min-width: 280px;
            max-width: 320px;
            z-index: 1001;
            padding: 0.5rem 0;
            margin-top: 0;
            animation: slideDown 0.2s ease;
            max-height: 80vh;
            overflow-y: auto;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .top-nav .dropdown:hover .dropdown-menu {
            display: block;
        }
        .top-nav .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            justify-content: space-between;
        }
        .top-nav .dropdown-menu a:hover {
            background: #f5f5f5;
            color: #128C7E;
            padding-left: 1.5rem;
        }
        .top-nav .dropdown-menu a.active {
            background: #e8f5e9;
            color: #128C7E;
            font-weight: 600;
        }
        
        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 1.5rem;
            color: rgba(255,255,255,0.9);
        }
        .user-info i {
            font-size: 1.2rem;
        }
        .logout-btn {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* Modal Improvements */
        .modal {
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-dialog {
            pointer-events: auto !important;
        }
        .modal-content {
            pointer-events: auto !important;
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .top-nav .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            .top-nav .nav-link span:not(.d-none) {
                display: none;
            }
            .user-info span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php
    // Determine current page for navbar highlighting
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    
    // Map PHP filenames to navbar page identifiers
    $pageMap = [
        'index' => 'mailbox',
        'crm_dashboard' => 'crm',
        'broadcasts' => 'broadcasts',
        'quick-replies' => 'quick-replies',
        'analytics' => 'analytics',
        'tags' => 'tags',
        'auto-tag-rules' => 'auto-tag-rules',
        'search' => 'search',
        'segments' => 'segments',
        'scheduled-messages' => 'scheduled',
        'notes' => 'notes',
        'deals' => 'deals',
        'ip-commands' => 'ip-commands',
        'workflows' => 'workflows',
        'drip-campaigns' => 'drip-campaigns',
        'message-templates' => 'message-templates',
        'webhook-manager' => 'webhook-manager',
        'users' => 'users',
        'subscriptions' => 'subscriptions',
        'logs' => 'logs'
    ];
    
    $currentNavPage = $pageMap[$currentPage] ?? $currentPage;
    
    // Render the Twig navbar template (single source of truth)
    global $twig;
    echo $twig->render('navbar.html.twig', [
        'currentPage' => $currentNavPage,
        'user' => $user,
        'username' => $user->username ?? 'Admin'
    ]);
    ?>
    
    <!-- Toast Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1060; pointer-events: none;">
        <div id="toastContainer" style="pointer-events: auto;"></div>
    </div>
    
    <script>
    // Handle dropdown toggle on click
    document.addEventListener('DOMContentLoaded', function() {
        const dropdown = document.querySelector('.top-nav .dropdown');
        const dropdownToggle = dropdown?.querySelector('.nav-link');
        
        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }
    });
    </script>
