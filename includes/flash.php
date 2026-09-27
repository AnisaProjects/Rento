<?php
// Flash messages: the one component for success / error / info notices on public pages.
//   echo flash_message('error', $error);        show a message now
//   flash_set('success', 'Saved'); redirect;    show it on the next page view
//   echo flash_render();                        print (and clear) queued messages

const FLASH_LABELS = ['success' => 'SUCCESS', 'error' => 'ERROR', 'warning' => 'NOTE', 'info' => 'INFO'];

// Markup for one message; empty string when there is no message.
function flash_message(string $type, ?string $message, bool $allowHtml = false): string
{
    if ($message === null || $message === '') {
        return '';
    }
    $type = isset(FLASH_LABELS[$type]) ? $type : 'info';
    $role = $type === 'error' ? 'alert' : 'status';
    $body = $allowHtml ? $message : htmlentities($message);
    return '<div class="rento-flash rento-flash--' . $type . '" role="' . $role . '"><strong>'
        . FLASH_LABELS[$type] . '</strong>: ' . $body . '</div>';
}

// Queues a message for the next page view (post/redirect/get).
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

// Re-open a modal (e.g. 'loginform') on the next page view, so a failed login or
// sign-up lands the visitor back in the form.
function flash_open_modal(string $modalId): void
{
    $_SESSION['flash_modal'] = $modalId;
}

// Queued messages as toasts (rendered once per page by includes/scripts.php).
function flash_render_toasts(): string
{
    $icons = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $message]) {
        $type = isset($icons[$type]) ? $type : 'info';
        // Errors stay until closed (WCAG 2.2.1). Screen readers hear the message through the live
        // regions below, which the page script fills after load (a region that is already filled on
        // load is often not announced).
        $html .= '<div class="r-toast r-toast--' . $type . ' toast" data-r-announce="' . ($type === 'error' ? 'alert' : 'status') . '"'
            . ($type === 'error' ? ' data-bs-autohide="false"' : ' data-bs-delay="8000"') . '>'
            . '<i class="fa ' . $icons[$type] . ' r-toast__icon" aria-hidden="true"></i>'
            . '<div class="r-toast__body">' . htmlentities($message) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    $modal = $_SESSION['flash_modal'] ?? '';
    unset($_SESSION['flash_modal']);
    $out = '<div class="visually-hidden" id="r-live-status" role="status" aria-live="polite" aria-atomic="true"></div>'
        . '<div class="visually-hidden" id="r-live-alert" role="alert" aria-atomic="true"></div>';
    $out .= $html === '' ? '' : '<div class="r-toasts">' . $html . '</div>';
    if (preg_match('/^[a-z-]+$/', $modal)) {
        $out .= '<div id="r-open-modal" data-modal="' . $modal . '" hidden></div>';
    }
    return $out;
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $message]) {
        $html .= flash_message($type, $message);
    }
    unset($_SESSION['flash']);
    return $html;
}
?>
