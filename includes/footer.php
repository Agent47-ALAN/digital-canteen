<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}
?>
    </main>
    <footer class="site-footer">
        <div class="shell">
            <p>Digital Canteen Token Management System</p>
        </div>
    </footer>
</body>
</html>
