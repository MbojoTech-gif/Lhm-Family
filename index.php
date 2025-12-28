<?php
// index.php - Loading page with animation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lighthouse Ministers - Loading</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/images/group.jpg') center/cover no-repeat fixed;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1;
        }
        
        .loading-container {
            text-align: center;
            max-width: 400px;
            padding: 30px;
            position: relative;
            z-index: 2;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
            overflow: hidden;
            border: 2px solid white;
        }
        
        .logo img {
            width: 140%;
            height: 140%;
            object-fit: contain;
            padding: 15px;
        }
        
        .loading-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            color: white;
        }
        
        .loading-subtitle {
            font-size: 1rem;
            color: #f0f0f0;
            margin-bottom: 25px;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .lighthouse-beam {
            position: relative;
            width: 4px;
            height: 70px;
            background: linear-gradient(to top, transparent, #ffffff);
            margin: 0 auto 25px;
            animation: beam 2s infinite alternate;
            border-radius: 2px;
        }
        
        @keyframes beam {
            0% {
                opacity: 0.3;
                box-shadow: 0 0 8px #fff;
            }
            100% {
                opacity: 1;
                box-shadow: 0 0 20px #fff, 0 0 40px #fff;
            }
        }
        
        .loading-text {
            font-size: 0.95rem;
            color: #ddd;
            margin-top: 15px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .loading-dots {
            display: inline-block;
        }
        
        .loading-dots span {
            animation: dots 1.5s infinite;
            opacity: 0;
        }
        
        .loading-dots span:nth-child(2) {
            animation-delay: 0.5s;
        }
        
        .loading-dots span:nth-child(3) {
            animation-delay: 1s;
        }
        
        @keyframes dots {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }
        
        .verse {
            margin-top: 25px;
            font-style: italic;
            color: #ccc;
            font-size: 0.8rem;
            max-width: 350px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="logo-container">
            <div class="logo">
                <img src="assets/images/logo1.png" alt="Lighthouse Ministers Logo">
            </div>
            <h1 class="loading-title">LIGHTHOUSE MINISTERS</h1>
            <p class="loading-subtitle">Family Portal</p>
        </div>
        
        <div class="lighthouse-beam"></div>
        
        <div class="loading-text">
            Loading Portal<span class="loading-dots">
                <span>.</span><span>.</span><span>.</span>
            </span>
        </div>
        
        <p class="verse">"Let your light so shine before men, that they may see your good works and glorify your Father in heaven." - Matthew 5:16</p>
    </div>

    <script>
        // Redirect to login page after 5 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);
    </script>
</body>
</html>