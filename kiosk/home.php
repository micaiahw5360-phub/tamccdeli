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
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2b4c7c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .kiosk-home { width: 100%; max-width: 1100px; animation: fadeInUp 0.6s ease-out; }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .home-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: 3rem;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .logo { max-width: 180px; margin-bottom: 1.5rem; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-8px); } }
        h1 {
            font-size: 3rem;
            background: linear-gradient(135deg, #1e3c72, #2b4c7c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }
        .greeting-text { font-size: 1.8rem; font-weight: 600; color: #1e3c72; margin-bottom: 2rem; }
        .fun-fact-card {
            background: linear-gradient(135deg, #1e3c72, #2b4c7c);
            padding: 1.2rem;
            border-radius: 2rem;
            margin: 2rem 0;
            color: white;
            font-weight: bold;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .start-btn {
            background: linear-gradient(135deg, #1e3c72, #2b4c7c);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            font-size: 1.6rem;
            font-weight: 800;
            border-radius: 3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 10px 20px rgba(30,60,114,0.3);
        }
        .start-btn:hover { transform: scale(1.05); background: linear-gradient(135deg, #2b4c7c, #1e3c72); }
        @media (max-width: 768px) {
            .home-card { padding: 2rem 1.5rem; }
            h1 { font-size: 2rem; }
            .greeting-text { font-size: 1.2rem; }
            .start-btn { font-size: 1.2rem; padding: 0.8rem 1.8rem; }
        }
    </style>
</head>
<body>
<div class="kiosk-home">
    <div class="home-card">
        <img src="/assets/images/ta-logo-1536x512.png" alt="TAMCC Deli" class="logo">
        <h1>🍔 TAMCC DELI 🍕<br><span style="font-size: 1.2rem;">Marryshow Mealhouse</span></h1>
        <div class="greeting-text"><?= $greeting ?></div>
        <div class="fun-fact-card">
            <span>🎉 FUN FACT! 🎉</span>
            <p><?= $random_fact ?></p>
        </div>
        <a href="<?= kiosk_url('/kiosk/menu.php') ?>" class="start-btn">🍽️ START YOUR ORDER 🍽️</a>
    </div>
</div>
</body>
</html>