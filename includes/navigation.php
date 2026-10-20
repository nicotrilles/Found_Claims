<?php
$current_page = basename($_SERVER['PHP_SELF']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>

<style>
    .site-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 30px;
        padding-bottom: 16px;
        border-bottom: 1px solid #ddd;
        font-family: Arial, sans-serif;
    }

    .site-brand {
        color: #111;
        font-size: 20px;
        font-weight: bold;
        text-decoration: none;
    }

    .site-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .site-nav a {
        display: inline-block;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        color: #111;
        text-decoration: none;
        font-size: 14px;
    }

    .site-nav a:hover,
    .site-nav a.active {
        background: #111;
        border-color: #111;
        color: #fff;
    }


.header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}

.staff-area {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
}

.staff-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #e8f2ff;
    color: #1d3557;
    border: 1px solid #c9defa;
    border-radius: 20px;
    font-size: 13px;
    white-space: nowrap;
}

.staff-dot {
    width: 8px;
    height: 8px;
    background: #249653;
    border-radius: 50%;
    flex-shrink: 0;
}

.site-nav .logout-link {
    border-color: #ddd;
    background: #fff;
    color: #b42318;
}

.site-nav .logout-link:hover {
    background: #b42318;
    border-color: #b42318;
    color: #fff;
}

@media (max-width: 600px) {
    .header-right {
        width: 100%;
        justify-content: flex-start;
    }

    .staff-area {
        justify-content: flex-start;
    }
}

    @media (max-width: 600px) {
        .site-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

</style>


<header class="site-header">
    <a class="site-brand" href="item.php">
        Found Items &amp; Claims Management System
    </a>

    <div class="header-right">
        <nav class="site-nav" aria-label="Main navigation">
            <a href="item.php"
               class="<?= $current_page === 'item.php' ? 'active' : '' ?>">
                Found Items
            </a>

            <a href="search_item.php"
               class="<?= $current_page === 'search_item.php' ? 'active' : '' ?>">
                Search Items
            </a>

            <a href="report_item.php"
               class="<?= $current_page === 'report_item.php' ? 'active' : '' ?>">
                Report Item
            </a>

            <a href="claim.php"
               class="<?= $current_page === 'claim.php' ? 'active' : '' ?>">
                Manage Claims
            </a>

            <a href="staff_comments.php"
                class="<?= $current_page === 'staff_comments.php' ? 'active' : '' ?>">
                Student Comments
            </a>
        </nav>

        <?php if (isset($_SESSION['staff_id'])): ?>
            <div class="staff-area">
                <span class="staff-indicator">
                    <span class="staff-dot"></span>
                    <?= htmlspecialchars(
                        $_SESSION['staff_name'] ?? 'Staff',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <a class="logout-link" href="staff_logout.php">
                    Logout
                </a>
            </div>
        <?php endif; ?>
    </div>
</header>
