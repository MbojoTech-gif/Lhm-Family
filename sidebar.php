<?php
// sidebar.php - Collapsing Sidebar Navigation
require_once 'db.php';
requireLogin();

// Get user information for display
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT full_name, profile_pic, role FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);
?>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <span class="hamburger-icon">☰</span>
    <span class="close-icon">✕</span>
</button>

<!-- Sidebar Container -->
<div class="sidebar-container" id="sidebar">
    <!-- Sidebar Header with Logo and Toggle -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="assets/images/logo1.png" alt="LHM Logo">
            <span class="logo-text">LHM FAMILY</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <span class="toggle-icon">‹</span>
        </button>
    </div>
    
    <!-- User Profile Section -->
    <div class="user-profile">
        <div class="profile-pic">
            <?php if ($user_data['profile_pic'] && file_exists('assets/uploads/' . $user_data['profile_pic'])): ?>
                <img src="assets/uploads/<?php echo $user_data['profile_pic']; ?>" alt="Profile">
            <?php else: ?>
                <div class="profile-initials">
                    <?php 
                    $initials = '';
                    $names = explode(' ', $user_data['full_name']);
                    foreach ($names as $name) {
                        $initials .= strtoupper(substr($name, 0, 1));
                    }
                    echo substr($initials, 0, 2);
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h4 class="user-name"><?php echo htmlspecialchars($user_data['full_name']); ?></h4>
            <span class="user-role"><?php echo ucfirst($user_data['role']); ?></span>
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <!-- Dashboard -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php" class="nav-link">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <!-- Announcements -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
                <a href="announcements.php" class="nav-link">
                    <span class="nav-icon">📢</span>
                    <span class="nav-text">Announcements</span>
                </a>
            </li>
            
            <!-- Itinerary -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'itenary.php' ? 'active' : ''; ?>">
                <a href="itenary.php" class="nav-link">
                    <span class="nav-icon">📅</span>
                    <span class="nav-text">Itinerary</span>
                </a>
            </li>
            
            <!-- Duty Roster -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'duty.php' ? 'active' : ''; ?>">
                <a href="duty.php" class="nav-link">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Duty Roster</span>
                </a>
            </li>
            
            <!-- Accounts -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'accounts.php' ? 'active' : ''; ?>">
                <a href="accounts.php" class="nav-link">
                    <span class="nav-icon">💰</span>
                    <span class="nav-text">Accounts</span>
                </a>
            </li>
            
            <!-- Songs -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'songs.php' ? 'active' : ''; ?>">
                <a href="songs.php" class="nav-link">
                    <span class="nav-icon">🎵</span>
                    <span class="nav-text">Songs Library</span>
                </a>
            </li>
            
            <!-- Suggestions -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'suggestions.php' ? 'active' : ''; ?>">
                <a href="suggestions.php" class="nav-link">
                    <span class="nav-icon">💡</span>
                    <span class="nav-text">Suggestions</span>
                </a>
            </li>
            
            <!-- Reports -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php" class="nav-link">
                    <span class="nav-icon">📈</span>
                    <span class="nav-text">Reports</span>
                </a>
            </li>
            
            <!-- Attendance -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                <a href="attendance.php" class="nav-link">
                    <span class="nav-icon">✅</span>
                    <span class="nav-text">Attendance</span>
                </a>
            </li>
            
            <!-- Members -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>">
                <a href="members.php" class="nav-link">
                    <span class="nav-icon">👨‍👩‍👧‍👦</span>
                    <span class="nav-text">Members</span>
                </a>
            </li>
            
            <!-- Profile -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php" class="nav-link">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">My Profile</span>
                </a>
            </li>
        </ul>
    </nav>
    
        <!-- Logout Button -->
        <div class="logout-section">
            <a href="logout.php" class="logout-link">
                <span class="logout-icon">🚪</span>
                <span class="logout-text">Logout</span>
            </a>
        </div>
        
        <!-- Copyright -->
        <div class="sidebar-copyright">
            <p>&copy; <?php echo date('Y'); ?> Lighthouse Ministers</p>
            <p class="version">v1.0</p>
        </div>
    </div>
</div>

<!-- CSS for Sidebar -->
<style>
    /* Sidebar Variables */
    :root {
        --sidebar-width: 250px;
        --sidebar-collapsed: 70px;
        --transition-speed: 0.3s;
        --primary-color: #000000;
        --secondary-color: #1a1a1a;
        --text-color: #333333;
        --light-text: #666666;
        --border-color: #e0e0e0;
        --hover-bg: #f5f5f5;
        --active-bg: #000000;
        --active-color: #ffffff;
    }
    
    /* Mobile Menu Toggle Button */
    .mobile-menu-toggle {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1100;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        width: 40px;
        height: 40px;
        font-size: 20px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .mobile-menu-toggle .close-icon {
        display: none;
    }
    
    .sidebar-container.mobile-open ~ .mobile-menu-toggle .hamburger-icon {
        display: none;
    }
    
    .sidebar-container.mobile-open ~ .mobile-menu-toggle .close-icon {
        display: block;
    }
    
    /* Sidebar Container */
    .sidebar-container {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
        border-right: 1px solid var(--border-color);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        transition: all var(--transition-speed) ease;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    /* Collapsed State */
    .sidebar-container.collapsed {
        width: var(--sidebar-collapsed);
    }
    
    /* Sidebar Header */
    .sidebar-header {
        padding: 20px 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
    }
    
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 180px;
    }
    
    .sidebar-logo img {
        width: 35px;
        height: 35px;
        object-fit: contain;
        transition: all var(--transition-speed) ease;
    }
    
    .logo-text {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary-color);
        white-space: nowrap;
        opacity: 1;
        transition: opacity var(--transition-speed) ease;
    }
    
    .sidebar-container.collapsed .logo-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    
    /* Toggle Button */
    .sidebar-toggle {
        background: none;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        width: 30px;
        height: 30px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-speed) ease;
    }
    
    .sidebar-toggle:hover {
        background: var(--hover-bg);
        border-color: var(--primary-color);
    }
    
    .toggle-icon {
        font-size: 18px;
        color: var(--text-color);
        transition: transform var(--transition-speed) ease;
    }
    
    .sidebar-container.collapsed .toggle-icon {
        transform: rotate(180deg);
    }
    
    /* User Profile */
    .user-profile {
        padding: 20px 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 12px;
        background: white;
    }
    
    .profile-pic {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    }
    
    .profile-pic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-initials {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .profile-info {
        min-width: 150px;
        transition: all var(--transition-speed) ease;
    }
    
    .user-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .user-role {
        font-size: 0.8rem;
        color: var(--light-text);
        display: block;
        margin-top: 2px;
    }
    
    .sidebar-container.collapsed .profile-info {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    
    /* Navigation */
    .sidebar-nav {
        flex: 1;
        padding: 20px 0;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-item {
        margin-bottom: 2px;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        text-decoration: none;
        color: var(--text-color);
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
        white-space: nowrap;
    }
    
    .nav-link:hover {
        background: var(--hover-bg);
        color: var(--primary-color);
        border-left-color: var(--primary-color);
    }
    
    .nav-item.active .nav-link {
        background: var(--active-bg);
        color: var(--active-color);
        border-left-color: var(--active-color);
    }
    
    .nav-icon {
        font-size: 1.2rem;
        margin-right: 12px;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }
    
    .nav-text {
        font-size: 0.9rem;
        font-weight: 500;
        transition: opacity var(--transition-speed) ease;
    }
    
    .sidebar-container.collapsed .nav-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    
    /* Sidebar Footer */
    .sidebar-footer {
        border-top: 1px solid var(--border-color);
        background: white;
    }
    
    /* Admin Section */
    .admin-section {
        padding: 10px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .admin-section:last-child {
        border-bottom: none;
    }
    
    .admin-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .admin-menu li {
        margin-bottom: 5px;
    }
    
    .admin-link {
        display: flex;
        align-items: center;
        padding: 8px 0;
        text-decoration: none;
        color: var(--light-text);
        font-size: 0.85rem;
        transition: color 0.2s ease;
    }
    
    .admin-link:hover {
        color: var(--primary-color);
    }
    
    /* Logout Section */
    .logout-section {
        padding: 15px 20px;
    }
    
    .logout-link {
        display: flex;
        align-items: center;
        padding: 10px;
        text-decoration: none;
        color: #d32f2f;
        background: rgba(211, 47, 47, 0.05);
        border-radius: 6px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .logout-link:hover {
        background: rgba(211, 47, 47, 0.1);
        color: #b71c1c;
    }
    
    .logout-icon {
        font-size: 1.2rem;
        margin-right: 10px;
        flex-shrink: 0;
    }
    
    .logout-text {
        font-size: 0.9rem;
        font-weight: 500;
        transition: opacity var(--transition-speed) ease;
    }
    
    .sidebar-container.collapsed .logout-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    
    /* Copyright */
    .sidebar-copyright {
        padding: 10px 20px;
        text-align: center;
        border-top: 1px solid var(--border-color);
    }
    
    .sidebar-copyright p {
        margin: 3px 0;
        font-size: 0.75rem;
        color: var(--light-text);
        white-space: nowrap;
        transition: opacity var(--transition-speed) ease;
    }
    
    .sidebar-container.collapsed .sidebar-copyright p {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    
    .version {
        font-size: 0.7rem !important;
        color: #999 !important;
    }
    
    /* Scrollbar Styling */
    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar-nav::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .sidebar-nav::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 2px;
    }
    
    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: #a1a1a1;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
        }
        
        .sidebar-container {
            transform: translateX(-100%);
            width: var(--sidebar-width);
        }
        
        .sidebar-container.mobile-open {
            transform: translateX(0);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.15);
        }
        
        .sidebar-container.collapsed {
            width: var(--sidebar-collapsed);
        }
        
        .main-content {
            margin-left: 0 !important;
            padding-left: 15px !important;
        }
        
        /* Adjust page header to avoid overlap with mobile toggle */
        .page-header {
            padding-top: 60px;
        }
    }
    
    /* Desktop hover effect for collapsed sidebar */
    @media (min-width: 769px) {
        .sidebar-container.collapsed:hover {
            width: var(--sidebar-width);
        }
        
        .sidebar-container.collapsed:hover .logo-text,
        .sidebar-container.collapsed:hover .profile-info,
        .sidebar-container.collapsed:hover .nav-text,
        .sidebar-container.collapsed:hover .section-title,
        .sidebar-container.collapsed:hover .logout-text,
        .sidebar-container.collapsed:hover .sidebar-copyright p {
            opacity: 1;
            width: auto;
            overflow: visible;
        }
    }
</style>

<!-- JavaScript for Sidebar -->
<script>
    // Sidebar Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        
        // Check for saved state in localStorage
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        
        // Desktop sidebar toggle
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Mobile menu toggle
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileToggle.contains(event.target) && 
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        // Close mobile sidebar when clicking on a link
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('mobile-open');
                }
            });
        });
        
        // Adjust page header padding for mobile
        function adjustPageHeader() {
            if (window.innerWidth <= 768) {
                const pageHeader = document.querySelector('.page-header');
                if (pageHeader) {
                    pageHeader.style.paddingTop = '60px';
                }
            } else {
                const pageHeader = document.querySelector('.page-header');
                if (pageHeader) {
                    pageHeader.style.paddingTop = '';
                }
            }
        }
        
        // Initial adjustment
        adjustPageHeader();
        
        // Adjust on resize
        window.addEventListener('resize', adjustPageHeader);
    });
</script>