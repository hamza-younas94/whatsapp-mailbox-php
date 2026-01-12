<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'WhatsApp CRM'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .top-nav {
            background: #128C7E;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .top-nav .nav-links {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .top-nav .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            transition: background 0.2s;
        }
        .top-nav .nav-link:hover,
        .top-nav .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        .top-nav .dropdown {
            position: relative;
        }
        .top-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 4px;
            margin-top: 0.5rem;
            min-width: 200px;
            z-index: 1000;
        }
        .top-nav .dropdown:hover .dropdown-menu {
            display: block;
        }
        .top-nav .dropdown-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }
        .top-nav .dropdown-menu a:hover {
            background: #f5f5f5;
        }
        .user-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-links">
            <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i>
                Mailbox
            </a>
            <a href="crm_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'crm_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard"></i>
                CRM
            </a>
            <a href="broadcasts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'broadcasts.php' ? 'active' : ''; ?>">
                <i class="fas fa-broadcast-tower"></i>
                Broadcasts
            </a>
            <a href="quick-replies.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quick-replies.php' ? 'active' : ''; ?>">
                <i class="fas fa-bolt"></i>
                Quick Replies
            </a>
            <a href="analytics.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Analytics
            </a>
            
            <div class="dropdown">
                <a href="#" class="nav-link">
                    <i class="fas fa-ellipsis-h"></i>
                    More ▾
                </a>
                <div class="dropdown-menu">
                    <a href="tags.php"><i class="fas fa-tags"></i> Tags</a>
                    <a href="segments.php"><i class="fas fa-users"></i> Segments</a>
                    <a href="scheduled-messages.php"><i class="fas fa-clock"></i> Scheduled</a>
                    <a href="notes.php"><i class="fas fa-sticky-note"></i> Notes</a>
                    <a href="deals.php"><i class="fas fa-handshake"></i> Deals</a>
                </div>
            </div>
            
            <div class="user-info">
                <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user->username ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 11">
        <div id="toastContainer"></div>
    </div>
