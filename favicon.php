<?php
// favicon.php - Universal favicon for Lighthouse Ministers

// Path to logo
$logo_path = 'assets/images/logo1.png';

// Cache busting version
$cache_buster = file_exists($logo_path) ? filemtime($logo_path) : time();

?>
<!-- Direct PNG favicon (modern browsers) -->
<link rel="icon" type="image/png" href="<?php echo $logo_path; ?>?v=<?php echo $cache_buster; ?>">

<!-- For old browsers that need ICO format (fallback) -->
<link rel="shortcut icon" href="<?php echo $logo_path; ?>?v=<?php echo $cache_buster; ?>" type="image/x-icon">

<!-- Apple devices -->
<link rel="apple-touch-icon" href="<?php echo $logo_path; ?>?v=<?php echo $cache_buster; ?>">

<!-- Theme color -->
<meta name="theme-color" content="#000000">