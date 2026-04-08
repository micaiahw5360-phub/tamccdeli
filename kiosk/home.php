<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$greeting = '';
$hour = date('H');
if ($hour < 12) $greeting = "🌅 Good Morning! Ready for Breakfast? 🌅";
elseif ($hour < 18) $greeting = "☀️ Good Afternoon! Lunch Time? ☀️";
else $greeting = "🌙 Good Evening! Dinner's Waiting! 🌙";

$fun_facts = [
    "🍔 Our famous bake & bulla sells out every morning by 10 AM!",
    "🎉 Students love our combo deals - best value on campus!",
    "🌿 We use fresh local ingredients from Grenadian farms!",
    "⭐ Over 500 happy meals served this week alone!",
    "🔥 Try our signature Marryshow Burger - student approved!",
    "💰 Wallet payments get you 5% cashback!"
];
$random_fact = $fun_facts[array_rand($fun_facts)];

$page_title = "Welcome | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Kiosk Home Page */
        .kiosk-home {
            background: linear-gradient(135deg, rgba(0,0,0,0.65), rgba(0,0,0,0.75)), 
                        url('/assets/images/main.menu.png') center/cover fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .kiosk-home::before {
            content: '🍔 🍕 🥤 🍳 🍰 🍦 🥗 🌮 🍪 🧃';
            position: absolute;
            font-size: 6rem;
            white-space: nowrap;
            opacity: 0.08;
            animation: slideEmojis 30s linear infinite;
            pointer-events: none;
            bottom: 0;
        }

        @keyframes slideEmojis {
            0% { transform: translateX(-20%); }
            100% { transform: translateX(20%); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        @keyframes cardPopIn {
            0% {
                opacity: 0;
                transform: scale(0.8) translateY(50px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.02); opacity: 0.95; }
        }

        .home-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            border-radius: 4rem;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 750px;
            width: 100%;
            animation: cardPopIn 0.6s cubic-bezier(0.34, 1.2, 0.64, 1);
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            border: 3px solid rgba(255,255,255,0.5);
            position: relative;
            z-index: 1;
        }

        .home-card .logo {
            max-width: 160px;
            margin-bottom: 1.5rem;
            animation: bounce 2s infinite;
        }

        .home-card h1 {
            font-size: 3.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #FF6B35, #FF4757, #6C5CE7, #FF69B4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: none;
        }

        .greeting-text {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 2rem;
        }

        .fun-fact-card {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            padding: 1.2rem;
            border-radius: 3rem;
            margin: 2rem 0;
            color: white;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        .fun-fact-card span {
            font-size: 1.8rem;
            margin-right: 0.5rem;
        }

        .fun-fact-card p {
            font-size: 1.2rem;
            margin: 0;
        }

        .start-btn {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border: none;
            padding: 1.2rem 3.5rem;
            font-size: 2rem;
            font-weight: 800;
            border-radius: 4rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.4);
            text-decoration: none;
            display: inline-block;
            letter-spacing: 2px;
        }

        .start-btn:hover {
            transform: scale(1.08) translateY(-5px);
            box-shadow: 0 20px 40px rgba(255, 107, 53, 0.6);
        }

        .start-btn:active {
            transform: scale(0.96);
        }

        @media (max-width: 768px) {
            .home-card {
                padding: 2rem;
            }
            .home-card h1 {
                font-size: 2rem;
            }
            .greeting-text {
                font-size: 1.3rem;
            }
            .start-btn {
                font-size: 1.3rem;
                padding: 0.8rem 2rem;
            }
            .fun-fact-card p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="kiosk-home">
        <div class="home-card">
            <img src="/assets/images/ta-logo-1536x512.png" alt="TAMCC Deli" class="logo">
            <h1>🍔 TAMCC DELI 🍕<br><span style="font-size: 1.5rem;">Marryshow Mealhouse</span></h1>
            <div class="greeting-text"><?= $greeting ?></div>
            <div class="fun-fact-card">
                <span>🎉 FUN FACT! 🎉</span>
                <p><?= $random_fact ?></p>
            </div>
            <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="start-btn">
                🍽️ START YOUR ORDER 🍽️
            </a>
        </div>
    </div>
</body>
</html>