<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post() || !csrf_verify()) {
    $user = current_user();
    set_flash('error', 'Use the Log out button to end your session.');
    if ($user) {
        redirect(home_path_for_role($user['role']) ?? 'login.php');
    }
    redirect('login.php');
}

destroy_session();
start_app_session();
set_flash('success', 'You have logged out.');
redirect('login.php');
