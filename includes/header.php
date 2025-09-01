<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_DESCRIPTION; ?>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a></h1>
                    <p class="tagline">Professional Transport Solutions</p>
                </div>
                <?php include 'nav.php'; ?>
            </div>
        </div>
    </header>
    
    <main class="main-content">
