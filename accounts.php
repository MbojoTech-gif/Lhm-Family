<?php
// accounts.php - Social Media Accounts & Bank Accounts Management
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('accounts.php')) {
    header('Location: access_denied.php');
    exit();
}

// Get user role for specific permission checks
$user_role = $_SESSION['role'];

// Check edit permissions for different sections
// Social Media: Only Publicity Leader, Chairman, and Admin can edit
$can_edit_social = in_array($user_role, ['publicity_leader', 'chairman', 'admin']);

// Bank Accounts: Only Treasurer, Chairman, and Admin can edit  
$can_edit_bank = in_array($user_role, ['treasurer', 'chairman', 'admin']);

$user_id = $_SESSION['user_id'];
$current_month_year = date('Y-m');
$previous_month_year = date('Y-m', strtotime('-1 month'));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_social_account'])) {
        // Add new social account
        if (!$can_edit_social) {
            $error = "You don't have permission to add social media accounts.";
        } else {
            $platform = sanitize($_POST['platform']);
            $account_name = sanitize($_POST['account_name']);
            $account_url = sanitize($_POST['account_url']);
            $followers = intval($_POST['followers_count']);
            $revenue = floatval($_POST['monthly_revenue']);
            $currency = sanitize($_POST['currency']);
            $status = sanitize($_POST['status']);
            $notes = sanitize($_POST['notes']);
            
            $sql = "INSERT INTO social_accounts (platform, account_name, account_url, followers_count, monthly_revenue, currency, status, notes, created_by, last_updated) 
                    VALUES ('$platform', '$account_name', '$account_url', '$followers', '$revenue', '$currency', '$status', '$notes', '$user_id', CURDATE())";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Social account added successfully!";
                $account_id = mysqli_insert_id($conn);
                
                // Add to revenue log
                $log_sql = "INSERT INTO revenue_log (social_account_id, user_id, action, new_followers, new_revenue, month_year, details) 
                           VALUES ('$account_id', '$user_id', 'create', '$followers', '$revenue', '$current_month_year', 'New account created')";
                mysqli_query($conn, $log_sql);
            } else {
                $error = "Error adding social account: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['edit_social_account'])) {
        // Edit social account
        if (!$can_edit_social) {
            $error = "You don't have permission to edit social media accounts.";
        } else {
            $account_id = intval($_POST['account_id']);
            $platform = sanitize($_POST['platform']);
            $account_name = sanitize($_POST['account_name']);
            $account_url = sanitize($_POST['account_url']);
            $followers = intval($_POST['followers_count']);
            $revenue = floatval($_POST['monthly_revenue']);
            $currency = sanitize($_POST['currency']);
            $status = sanitize($_POST['status']);
            $notes = sanitize($_POST['notes']);
            
            // Get old data for log
            $old_sql = "SELECT followers_count, monthly_revenue FROM social_accounts WHERE id = '$account_id'";
            $old_result = mysqli_query($conn, $old_sql);
            $old_data = mysqli_fetch_assoc($old_result);
            
            $sql = "UPDATE social_accounts SET 
                    platform = '$platform', 
                    account_name = '$account_name', 
                    account_url = '$account_url', 
                    followers_count = '$followers', 
                    monthly_revenue = '$revenue', 
                    currency = '$currency', 
                    status = '$status', 
                    notes = '$notes', 
                    last_updated = CURDATE() 
                    WHERE id = '$account_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Social account updated successfully!";
                
                // Add to revenue log if followers or revenue changed
                if ($old_data['followers_count'] != $followers || $old_data['monthly_revenue'] != $revenue) {
                    $details = "Updated: ";
                    $changes = [];
                    if ($old_data['followers_count'] != $followers) {
                        $changes[] = "Followers: " . $old_data['followers_count'] . " → " . $followers;
                    }
                    if ($old_data['monthly_revenue'] != $revenue) {
                        $changes[] = "Revenue: KES " . number_format($old_data['monthly_revenue'], 2) . " → KES " . number_format($revenue, 2);
                    }
                    
                    $log_details = implode(", ", $changes);
                    $log_sql = "INSERT INTO revenue_log (social_account_id, user_id, action, old_followers, new_followers, old_revenue, new_revenue, month_year, details) 
                               VALUES ('$account_id', '$user_id', 'update', '{$old_data['followers_count']}', '$followers', '{$old_data['monthly_revenue']}', '$revenue', '$current_month_year', '$log_details')";
                    mysqli_query($conn, $log_sql);
                }
            } else {
                $error = "Error updating social account: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['delete_social_account'])) {
        // Delete social account - Only admin can delete
        if (!isAdmin()) {
            $error = "Only administrators can delete social accounts.";
        } else {
            $account_id = intval($_POST['account_id']);
            
            $sql = "DELETE FROM social_accounts WHERE id = '$account_id'";
            if (mysqli_query($conn, $sql)) {
                $success = "Social account deleted successfully!";
            } else {
                $error = "Error deleting social account: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['update_bank_balance'])) {
        // Update bank account balance
        if (!$can_edit_bank) {
            $error = "You don't have permission to update bank account balances.";
        } else {
            $bank_id = intval($_POST['bank_id']);
            $new_balance = floatval($_POST['new_balance']);
            $transaction_type = sanitize($_POST['transaction_type']);
            $details = sanitize($_POST['details']);
            
            // Get old balance for log
            $old_sql = "SELECT current_balance FROM bank_accounts WHERE id = '$bank_id'";
            $old_result = mysqli_query($conn, $old_sql);
            $old_data = mysqli_fetch_assoc($old_result);
            
            $sql = "UPDATE bank_accounts SET 
                    current_balance = '$new_balance', 
                    last_updated = CURDATE() 
                    WHERE id = '$bank_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Bank balance updated successfully!";
                
                // Add to transaction log
                $log_sql = "INSERT INTO bank_transaction_log (bank_account_id, user_id, action, old_balance, new_balance, transaction_type, details) 
                           VALUES ('$bank_id', '$user_id', 'update', '{$old_data['current_balance']}', '$new_balance', '$transaction_type', '$details')";
                mysqli_query($conn, $log_sql);
            } else {
                $error = "Error updating bank balance: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['add_bank_account'])) {
        // Add new bank account
        if (!$can_edit_bank) {
            $error = "You don't have permission to add bank accounts.";
        } else {
            $bank_name = sanitize($_POST['bank_name']);
            $account_name = sanitize($_POST['account_name']);
            $account_number = sanitize($_POST['account_number']);
            $account_type = sanitize($_POST['account_type']);
            $current_balance = floatval($_POST['current_balance']);
            $currency = sanitize($_POST['currency']);
            $status = sanitize($_POST['status']);
            $notes = sanitize($_POST['notes']);
            
            $sql = "INSERT INTO bank_accounts (bank_name, account_name, account_number, account_type, current_balance, currency, status, notes, created_by, last_updated) 
                    VALUES ('$bank_name', '$account_name', '$account_number', '$account_type', '$current_balance', '$currency', '$status', '$notes', '$user_id', CURDATE())";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Bank account added successfully!";
            } else {
                $error = "Error adding bank account: " . mysqli_error($conn);
            }
        }
    }
}

// Get all social accounts
$social_sql = "SELECT sa.*, u.full_name as updated_by_name 
               FROM social_accounts sa 
               LEFT JOIN users u ON sa.created_by = u.id 
               ORDER BY 
                 CASE platform 
                   WHEN 'YouTube' THEN 1
                   WHEN 'RouteNote' THEN 2
                   WHEN 'TikTok' THEN 3
                   WHEN 'Facebook' THEN 4
                   WHEN 'Instagram' THEN 5
                   ELSE 6
                 END,
                 platform";
$social_result = mysqli_query($conn, $social_sql);

// Calculate totals
$total_followers = 0;
$total_monthly_revenue = 0;
$social_accounts = [];

if ($social_result) {
    while ($account = mysqli_fetch_assoc($social_result)) {
        $social_accounts[] = $account;
        $total_followers += $account['followers_count'];
        $total_monthly_revenue += $account['monthly_revenue'];
    }
}

// Get bank accounts
$bank_sql = "SELECT ba.*, u.full_name as created_by_name 
             FROM bank_accounts ba 
             LEFT JOIN users u ON ba.created_by = u.id 
             WHERE ba.status = 'active' 
             ORDER BY ba.current_balance DESC";
$bank_result = mysqli_query($conn, $bank_sql);
$bank_accounts = [];
$total_bank_balance = 0;

if ($bank_result) {
    while ($bank = mysqli_fetch_assoc($bank_result)) {
        $bank_accounts[] = $bank;
        $total_bank_balance += $bank['current_balance'];
    }
}

// Get revenue history for charts (last 6 months)
$revenue_history_sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        SUM(new_revenue) as total_revenue
                        FROM revenue_log 
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 6";
$revenue_history_result = mysqli_query($conn, $revenue_history_sql);
$revenue_history = [];
$revenue_months = [];
$revenue_amounts = [];

if ($revenue_history_result) {
    while ($row = mysqli_fetch_assoc($revenue_history_result)) {
        $revenue_history[] = $row;
        $revenue_months[] = date('M Y', strtotime($row['month'] . '-01'));
        $revenue_amounts[] = $row['total_revenue'];
    }
    $revenue_months = array_reverse($revenue_months);
    $revenue_amounts = array_reverse($revenue_amounts);
}

// Get follower growth history
$followers_history_sql = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          SUM(new_followers) as total_followers
                          FROM revenue_log 
                          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                          ORDER BY month DESC
                          LIMIT 6";
$followers_history_result = mysqli_query($conn, $followers_history_sql);
$followers_history = [];
$follower_months = [];
$follower_counts = [];

if ($followers_history_result) {
    while ($row = mysqli_fetch_assoc($followers_history_result)) {
        $followers_history[] = $row;
        $follower_months[] = date('M Y', strtotime($row['month'] . '-01'));
        $follower_counts[] = $row['total_followers'];
    }
    $follower_months = array_reverse($follower_months);
    $follower_counts = array_reverse($follower_counts);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts - Lighthouse Ministers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Main Content Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: #333;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .sidebar-container.collapsed ~ .main-content {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 1rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
        }
        
        .summary-card.followers::before { background: #007bff; }
        .summary-card.revenue::before { background: #28a745; }
        .summary-card.bank::before { background: #ffc107; }
        
        .summary-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.1;
        }
        
        .summary-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .summary-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .summary-description {
            font-size: 0.9rem;
            color: #888;
        }
        
        .summary-change {
            font-size: 0.85rem;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .summary-change.positive { color: #28a745; }
        .summary-change.negative { color: #dc3546; }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .chart-header {
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-size: 1.2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .chart-header p {
            font-size: 0.9rem;
            color: #666;
        }
        
        .chart-container {
            height: 250px;
            position: relative;
        }
        
        /* Social Accounts Section */
        .social-accounts-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: #222;
            font-weight: 600;
        }
        
        .add-account-btn {
            background: #000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-account-btn:hover {
            background: #333;
        }
        
        /* Platform Cards */
        .platform-cards {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .platform-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .platform-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .platform-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .platform-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .platform-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .platform-icon.youtube { background: #ff0000; }
        .platform-icon.tiktok { background: #000000; }
        .platform-icon.routenote { background: #00a859; }
        .platform-icon.facebook { background: #1877f2; }
        .platform-icon.instagram { background: #e4405f; }
        .platform-icon.other { background: #6c757d; }
        
        .platform-info h3 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .platform-info a {
            font-size: 0.85rem;
            color: #007bff;
            text-decoration: none;
        }
        
        .platform-info a:hover {
            text-decoration: underline;
        }
        
        .platform-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .platform-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .platform-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .platform-actions {
            display: flex;
            gap: 5px;
        }
        
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: opacity 0.3s;
        }
        
        .edit-btn {
            background: #007bff;
            color: white;
        }
        
        .delete-btn {
            background: #dc3546;
            color: white;
        }
        
        .edit-btn:hover, .delete-btn:hover {
            opacity: 0.9;
        }
        
        /* Bank Accounts Section */
        .bank-accounts-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .bank-accounts-grid {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .bank-accounts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .bank-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .bank-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .bank-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(-30%, 30%);
        }
        
        .bank-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .bank-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .bank-type {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .bank-icon {
            font-size: 1.8rem;
            opacity: 0.7;
        }
        
        .bank-balance {
            text-align: center;
            margin: 20px 0;
        }
        
        .balance-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .balance-amount {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .bank-details {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 15px;
        }
        
        .bank-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .update-balance-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.3s;
        }
        
        .update-balance-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.2rem;
            color: #222;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #000;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-submit {
            background: #000;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            background: #333;
        }
        
        /* Platform Colors */
        .platform-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
        }
        
        .platform-youtube { background: #ff0000; }
        .platform-tiktok { background: #000000; }
        .platform-routenote { background: #00a859; }
        .platform-facebook { background: #1877f2; }
        .platform-instagram { background: #e4405f; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #444;
        }
        
        /* Permission Notice */
        .permission-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Accounts & Revenue</h1>
            <p>Track social media followers, monthly revenue, and bank account balances.</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card followers">
                <div class="summary-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="summary-title">Total Followers</div>
                <div class="summary-value"><?php echo number_format($total_followers); ?></div>
                <div class="summary-description">Across all social platforms</div>
                <div class="summary-change positive">
                    <i class="fas fa-arrow-up"></i>
                    12% increase this month
                </div>
            </div>
            
            <div class="summary-card revenue">
                <div class="summary-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="summary-title">Monthly Revenue</div>
                <div class="summary-value">KES <?php echo number_format($total_monthly_revenue, 2); ?></div>
                <div class="summary-description">Total from all platforms</div>
                <div class="summary-change positive">
                    <i class="fas fa-arrow-up"></i>
                    8% increase from last month
                </div>
            </div>
            
            <div class="summary-card bank">
                <div class="summary-icon">
                    <i class="fas fa-university"></i>
                </div>
                <div class="summary-title">Bank Balance</div>
                <div class="summary-value">KES <?php echo number_format($total_bank_balance, 2); ?></div>
                <div class="summary-description">Total in all bank accounts</div>
                <div class="summary-change positive">
                    <i class="fas fa-arrow-up"></i>
                    15% growth this quarter
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Monthly Revenue Trend</h3>
                    <p>Revenue generated over the last 6 months</p>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Followers Growth</h3>
                    <p>New followers gained over the last 6 months</p>
                </div>
                <div class="chart-container">
                    <canvas id="followersChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Social Accounts Section -->
        <div class="social-accounts-section">
            <div class="section-header">
                <h2>Social Media Accounts</h2>
                <?php if ($can_edit_social): ?>
                <button class="add-account-btn" onclick="openAddSocialModal()">
                    <i class="fas fa-plus"></i> Add Account
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (!$can_edit_social && $user_role != 'chairman' && $user_role != 'admin'): ?>
            <?php endif; ?>
            
            <div class="platform-cards">
                <?php if (!empty($social_accounts)): ?>
                    <?php foreach ($social_accounts as $account): ?>
                    <div class="platform-card">
                        <div class="platform-header">
                            <div class="platform-icon <?php echo strtolower($account['platform']); ?>">
                                <?php 
                                $icons = [
                                    'YouTube' => 'fab fa-youtube',
                                    'TikTok' => 'fab fa-tiktok',
                                    'RouteNote' => 'fas fa-music',
                                    'Facebook' => 'fab fa-facebook',
                                    'Instagram' => 'fab fa-instagram'
                                ];
                                $icon = $icons[$account['platform']] ?? 'fas fa-globe';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="platform-info">
                                <h3><?php echo htmlspecialchars($account['platform']); ?></h3>
                                <div class="platform-badge platform-<?php echo strtolower($account['platform']); ?>">
                                    <?php echo htmlspecialchars($account['account_name']); ?>
                                </div>
                                <?php if ($account['account_url']): ?>
                                <a href="<?php echo htmlspecialchars($account['account_url']); ?>" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Visit Profile
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="platform-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($account['followers_count']); ?></div>
                                <div class="stat-label">Followers</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">KES <?php echo number_format($account['monthly_revenue'], 2); ?></div>
                                <div class="stat-label">Monthly Revenue</div>
                            </div>
                        </div>
                        
                        <?php if ($account['notes']): ?>
                        <div class="notes" style="font-size: 0.85rem; color: #666; margin-bottom: 10px; padding: 8px; background: rgba(0,0,0,0.03); border-radius: 4px;">
                            <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(substr($account['notes'], 0, 100)); ?>
                            <?php if (strlen($account['notes']) > 100): ?>...<?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="platform-footer">
                            <span class="platform-status status-<?php echo $account['status']; ?>">
                                <?php echo ucfirst($account['status']); ?>
                            </span>
                            
                            <?php if ($can_edit_social): ?>
                            <div class="platform-actions">
                                <button class="edit-btn" onclick="openEditSocialModal(<?php echo htmlspecialchars(json_encode($account)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if (isAdmin()): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                    <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                    <button type="submit" name="delete_social_account" class="delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Social Accounts Found</h3>
                        <p>No social media accounts have been added yet.</p>
                        <?php if ($can_edit_social): ?>
                        <button class="add-account-btn" onclick="openAddSocialModal()" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Add First Account
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bank Accounts Section -->
        <div class="bank-accounts-section">
            <div class="section-header">
                <h2>Bank Accounts</h2>
                <?php if ($can_edit_bank): ?>
                <button class="add-account-btn" onclick="openAddBankModal()">
                    <i class="fas fa-plus"></i> Add Bank Account
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (!$can_edit_bank && $user_role != 'chairman' && $user_role != 'admin'): ?>
            <?php endif; ?>
            
            <div class="bank-accounts-grid">
                <?php if (!empty($bank_accounts)): ?>
                    <?php foreach ($bank_accounts as $bank): ?>
                    <div class="bank-card">
                        <div class="bank-header">
                            <div>
                                <div class="bank-name"><?php echo htmlspecialchars($bank['bank_name']); ?></div>
                                <div class="bank-type"><?php echo htmlspecialchars($bank['account_type']); ?> Account</div>
                            </div>
                            <div class="bank-icon">
                                <i class="fas fa-university"></i>
                            </div>
                        </div>
                        
                        <div class="bank-balance">
                            <div class="balance-label">Current Balance</div>
                            <div class="balance-amount">KES <?php echo number_format($bank['current_balance'], 2); ?></div>
                        </div>
                        
                        <div class="bank-details">
                            <div>Account: <?php echo htmlspecialchars($bank['account_name']); ?></div>
                            <div>Number: <?php echo htmlspecialchars($bank['account_number']); ?></div>
                            <?php if ($bank['last_updated']): ?>
                            <div>Last Updated: <?php echo date('M j, Y', strtotime($bank['last_updated'])); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bank-footer">
                            <div>
                                <span class="platform-status status-active">
                                    <?php echo ucfirst($bank['status']); ?>
                                </span>
                            </div>
                            <?php if ($can_edit_bank): ?>
                            <button class="update-balance-btn" onclick="openUpdateBalanceModal(<?php echo htmlspecialchars(json_encode($bank)); ?>)">
                                <i class="fas fa-sync-alt"></i> Update Balance
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-university"></i>
                        <h3>No Bank Accounts Found</h3>
                        <p>No bank accounts have been added yet.</p>
                        <?php if ($can_edit_bank): ?>
                        <button class="add-account-btn" onclick="openAddBankModal()" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Add First Bank Account
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Accounts &copy; <?php echo date('Y'); ?> | Last updated: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>All amounts in Kenyan Shillings (KES)</p>
        </div>
    </div>
    
    <!-- Add Social Account Modal (Publicity Leader, Chairman, Admin only) -->
    <?php if ($can_edit_social): ?>
    <div id="addSocialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Social Media Account</h3>
                <button class="close-modal" onclick="closeAddSocialModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addSocialForm">
                    <div class="form-group">
                        <label for="platform">Platform *</label>
                        <select id="platform" name="platform" class="form-control" required>
                            <option value="">-- Select Platform --</option>
                            <option value="YouTube">YouTube</option>
                            <option value="TikTok">TikTok</option>
                            <option value="RouteNote">RouteNote</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_name">Account Name *</label>
                        <input type="text" id="account_name" name="account_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_url">Profile URL</label>
                        <input type="url" id="account_url" name="account_url" class="form-control" placeholder="https://">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="followers_count">Followers Count *</label>
                            <input type="number" id="followers_count" name="followers_count" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="monthly_revenue">Monthly Revenue (KES) *</label>
                            <input type="number" id="monthly_revenue" name="monthly_revenue" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency">Currency *</label>
                            <select id="currency" name="currency" class="form-control" required>
                                <option value="KES" selected>KES - Kenyan Shilling</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes & Comments</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_social_account" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Social Account Modal (Publicity Leader, Chairman, Admin only) -->
    <div id="editSocialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Social Media Account</h3>
                <button class="close-modal" onclick="closeEditSocialModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editSocialForm">
                    <input type="hidden" id="edit_account_id" name="account_id">
                    
                    <div class="form-group">
                        <label for="edit_platform">Platform *</label>
                        <select id="edit_platform" name="platform" class="form-control" required>
                            <option value="YouTube">YouTube</option>
                            <option value="TikTok">TikTok</option>
                            <option value="RouteNote">RouteNote</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_account_name">Account Name *</label>
                        <input type="text" id="edit_account_name" name="account_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_account_url">Profile URL</label>
                        <input type="url" id="edit_account_url" name="account_url" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_followers_count">Followers Count *</label>
                            <input type="number" id="edit_followers_count" name="followers_count" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_monthly_revenue">Monthly Revenue (KES) *</label>
                            <input type="number" id="edit_monthly_revenue" name="monthly_revenue" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_currency">Currency *</label>
                            <select id="edit_currency" name="currency" class="form-control" required>
                                <option value="KES">KES - Kenyan Shilling</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status *</label>
                            <select id="edit_status" name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notes & Comments</label>
                        <textarea id="edit_notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="edit_social_account" class="btn-submit">
                            <i class="fas fa-save"></i> Update Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Update Bank Balance Modal (Treasurer, Chairman, Admin only) -->
    <?php if ($can_edit_bank): ?>
    <div id="updateBalanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Bank Account Balance</h3>
                <button class="close-modal" onclick="closeUpdateBalanceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateBalanceForm">
                    <input type="hidden" id="update_bank_id" name="bank_id">
                    
                    <div class="form-group">
                        <label>Bank Account</label>
                        <div class="form-control" style="background: #f8f9fa;" id="bankAccountDisplay"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_balance">New Balance (KES) *</label>
                        <input type="number" id="new_balance" name="new_balance" class="form-control" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_type">Transaction Type *</label>
                        <select id="transaction_type" name="transaction_type" class="form-control" required>
                            <option value="deposit">Deposit</option>
                            <option value="withdrawal">Withdrawal</option>
                            <option value="adjustment">Balance Adjustment</option>
                            <option value="interest">Interest Earned</option>
                            <option value="fee">Bank Fee</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="update_details">Details *</label>
                        <textarea id="update_details" name="details" class="form-control" rows="3" 
                                  placeholder="Enter details about this transaction..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_bank_balance" class="btn-submit">
                            <i class="fas fa-sync-alt"></i> Update Balance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Bank Account Modal (Treasurer, Chairman, Admin only) -->
    <div id="addBankModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Bank Account</h3>
                <button class="close-modal" onclick="closeAddBankModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addBankForm">
                    <div class="form-group">
                        <label for="bank_name">Bank Name *</label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_name">Account Name *</label>
                        <input type="text" id="account_name" name="account_name" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="account_number">Account Number *</label>
                            <input type="text" id="account_number" name="account_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="account_type">Account Type *</label>
                            <select id="account_type" name="account_type" class="form-control" required>
                                <option value="Current Account">Current Account</option>
                                <option value="Savings Account">Savings Account</option>
                                <option value="Fixed Deposit">Fixed Deposit</option>
                                <option value="Business Account">Business Account</option>
                                <option value="Church Account">Church Account</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_balance">Current Balance (KES) *</label>
                            <input type="number" id="current_balance" name="current_balance" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="currency">Currency *</label>
                            <select id="currency" name="currency" class="form-control" required>
                                <option value="KES" selected>KES - Kenyan Shilling</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes & Comments</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_bank_account" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Bank Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script>
        // Modal Functions
        function openAddSocialModal() {
            document.getElementById('addSocialModal').style.display = 'flex';
        }
        
        function closeAddSocialModal() {
            document.getElementById('addSocialModal').style.display = 'none';
        }
        
        function openEditSocialModal(account) {
            document.getElementById('editSocialModal').style.display = 'flex';
            
            // Populate form with account data
            document.getElementById('edit_account_id').value = account.id;
            document.getElementById('edit_platform').value = account.platform;
            document.getElementById('edit_account_name').value = account.account_name;
            document.getElementById('edit_account_url').value = account.account_url || '';
            document.getElementById('edit_followers_count').value = account.followers_count;
            document.getElementById('edit_monthly_revenue').value = parseFloat(account.monthly_revenue).toFixed(2);
            document.getElementById('edit_currency').value = account.currency;
            document.getElementById('edit_status').value = account.status;
            document.getElementById('edit_notes').value = account.notes || '';
        }
        
        function closeEditSocialModal() {
            document.getElementById('editSocialModal').style.display = 'none';
        }
        
        function openUpdateBalanceModal(bank) {
            document.getElementById('updateBalanceModal').style.display = 'flex';
            
            // Populate form with bank data
            document.getElementById('update_bank_id').value = bank.id;
            document.getElementById('bankAccountDisplay').textContent = bank.bank_name + ' - ' + bank.account_name;
            document.getElementById('new_balance').value = parseFloat(bank.current_balance).toFixed(2);
            document.getElementById('new_balance').focus();
            document.getElementById('new_balance').select();
        }
        
        function closeUpdateBalanceModal() {
            document.getElementById('updateBalanceModal').style.display = 'none';
        }
        
        function openAddBankModal() {
            document.getElementById('addBankModal').style.display = 'flex';
        }
        
        function closeAddBankModal() {
            document.getElementById('addBankModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addSocialModal', 'editSocialModal', 'updateBalanceModal', 'addBankModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && event.target === modal) {
                    eval('close' + modalId.charAt(0).toUpperCase() + modalId.slice(1) + '()');
                }
            });
        }
        
        // Update main content margin based on sidebar
        document.addEventListener('DOMContentLoaded', function() {
            function updateMainContentMargin() {
                const sidebar = document.querySelector('.sidebar-container');
                const mainContent = document.getElementById('mainContent');
                
                if (sidebar && mainContent) {
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = '70px';
                    } else {
                        mainContent.style.marginLeft = '250px';
                    }
                }
            }
            
            updateMainContentMargin();
            
            // Watch for sidebar changes
            const sidebar = document.querySelector('.sidebar-container');
            if (sidebar) {
                const observer = new MutationObserver(updateMainContentMargin);
                observer.observe(sidebar, { 
                    attributes: true, 
                    attributeFilter: ['class'] 
                });
            }
            
            // Initialize Charts
            initializeCharts();
        });
        
        // Charts
        function initializeCharts() {
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($revenue_months); ?>,
                    datasets: [{
                        label: 'Monthly Revenue (KES)',
                        data: <?php echo json_encode($revenue_amounts); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'KES ' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Followers Chart
            const followersCtx = document.getElementById('followersChart').getContext('2d');
            const followersChart = new Chart(followersCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($follower_months); ?>,
                    datasets: [{
                        label: 'New Followers',
                        data: <?php echo json_encode($follower_counts); ?>,
                        backgroundColor: '#007bff',
                        borderColor: '#0056b3',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>