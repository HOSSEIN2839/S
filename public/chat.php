<?php
declare(strict_types=1);
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دستیار خرید هوشمند</title>
    <link rel="stylesheet" href="css/chat.css">
</head>
<body>
<main class="chat">
    <header class="chat__header">
        <h1>دستیار خرید هوشمند</h1>
        <button id="new-chat" type="button" class="btn-ghost">گفتگوی جدید</button>
    </header>

    <section id="messages" class="chat__messages" aria-live="polite"></section>

    <div id="status" class="chat__status" role="status"></div>

    <div class="chat__composer">
        <textarea id="input" rows="1" maxlength="500" placeholder="مثلاً: یک ساعت مردانه تا ۶ میلیون می‌خواهم" aria-label="پیام شما"></textarea>
        <button id="send" type="button" class="btn-primary">ارسال</button>
    </div>
</main>
<script src="js/chat.js"></script>
</body>
</html>
